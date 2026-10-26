<x-filament-panels::page>
    <div class="studio-campaigns">
        <header class="studio-section-heading">
            <div><h2>Campaigns</h2><p>Create and manage campaigns across your Telegram audience.</p></div>
            <a class="studio-cta" href="{{ \App\Filament\Resources\Broadcasts\BroadcastResource::getUrl('create') }}">＋ New Campaign</a>
        </header>
        <div class="studio-campaign-table">{{ $this->content }}</div>
        <div class="studio-campaign-builder">
            <div class="studio-campaign-form-illustration">
                <div class="studio-campaign-builder-title">
                    <div><h3>{{ $preview ? 'Campaign preview' : 'New campaign' }}</h3><p>{{ $preview ? 'Your latest editable draft or scheduled campaign' : 'Start with the five-step builder' }}</p></div>
                    <a href="{{ $preview && $preview->status->value === 'draft' ? \App\Filament\Resources\Broadcasts\BroadcastResource::getUrl('edit', ['record'=>$preview]) : \App\Filament\Resources\Broadcasts\BroadcastResource::getUrl('create') }}" class="studio-action-outline">{{ $preview ? 'Open campaign' : 'Create campaign' }}</a>
                </div>
                <ol class="studio-campaign-steps">
                    @foreach(['Details','Audience','Content','Schedule','Review'] as $index=>$stage)
                        <li><span>{{ $index + 1 }}</span>{{ $stage }}</li>
                    @endforeach
                </ol>
                <div class="studio-campaign-draft-details">
                    <label>Campaign name</label><div>{{ $preview?->name ?: 'Your campaign name' }}</div>
                    <label>Channel</label><span class="studio-tag blue">➤ Telegram</span>
                    <label>Message content</label>
                    <div class="studio-campaign-preview-text">{{ $previewText ? \Illuminate\Support\Str::limit($previewText, 230) : 'Write a new message in the five-step composer to preview its content here.' }}</div>
                </div>
            </div>
            <div class="studio-campaign-phone-preview">
                <span>Live content preview</span>
                <div class="studio-preview-phone">
                    <div class="studio-preview-notch"></div>
                    <div class="studio-preview-brand">◉ KemerBet</div>
                    <div class="studio-preview-bubble">{{ $previewText ? \Illuminate\Support\Str::limit($previewText, 145) : 'Your campaign will appear here when you add a message.' }}</div>
                    <div class="studio-preview-time">Telegram · {{ now()->timezone(config('app.display_timezone'))->format('H:i') }}</div>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
