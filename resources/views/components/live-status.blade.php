@props(['event'])
{{-- F20: status screens driven by the live snapshot (event not open, ended, gone). --}}
@foreach (['not_open', 'ended', 'not_found'] as $screen)
    <div x-data x-cloak x-show="$store.realtime.screen() === @js($screen)" role="status"
        {{ $attributes->merge(['class' => 'flex grow flex-col items-center justify-center gap-4 text-center']) }}>
        <x-logo :size="80" :wordmark="false" outline="stroke-canvas" />
        <h1 class="font-display text-[28px] leading-tight font-bold">{{ __("join.screens.{$screen}.title") }}</h1>
        <p class="max-w-md text-lg text-muted">{{ __("join.screens.{$screen}.body", ['event' => $event->name]) }}</p>
    </div>
@endforeach
