<?php

declare(strict_types=1);

namespace App\Filament\Resources\Broadcasts\Pages;

use App\Filament\Resources\Broadcasts\BroadcastResource;
use App\Filament\Widgets\CampaignListStats;
use App\Models\Broadcast;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListBroadcasts extends ListRecords
{
    protected static string $resource = BroadcastResource::class;

    protected string $view = 'filament.resources.broadcasts.pages.list-broadcasts';

    public function getTitle(): string
    {
        return 'Campaigns';
    }

    public function getSubheading(): ?string
    {
        return 'Create, manage and optimize your Telegram campaigns.';
    }

    protected function getHeaderWidgets(): array
    {
        return [CampaignListStats::class];
    }

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('New campaign')];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All')->badge(Broadcast::query()->count()),
            'active' => Tab::make('Active')->badge(Broadcast::query()->whereIn('status', ['preparing', 'sending'])->count())
                ->modifyQueryUsing(fn (Builder $q) => $q->whereIn('status', ['preparing', 'sending'])),
            'draft' => Tab::make('Drafts')->badge(Broadcast::query()->where('status', 'draft')->count())
                ->modifyQueryUsing(fn (Builder $q) => $q->where('status', 'draft')),
            'scheduled' => Tab::make('Scheduled')->badge(Broadcast::query()->where('status', 'scheduled')->count())
                ->modifyQueryUsing(fn (Builder $q) => $q->where('status', 'scheduled')),
            'completed' => Tab::make('Completed')->badge(Broadcast::query()->where('status', 'completed')->count())
                ->modifyQueryUsing(fn (Builder $q) => $q->where('status', 'completed')),
        ];
    }

    protected function getViewData(): array
    {
        $preview = Broadcast::query()->whereIn('status', ['draft', 'scheduled'])
            ->with('translations')->latest()->first();

        return [
            'preview' => $preview,
            'previewText' => $preview?->translations->firstWhere('lang', \App\Enums\BotLanguage::En)?->text,
        ];
    }
}
