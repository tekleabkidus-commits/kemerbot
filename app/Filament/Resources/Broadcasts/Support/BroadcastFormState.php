<?php

namespace App\Filament\Resources\Broadcasts\Support;

use App\Models\AudienceSegment;
use App\Models\Broadcast;
use App\Models\BroadcastButton;
use App\Models\BroadcastButtonTranslation;
use App\Models\BroadcastTranslation;
use App\Models\MediaFile;
use App\Services\MediaFileService;
use Illuminate\Support\Facades\Storage;

/**
 * Maps between the wizard/edit form state and broadcast structures: the
 * audience_filter jsonb, the recurrence definition, and an UNSAVED preview
 * Broadcast used by the in-wizard test send (nothing persists until Confirm).
 */
class BroadcastFormState
{
    /** Build the audience_filter jsonb from form fields, dropping empties. */
    public static function audienceFilter(array $state): ?array
    {
        $filter = array_filter([
            'joined_after' => $state['aud_joined_after'] ?? null,
            'joined_before' => $state['aud_joined_before'] ?? null,
            'active_last_days' => $state['aud_active_last_days'] ?? null,
            'inactive_days' => $state['aud_inactive_days'] ?? null,
            'language' => $state['aud_language'] ?? null,
            'source' => $state['aud_source'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');

        if (($state['aud_in_channel'] ?? null) !== null && ($state['aud_in_channel'] ?? '') !== '') {
            $filter['in_channel'] = (bool) $state['aud_in_channel'];
        }

        if (is_array($state['aud_user_ids'] ?? null)) {
            $filter['user_ids'] = array_values(array_map('intval', $state['aud_user_ids']));
        }
        if (! empty($state['segment_id'])) {
            $filter = [...((AudienceSegment::find($state['segment_id'])?->filter) ?? []), ...$filter];
        }

        return $filter === [] ? ['everyone' => true] : $filter;
    }

    public static function recurrence(array $state): ?array
    {
        if (($state['timing_mode'] ?? null) !== 'recurring') {
            return null;
        }

        return array_filter([
            'frequency' => $state['rec_frequency'] ?? null,
            'day' => ($state['rec_frequency'] ?? null) === 'weekly' ? ($state['rec_day'] ?? null) : null,
            'day_of_month' => ($state['rec_frequency'] ?? null) === 'monthly' ? (int) ($state['rec_day_of_month'] ?? 0) : null,
            'time' => $state['rec_time'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');
    }

    public static function templateFields(array $state): ?array
    {
        if (($state['type'] ?? null) !== 'match_card') {
            return null;
        }

        return array_filter([
            'home_team' => $state['tf_home_team'] ?? null,
            'away_team' => $state['tf_away_team'] ?? null,
            'kickoff_at' => $state['tf_kickoff_at'] ?? null,
            'odds' => array_filter([
                'home' => $state['tf_odds_home'] ?? null,
                'draw' => $state['tf_odds_draw'] ?? null,
                'away' => $state['tf_odds_away'] ?? null,
            ], fn ($v) => $v !== null && $v !== ''),
            'cta' => $state['tf_cta'] ?? null,
        ], fn ($v) => $v !== null && $v !== '' && $v !== []);
    }

    /**
     * An unsaved Broadcast carrying the form state, for test sends and
     * previews. Callback buttons render as bc:0:0 — valid protocol shape,
     * harmless no-op when tapped on a test message.
     */
    public static function previewBroadcast(array $state): Broadcast
    {
        $broadcast = new Broadcast([
            'type' => $state['type'] ?? 'standard',
            'template_fields' => self::templateFields($state),
        ]);
        $broadcast->id = 0;

        $translations = collect();

        foreach (['en' => 'text_en', 'am' => 'text_am'] as $lang => $key) {
            if (! filled($state[$key] ?? null)) {
                continue;
            }

            $translation = new BroadcastTranslation(['lang' => $lang, 'text' => $state[$key]]);
            $translation->setRelation('mediaFile', self::previewMedia($state["media_{$lang}_upload"] ?? null));
            $translations->push($translation);
        }

        $broadcast->setRelation('translations', $translations);

        $buttons = collect();

        foreach (($state['buttons_data'] ?? []) as $index => $row) {
            if (! filled($row['label']['en'] ?? null)) {
                continue;
            }

            $button = new BroadcastButton([
                'row' => (int) ($row['row'] ?? 0),
                'position' => $index,
                'kind' => $row['kind'] ?? 'url',
                'url' => $row['url'] ?? null,
            ]);
            $button->id = 0;

            $labels = collect();

            foreach (['en', 'am'] as $lang) {
                if (filled($row['label'][$lang] ?? null)) {
                    $labels->push(new BroadcastButtonTranslation(['lang' => $lang, 'label' => $row['label'][$lang]]));
                }
            }

            $button->setRelation('translations', $labels);
            $buttons->push($button);
        }

        $broadcast->setRelation('buttons', $buttons);

        return $broadcast;
    }

    private static function previewMedia(?string $path): ?MediaFile
    {
        if (! filled($path) || ! Storage::disk()->exists($path)) {
            return null;
        }

        $mime = (string) Storage::disk()->mimeType($path);
        $kind = MediaFileService::kindForMime($mime);

        if ($kind === null) {
            return null;
        }

        return new MediaFile([
            'path' => $path,
            'kind' => $kind,
            'mime' => $mime,
            'size' => Storage::disk()->size($path),
        ]);
    }
}
