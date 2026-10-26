<x-filament-panels::page>
    <div class="studio-people">
        <header class="studio-section-heading">
            <div><h2>People</h2><p>Manage your audience and player database.</p></div>
            <a class="studio-cta studio-secondary-cta" href="{{ \App\Filament\Resources\Segments\SegmentResource::getUrl() }}">Saved audiences</a>
        </header>
        <div class="studio-people-grid">
            <section class="studio-people-table">
                <header class="studio-people-table-header"><h3>Audience members</h3><p>Search, filter and open a person to view their full profile.</p></header>
                <div class="studio-people-recent" aria-label="Quick profile preview">
                    @foreach($previewUsers as $person)
                        <button wire:click="selectPreview({{ $person->id }})" type="button" class="{{ $previewUser?->id === $person->id ? 'is-selected' : '' }}">
                            <span class="studio-avatar studio-avatar-sm">{{ mb_strtoupper(mb_substr($person->first_name ?: '?',0,1)) }}</span>
                            <span>{{ \Illuminate\Support\Str::limit($person->first_name ?: 'Member', 13) }}</span>
                        </button>
                    @endforeach
                </div>
                {{ $this->content }}
            </section>
            <div class="studio-people-side">
                @include('filament.partials.studio-profile', ['user'=>$previewUser,'editableTabs'=>false,'recentActivity'=>collect(),'recentCampaigns'=>collect()])
                @if($previewUser)
                    <a class="studio-people-profile-open" href="{{ \App\Filament\Resources\Users\UserResource::getUrl('view',['record'=>$previewUser]) }}">Open full profile →</a>
                @endif
            </div>
        </div>
    </div>
</x-filament-panels::page>
