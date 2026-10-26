<x-filament-panels::page>
    <div class="studio-profile-view">
        <header class="studio-person-heading">
            <a href="{{ \App\Filament\Resources\Users\UserResource::getUrl() }}" class="studio-person-back">← All people</a>
            <div class="studio-person-heading-content">
                <div>
                    <span class="studio-person-eyebrow">PERSON / CUSTOMER PROFILE</span>
                    <h2>{{ $user->first_name }} <span class="studio-tag green">{{ ucfirst($user->support_status) }}</span></h2>
                    <p>{{ $user->username ? '@'.$user->username : 'Telegram member' }} · Joined {{ $user->joined_at?->timezone(config('app.display_timezone'))->format('d M Y') ?? '—' }}</p>
                </div>
                <a href="{{ \App\Filament\Pages\Inbox::getUrl() }}" class="studio-action-outline">Open inbox</a>
            </div>
        </header>
        <div class="studio-profile-layout">
            <div class="studio-profile-panel">
                @include('filament.partials.studio-profile', ['user'=>$user,'editableTabs'=>false,'recentActivity'=>collect(),'recentCampaigns'=>collect()])
            </div>
            <section class="studio-thread-panel" aria-label="Conversation with {{ $user->first_name }}">
                @include('filament.partials.conversation-thread', ['user'=>$user,'messages'=>$messages,'editableTabs'=>false])
            </section>
        </div>
    </div>
</x-filament-panels::page>
