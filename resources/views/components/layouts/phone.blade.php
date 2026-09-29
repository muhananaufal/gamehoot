@props([
    'title',
    'event' => null,
    'person' => null,
])
{{-- Participant phone: follows the system theme with the same switch rules as the host (F21). --}}
<x-layouts.base :title="$title" class="flex flex-col bg-canvas">
    <header class="flex items-center justify-between gap-3 rounded-b-[28px] bg-nav px-5 py-5 text-nav-ink">
        @if ($event)
            <span class="truncate font-display text-[22px] font-bold">{{ $event->name }}</span>
        @else
            <x-logo :size="32" class="text-[22px]" />
        @endif
        @if ($person)
            <span class="truncate text-sm text-nav-muted">{{ $person->name }}</span>
        @endif
    </header>
    <main class="mx-auto flex w-full max-w-md grow flex-col px-5 py-6">
        {{ $slot }}
    </main>
</x-layouts.base>
