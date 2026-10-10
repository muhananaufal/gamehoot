@php
    $replace = [...$replace, 'event' => $event?->name ?? ''];
    $title = __("join.screens.{$screen}.title");
@endphp
<x-layouts.phone :title="$title" :event="$event">
    <div class="flex grow flex-col items-center justify-center gap-4 text-center" role="status">
        {{-- U29: one mood per status screen. --}}
        <x-mascot :size="80" :mood="['not_found' => 'thinking', 'not_open' => 'sleepy', 'ended' => 'wave', 'locked' => 'sad', 'taken' => 'surprised', 'name_gone' => 'sad', 'link_invalid' => 'thinking'][$screen] ?? 'idle'" />
        <h1 class="font-display text-title leading-tight font-bold">{{ $title }}</h1>
        <p class="max-w-xs text-body-l text-muted">{{ __("join.screens.{$screen}.body", $replace) }}</p>
        @if ($event && in_array($screen, ['taken', 'link_invalid', 'name_gone'], true))
            <x-button :href="route('join.index', $event)" variant="secondary" class="mt-2">{{ __('join.back_to_names') }}</x-button>
        @endif
        @if ($code)
            <p class="text-body text-muted">{{ __('join.error_code', ['code' => $code]) }}</p>
        @endif
    </div>
</x-layouts.phone>
