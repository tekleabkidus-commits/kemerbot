<x-filament-panels::page>
<div class="studio-automations">
    <header class="studio-section-heading">
        <div><h2>Automations</h2><p>Build journeys and create better player experiences.</p></div>
        <a class="studio-cta" href="{{ \App\Filament\Resources\Automations\AutomationResource::getUrl('create') }}">＋ Create journey</a>
    </header>
    <div class="studio-automation-editor">
        <div class="studio-automation-side">
            <h3>Journeys</h3>
            <p>Choose a saved journey to inspect its flow.</p>
            @forelse($journeys as $journey)
                <button wire:click="chooseJourney({{ $journey->id }})" type="button" class="{{ $selected?->id === $journey->id ? 'is-selected' : '' }}">
                    <span class="studio-automation-j-icon">⟳</span>
                    <span><strong>{{ $journey->name }}</strong><small>{{ $journey->is_active ? 'Active' : 'Paused' }}</small></span>
                </button>
            @empty
                <div class="studio-dashboard-empty"><p>No journeys yet. Create a new automation to build one.</p></div>
            @endforelse
        </div>
        <section class="studio-automation-canvas">
            <header>
                <div><h3>{{ $selected?->name ?: 'Your first journey' }}</h3><p>{{ $selected ? 'Saved flow · triggers, delays and messages' : 'Choose an automation to preview its steps' }}</p></div>
                @if($selected)
                    <span class="studio-tag {{ $selected->is_active ? 'green' : 'neutral' }}">{{ $selected->is_active ? 'Active' : 'Paused' }}</span>
                @endif
            </header>
            <div class="studio-flow">
                @if($selected)
                    <div class="studio-flow-node studio-flow-trigger"><span class="studio-flow-icon">♧</span><div><small>Trigger</small><strong>{{ $selected->trigger->value === 'user_joined' ? 'User signs up' : 'Inactive user' }}</strong></div></div>
                    @foreach($selected->steps as $step)
                        <span class="studio-flow-connector" aria-hidden="true"></span>
                        <div class="studio-flow-node studio-flow-wait"><span class="studio-flow-icon">◷</span><div><small>Wait</small><strong>{{ $step->delay_hours }} {{ $step->delay_hours === 1 ? 'hour' : 'hours' }}</strong></div></div>
                        <span class="studio-flow-connector" aria-hidden="true"></span>
                        <div class="studio-flow-node studio-flow-message"><span class="studio-flow-icon">➤</span><div><small>Send message · step {{ $step->step_no + 1 }}</small><strong>{{ \Illuminate\Support\Str::limit($step->translations->firstWhere('lang', \App\Enums\BotLanguage::En)?->text ?: 'Message content', 43) }}</strong></div></div>
                    @endforeach
                    <span class="studio-flow-connector" aria-hidden="true"></span>
                    <div class="studio-flow-end">✓ End</div>
                @else
                    <div class="studio-dashboard-empty"><strong>Begin with a trigger</strong><p>Add your first automated message to see the journey on this canvas.</p></div>
                @endif
            </div>
        </section>
        <aside class="studio-automation-inspector">
            <h3>Step editor</h3><p>Manage steps in the existing safe, validated journey editor.</p>
            @if($selected)
                <div class="studio-automation-inspector-card"><span>Journey</span><strong>{{ $selected->name }}</strong></div>
                <div class="studio-automation-inspector-card"><span>Trigger</span><strong>{{ $selected->trigger->value === 'user_joined' ? 'New member' : 'Inactive member' }}</strong></div>
                <div class="studio-automation-inspector-card"><span>Message steps</span><strong>{{ $selected->steps->count() }}</strong></div>
                <div class="studio-automation-inspector-card"><span>Cooldown</span><strong>{{ $selected->cooldown_days }} days</strong></div>
                <a class="studio-cta" href="{{ \App\Filament\Resources\Automations\AutomationResource::getUrl('edit',['record'=>$selected]) }}">Edit this journey →</a>
                <p class="studio-automation-limits">This canvas reflects the actual sequential trigger, wait and message steps. Conditional branching is not yet supported by KemerBot's automation engine.</p>
            @else
                <a class="studio-cta" href="{{ \App\Filament\Resources\Automations\AutomationResource::getUrl('create') }}">Create automation</a>
            @endif
        </aside>
    </div>
    <section class="studio-automation-table"><div><h3>All automation journeys</h3><p>Search, edit, pause and resume from the table below.</p></div>{{ $this->content }}</section>
</div>
</x-filament-panels::page>
