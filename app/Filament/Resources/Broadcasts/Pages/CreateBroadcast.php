<?php

namespace App\Filament\Resources\Broadcasts\Pages;

use App\Enums\BotLanguage;
use App\Filament\Resources\Broadcasts\BroadcastResource;
use App\Filament\Resources\Broadcasts\Schemas\BroadcastWizard;
use App\Filament\Resources\Broadcasts\Support\BroadcastFormState;
use App\Filament\Support\FormMedia;
use App\Services\Broadcasts\BroadcastLifecycle;
use App\Services\Broadcasts\NextOccurrenceCalculator;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\HasWizard;
use Illuminate\Support\Carbon;

class CreateBroadcast extends CreateRecord
{
    use HasWizard;

    protected static string $resource = BroadcastResource::class;

    private array $wizardState = [];

    public function getSteps(): array
    {
        return BroadcastWizard::steps();
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->wizardState = $data;

        return [
            'type' => $data['type'],
            'status' => 'draft',
            'audience_filter' => BroadcastFormState::audienceFilter($data),
            'template_fields' => BroadcastFormState::templateFields($data),
            'created_by' => auth()->id(),
        ];
    }

    protected function afterCreate(): void
    {
        $state = $this->wizardState;
        $broadcast = $this->record;

        foreach (BotLanguage::cases() as $lang) {
            $text = $state['text_'.$lang->value] ?? null;
            $mediaPath = $state['media_'.$lang->value.'_upload'] ?? null;

            if (! filled($text) && ! filled($mediaPath)) {
                continue;
            }

            $broadcast->translations()->create([
                'lang' => $lang->value,
                'text' => $text,
                'media_file_id' => FormMedia::resolveMediaFileId($mediaPath, null),
            ]);
        }

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

        $lifecycle = app(BroadcastLifecycle::class);

        match ($state['timing_mode'] ?? 'now') {
            'scheduled' => $lifecycle->schedule($broadcast, Carbon::parse($state['scheduled_at'])),
            'recurring' => $lifecycle->schedule(
                $broadcast,
                app(NextOccurrenceCalculator::class)->next(BroadcastFormState::recurrence($state), now()),
                BroadcastFormState::recurrence($state),
            ),
            default => $lifecycle->start($broadcast),
        };
    }
}
