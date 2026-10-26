<x-filament-panels::page>
    <div class="ks-page">
        <header class="ks-page-head">
            <div>
                <span class="ks-eyebrow">WORKSPACE CONFIGURATION</span>
                <h2 class="ks-heading">Settings &amp; preferences.</h2>
                <p class="ks-subtitle">Manage bot content, messaging limits and operational preferences. Security credentials remain in Laravel Cloud environment variables.</p>
            </div>
            <div class="ks-page-actions"><a class="ks-action" href="{{ \App\Filament\Pages\DeliveryCenter::getUrl() }}">Delivery center →</a></div>
        </header>
        <div class="ks-insight"><strong>Safe changes, clear controls</strong>Messaging preferences and consent must be respected. Only the Owner can change infrastructure controls; users with view-only access can review these settings without editing.</div>
        <form wire:submit="save" class="ks-form-shell">
            {{ $this->form }}
            @unless(auth()->user()->isViewer())
                <div class="ks-form-actions">
                    <p>Changes are saved to the active workspace when you select Save.</p>
                    <x-filament::button type="submit" wire:loading.attr="disabled" wire:target="save" icon="heroicon-o-check">Save settings</x-filament::button>
                </div>
            @else
                <div class="ks-insight"><strong>Read-only access</strong>Your role can view settings but cannot change them.</div>
            @endunless
        </form>
    </div>
</x-filament-panels::page>
