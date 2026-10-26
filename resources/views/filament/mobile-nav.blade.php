@if(\Filament\Facades\Filament::auth()->check())
<nav class="ks-mobile-nav" aria-label="Mobile workspace navigation">
    <a href="{{ \App\Filament\Pages\Home::getUrl() }}" class="{{ request()->is('admin') || request()->is('admin/home') ? 'is-current' : '' }}" @if(request()->is('admin') || request()->is('admin/home')) aria-current="page" @endif>
        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none"><path d="m3 11 9-8 9 8v9a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1v-9Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>
        <span>Home</span>
    </a>
    <a href="{{ \App\Filament\Pages\Inbox::getUrl() }}" class="{{ request()->is('admin/inbox*') ? 'is-current' : '' }}" @if(request()->is('admin/inbox*')) aria-current="page" @endif>
        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none"><path d="M4 4h16v12H8l-4 4V4Zm3 4h10M7 12h7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
        <span>Inbox</span>
    </a>
    <a href="{{ \App\Filament\Resources\Users\UserResource::getUrl() }}" class="{{ request()->is('admin/users*') ? 'is-current' : '' }}" @if(request()->is('admin/users*')) aria-current="page" @endif>
        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none"><circle cx="9" cy="8" r="3" stroke="currentColor" stroke-width="1.8"/><path d="M3 20v-2c0-3 3-5 6-5s6 2 6 5v2H3Zm13-15c2 0 4 2 4 4s-2 4-4 4m1 2c3 0 5 2 5 5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
        <span>People</span>
    </a>
    <a href="{{ \App\Filament\Pages\Calendar::getUrl() }}" class="{{ request()->is('admin/calendar*') ? 'is-current' : '' }}" @if(request()->is('admin/calendar*')) aria-current="page" @endif>
        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none"><rect x="3" y="5" width="18" height="16" rx="2" stroke="currentColor" stroke-width="1.8"/><path d="M7 3v4m10-4v4M3 10h18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
        <span>Calendar</span>
    </a>
    <details class="studio-mobile-more">
        <summary aria-label="More workspace sections">
            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
            <span>More</span>
        </summary>
        <div class="studio-mobile-more-menu">
            <h3>More workspace tools</h3>
            <a href="{{ \App\Filament\Resources\Broadcasts\BroadcastResource::getUrl() }}">Campaigns</a>
            <a href="{{ \App\Filament\Resources\Automations\AutomationResource::getUrl() }}">Automations</a>
            <a href="{{ \App\Filament\Pages\Reports::getUrl() }}">Reports</a>
            <a href="{{ \App\Filament\Resources\Polls\PollResource::getUrl() }}">Polls</a>
            <a href="{{ \App\Filament\Resources\TrackingLinks\TrackingLinkResource::getUrl() }}">Tracking Links</a>
            <a href="{{ \App\Filament\Resources\Segments\SegmentResource::getUrl() }}">Saved Audiences</a>
            <a href="{{ \App\Filament\Resources\MenuItems\MenuItemResource::getUrl() }}">Menus</a>
            <a href="{{ \App\Filament\Resources\KeywordReplies\KeywordReplyResource::getUrl() }}">Auto Replies</a>
            <a href="{{ \App\Filament\Pages\DeliveryCenter::getUrl() }}">Delivery Center</a>
        </div>
    </details>
</nav>
@endif
