{{-- Telegram-style preview of the live main menu (spec §5.2). --}}
<div class="space-y-6">
    @foreach ($keyboards as $lang => $keyboard)
        <div>
            <div class="mb-2 text-sm font-semibold text-gray-500 dark:text-gray-400">
                {{ strtoupper($lang) }}
            </div>
            <div class="mx-auto max-w-sm rounded-xl bg-gray-100 p-4 dark:bg-gray-800">
                <div class="mb-3 rounded-lg bg-white p-3 text-sm shadow dark:bg-gray-700 dark:text-gray-100">
                    {{ $welcome[$lang] ?? 'Choose an option:' }}
                </div>
                @if ($keyboard === null)
                    <div class="text-center text-xs text-gray-400">No active menu items</div>
                @else
                    <div class="space-y-1.5">
                        @foreach ($keyboard['inline_keyboard'] as $row)
                            <div class="flex gap-1.5">
                                @foreach ($row as $button)
                                    <div class="flex-1 truncate rounded-md bg-sky-500/90 px-3 py-2 text-center text-xs font-medium text-white">
                                        {{ $button['text'] }}
                                        @if (isset($button['url'])) 🔗 @endif
                                        @if (isset($button['web_app'])) 📱 @endif
                                    </div>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    @endforeach
</div>
