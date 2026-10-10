@props(['event'])
{{-- F20: status screens driven by the live snapshot (event not open, ended, gone). --}}
@foreach (['not_open', 'ended', 'not_found'] as $screen)
    <div x-data x-cloak x-show="$store.realtime.screen() === @js($screen)" role="status"
        {{ $attributes->merge(['class' => 'flex grow flex-col items-center justify-center gap-4 text-center']) }}>
        {{-- U29: one mood per status screen. --}}
        <x-mascot :size="80" :mood="['not_open' => 'sleepy', 'ended' => 'wave', 'not_found' => 'thinking'][$screen]" />
        <h1 class="font-display text-title leading-tight font-bold">{{ __("join.screens.{$screen}.title") }}</h1>
        <p class="max-w-md text-body-l text-muted">{{ __("join.screens.{$screen}.body", ['event' => $event->name]) }}</p>
    </div>
@endforeach
