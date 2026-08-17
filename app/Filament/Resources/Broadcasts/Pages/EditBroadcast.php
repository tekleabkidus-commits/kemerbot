<?php

namespace App\Filament\Resources\Broadcasts\Pages;

use App\Enums\BotLanguage;
use App\Enums\BroadcastStatus;
use App\Filament\Resources\Broadcasts\BroadcastResource;
use App\Filament\Resources\Broadcasts\Support\BroadcastFormState;
use App\Filament\Support\FormMedia;
use App\Services\Broadcasts\BroadcastLifecycle;
use App\Services\Broadcasts\NextOccurrenceCalculator;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Carbon;

class EditBroadcast extends EditRecord
{
    protected static string $resource = BroadcastResource::class;

    private array $formState = [];

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn (): bool => $this->record->status === BroadcastStatus::Draft),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->record;
        $filter = $record->audience_filter ?? [];
        $recurrence = $record->recurrence ?? [];

        foreach (BotLanguage::cases() as $lang) {
            $translation = $record->translations->firstWhere('lang', $lang);
            $data['text_'.$lang->value] = $translation?->text;
            $data['media_'.$lang->value.'_upload'] = $translation?->mediaFile?->path;
        }

        $data['buttons_data'] = $record->buttons->map(fn ($button) => [
            'kind' => $button->kind->value,
            'url' => $button->url,
            'row' => $button->row,
            'label' => [
                'en' => $button->translations->firstWhere('lang', BotLanguage::En)?->label,
                'am' => $button->translations->firstWhere('lang', BotLanguage::Am)?->label,
            ],
        ])->all();

        $fields = $record->template_fields ?? [];
        $data['tf_home_team'] = $fields['home_team'] ?? null;
        $data['tf_away_team'] = $fields['away_team'] ?? null;
        $data['tf_kickoff_at'] = $fields['kickoff_at'] ?? null;
        $data['tf_cta'] = $fields['cta'] ?? null;
        $data['tf_odds_home'] = $fields['odds']['home'] ?? null;
        $data['tf_odds_draw'] = $fields['odds']['draw'] ?? null;
        $data['tf_odds_away'] = $fields['odds']['away'] ?? null;

        $data['aud_joined_after'] = $filter['joined_after'] ?? null;
        $data['aud_joined_before'] = $filter['joined_before'] ?? null;
        $data['aud_active_last_days'] = $filter['active_last_days'] ?? null;
        $data['aud_inactive_days'] = $filter['inactive_days'] ?? null;
        $data['aud_language'] = $filter['language'] ?? null;
        $data['aud_source'] = $filter['source'] ?? null;
        $data['aud_in_channel'] = isset($filter['in_channel']) ? (string) (int) $filter['in_channel'] : null;

        $data['timing_mode'] = $record->recurrence !== null
            ? 'recurring'
            : ($record->status === BroadcastStatus::Scheduled ? 'scheduled' : 'now');
        $data['rec_frequency'] = $recurrence['frequency'] ?? null;
        $data['rec_day'] = $recurrence['day'] ?? null;
        $data['rec_day_of_month'] = $recurrence['day_of_month'] ?? null;
        $data['rec_time'] = $recurrence['time'] ?? null;

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->formState = $data;

        return [
            'type' => $data['type'],
            'audience_filter' => BroadcastFormState::audienceFilter($data),
            'template_fields' => BroadcastFormState::templateFields($data),
        ];
    }

    protected function afterSave(): void
    {
        $state = $this->formState;
        $broadcast = $this->record;

        foreach (BotLanguage::cases() as $lang) {
            $text = $state['text_'.$lang->value] ?? null;
            $mediaPath = $state['media_'.$lang->value.'_upload'] ?? null;
            $existing = $broadcast->translations->firstWhere('lang', $lang);

            if (! filled($text) && ! filled($mediaPath)) {
                $existing?->delete();

                continue;
            }

            $broadcast->translations()->updateOrCreate(
                ['lang' => $lang->value],
                [
                    'text' => $text,
                    'media_file_id' => FormMedia::resolveMediaFileId($mediaPath, $existing?->mediaFile),
                ],
            );
        }

        // Buttons: replace wholesale — simple and unambiguous for drafts.
        $broadcast->buttons()->delete();

        foreach (($state['buttons_data'] ?? []) as $index => $row) {
            if (! filled($row['label']['en'] ?? null)) {
                continue;
            }

            $button = $broadcast->buttons()->create([
                'row' => (int) ($row['row'] ?? 0),
                'position' => $index,
                'kind' => $row['kind'] ?? 'url',
                'url' => ($row['kind'] ?? 'url') === 'url' ? ($row['url'] ?? null) : null,
            ]);

            foreach (['en', 'am'] as $lang) {
                if (filled($row['label'][$lang] ?? null)) {
                    $button->translations()->create(['lang' => $lang, 'label' => $row['label'][$lang]]);
                }
            }
        }

        $this->applyTiming($state);
    }

    private function applyTiming(array $state): void
    {
        $broadcast = $this->record->refresh();
        $lifecycle = app(BroadcastLifecycle::class);
        $mode = $state['timing_mode'] ?? 'now';

        if ($mode === 'scheduled') {
            $at = Carbon::parse($state['scheduled_at']);

            if ($broadcast->status === BroadcastStatus::Scheduled) {
                $broadcast->forceFill(['scheduled_at' => $at, 'recurrence' => null])->save();
            } else {
                $lifecycle->schedule($broadcast, $at);
            }

            return;
        }

        if ($mode === 'recurring') {
            $recurrence = BroadcastFormState::recurrence($state);
            $at = app(NextOccurrenceCalculator::class)->next($recurrence, now());

            if ($broadcast->status === BroadcastStatus::Scheduled) {
                $broadcast->forceFill(['scheduled_at' => $at, 'recurrence' => $recurrence])->save();
            } else {
                $lifecycle->schedule($broadcast, $at, $recurrence);
            }

            return;
        }

        // "now": a scheduled broadcast edited back to draft stays a draft —
        // actually sending is an explicit table action, never a form side effect.
        if ($broadcast->status === BroadcastStatus::Scheduled) {
            $lifecycle->unschedule($broadcast);
        }
    }
}
