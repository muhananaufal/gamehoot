@props([
    'variant' => 'primary',
    'href' => null,
    'type' => 'button',
])
@php
    // O11: every target is at least 44 px high.
    $classes = 'inline-flex h-11 items-center justify-center gap-2 rounded-control px-4 text-sm font-bold whitespace-nowrap transition-colors disabled:cursor-not-allowed disabled:opacity-60 aria-busy:cursor-progress '
        . match ($variant) {
            'primary' => 'bg-accent text-on-accent hover:bg-accent-strong',
            'secondary' => 'border border-line-strong bg-surface text-ink hover:bg-subtle',
            'destructive' => 'bg-destructive text-on-destructive hover:opacity-90',
            'link' => 'h-auto min-h-11 px-0 text-link underline-offset-4 hover:underline',
            'link-destructive' => 'h-auto min-h-11 px-0 text-on-danger underline-offset-4 hover:underline',
        };
@endphp
@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</button>
@endif
