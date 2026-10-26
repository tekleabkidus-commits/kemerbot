<div class="studio-topbar-tools" role="navigation" aria-label="Workspace utilities">
    <form class="studio-global-search" method="get" action="{{ \App\Filament\Resources\Users\UserResource::getUrl() }}" role="search">
        <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 5 5"/></svg>
        <label class="sr-only" for="studio-global-search-input">Search people</label>
        <input id="studio-global-search-input" type="search" name="search" placeholder="Search people by name, username or ID…" autocomplete="off">
        <button type="submit" aria-label="Search people">Search</button>
    </form>
    <a class="studio-top-date" href="{{ \App\Filament\Pages\Calendar::getUrl() }}" title="Open campaign calendar">
        <svg width="16" height="16" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" fill="none" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 2v6m10-6v6M3 10h18"/></svg>
        {{ now()->timezone(config('app.display_timezone'))->format('M d, Y') }}
        <span aria-hidden="true">⌄</span>
    </a>
    <a class="studio-notification-link" href="{{ \App\Filament\Pages\DeliveryCenter::getUrl() }}" title="Delivery notifications and issues" aria-label="Delivery center">
        <svg width="19" height="19" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" fill="none" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 8-3 10h18c0-2-3-3-3-10M10 22h4"/></svg>
    </a>
</div>
