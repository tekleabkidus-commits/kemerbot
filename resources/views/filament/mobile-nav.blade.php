@if(\Filament\Facades\Filament::auth()->check())
    <nav class="ks-mobile-nav" aria-label="Mobile workspace navigation">
        <a href="{{ \App\Filament\Pages\Home::getUrl() }}" class="{{ request()->is('admin') || request()->is('admin/home') ? 'is-current' : '' }}" aria-label="Dashboard" @if(request()->is('admin') || request()->is('admin/home')) aria-current="page" @endif>
            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none"><path d="m3 11 9-8 9 8v9a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1v-9Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>
            <span>Home</span>
        </a>
        <a href="{{ \App\Filament\Resources\Broadcasts\BroadcastResource::getUrl() }}" class="{{ request()->is('admin/broadcasts*') ? 'is-current' : '' }}" aria-label="Campaigns" @if(request()->is('admin/broadcasts*')) aria-current="page" @endif>
            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none"><path d="m4 10 13-6v16L4 14H2v-4h2Zm2 5 2 5h3l-3-5" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>
            <span>Campaigns</span>
        </a>
        <a href="{{ \App\Filament\Pages\Inbox::getUrl() }}" class="{{ request()->is('admin/inbox*') ? 'is-current' : '' }}" aria-label="Inbox" @if(request()->is('admin/inbox*')) aria-current="page" @endif>
            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none"><path d="M4 4h16v12H8l-4 4V4Zm3 4h10M7 12h7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
            <span>Inbox</span>
        </a>
        <a href="{{ \App\Filament\Resources\Users\UserResource::getUrl() }}" class="{{ request()->is('admin/users*') ? 'is-current' : '' }}" aria-label="People" @if(request()->is('admin/users*')) aria-current="page" @endif>
            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none"><circle cx="9" cy="8" r="3" stroke="currentColor" stroke-width="1.8"/><path d="M3 20v-2c0-3 3-5 6-5s6 2 6 5v2H3Zm13-15c2 0 4 2 4 4s-2 4-4 4m1 2c3 0 5 2 5 5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
            <span>People</span>
        </a>
        <a href="{{ \App\Filament\Pages\Calendar::getUrl() }}" class="{{ request()->is('admin/calendar*') ? 'is-current' : '' }}" aria-label="Calendar" @if(request()->is('admin/calendar*')) aria-current="page" @endif>
            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none"><rect x="3" y="5" width="18" height="16" rx="2" stroke="currentColor" stroke-width="1.8"/><path d="M7 3v4m10-4v4M3 10h18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
            <span>Calendar</span>
        </a>
    </nav>
@endif
