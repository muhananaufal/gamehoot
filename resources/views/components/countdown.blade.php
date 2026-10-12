@props([
    // A JavaScript expression for the whole seconds left (F4).
    'seconds',
    'label' => __('pentahoot.seconds'),
    // U30: optional expressions for the ring (stroke offset of a 100-long circle) and the
    // urgency ('calm', 'soon', 'last'), from pentahootScreen.
    'ring' => null,
    'urgency' => null,
    // U34 (audit UI-1, CODE-5): the size of the digits and of the label, named here instead of
    // reached from outside with a child selector (the ring once became the first child, and the
    // digits silently stayed as small as the label).
    'digits' => 'text-[2.4em]',
    'unit' => '',
])
{{-- F20, U30: the countdown, drawn from the server clock by the enclosing Alpine component. A ring
     empties as time runs out; the last five seconds beat (x-nudge.pulse) and the last three turn
     red-orange. The ring moves its stroke offset, a paint on one small element, with the 250 ms
     tick of the screen (TICK_MS). --}}
<div role="timer" aria-live="off" @if ($urgency) :class="{{ $urgency }} === 'last' ? 'bg-brand-coral text-brand-pupil' : 'bg-rank-1 text-on-rank-1'" @endif
    {{ $attributes->merge(['class' => 'relative flex shrink-0 flex-col items-center justify-center rounded-full'.($urgency ? '' : ' bg-rank-1 text-on-rank-1')]) }}>
    @if ($ring)
        <svg class="absolute inset-0 size-full -rotate-90" viewBox="0 0 100 100" aria-hidden="true">
            <circle cx="50" cy="50" r="46" pathLength="100" fill="none" stroke="currentColor" stroke-opacity="0.25" stroke-width="5"></circle>
            <circle cx="50" cy="50" r="46" pathLength="100" fill="none" stroke="currentColor" stroke-width="5" stroke-linecap="round"
                stroke-dasharray="100" :stroke-dashoffset="{{ $ring }}" class="countdown-ring"></circle>
        </svg>
    @endif
    <span data-test="countdown-digits" class="relative font-display leading-none font-bold tabular-nums {{ $digits }}" x-text="{{ $seconds }}"
        @if ($urgency) x-nudge.pulse="{{ $urgency }} === 'calm' ? null : {{ $seconds }}" @endif></span>
    <span class="relative font-semibold {{ $unit }}">{{ $label }}</span>
</div>
