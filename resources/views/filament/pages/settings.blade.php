<x-filament-panels::page>
    <form wire:submit="save" class="space-y-6">
        {{ $this->form }}

        @unless (auth()->user()->isViewer())
            <x-filament::button type="submit">
                Save settings
            </x-filament::button>
        @endunless
    </form>
</x-filament-panels::page>
