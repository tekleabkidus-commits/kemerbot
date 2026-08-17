<?php

namespace App\Filament\Resources\Polls\Schemas;

use App\Models\Poll;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PollInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Poll')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('question_en')
                            ->label('Question (EN)')
                            ->state(fn (Poll $record): string => $record->question['en'] ?? '—'),
                        TextEntry::make('question_am')
                            ->label('Question (AM)')
                            ->state(fn (Poll $record): string => $record->question['am'] ?? '—'),
                        TextEntry::make('instances')
                            ->label('Sent to')
                            ->state(fn (Poll $record): string => number_format($record->instances()->count()).' users'),
                        TextEntry::make('is_anonymous')
                            ->label('Anonymous')
                            ->state(fn (Poll $record): string => $record->is_anonymous ? 'Yes' : 'No'),
                    ]),
                Section::make('Results')
                    ->schema([
                        TextEntry::make('results')
                            ->hiddenLabel()
                            ->state(function (Poll $record): string {
                                $options = $record->options['en'] ?? [];
                                $counts = (array) $record->answer_counts;
                                $total = array_sum(array_map('intval', $counts));

                                if ($options === []) {
                                    return 'No options.';
                                }

                                return collect($options)->map(function (string $option, int $index) use ($counts, $total): string {
                                    $votes = (int) ($counts[(string) $index] ?? $counts[$index] ?? 0);
                                    $pct = $total > 0 ? round($votes / $total * 100, 1) : 0;

                                    return "{$option}: {$votes} votes ({$pct}%)";
                                })->implode("\n");
                            }),
                    ]),
            ]);
    }
}
