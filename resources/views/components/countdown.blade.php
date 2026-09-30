@props([
    // A JavaScript expression for the whole seconds left (F4).
    'seconds',
    'label' => __('pentahoot.seconds'),
])
{{-- F20: the countdown, drawn from the server clock by the enclosing Alpine component. --}}
<div role="timer" aria-live="off" {{ $attributes->merge(['class' => 'flex shrink-0 flex-col items-center justify-center rounded-full bg-rank-1 text-on-rank-1']) }}>
    <span class="font-display leading-none font-bold tabular-nums" x-text="{{ $seconds }}"></span>
    <span class="font-semibold">{{ $label }}</span>
</div>
