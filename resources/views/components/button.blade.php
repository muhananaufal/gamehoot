@props([
    'variant' => 'primary',
    'href' => null,
    'type' => 'button',
])
@php
    // O11: every target is at least 44 px high.
    $classes = 'inline-flex h-11 items-center justify-center gap-2 rounded-control px-4 text-sm font-bold whitespace-nowrap transition-colors disabled:cursor-not-allowed disabled:opacity-60 aria-busy:cursor-progress aria-busy:opacity-100 '
        . match ($variant) {
            // U16: --busy-ink colours the spinner shown while the button sends (app.css).
            'primary' => 'bg-accent text-on-accent hover:bg-accent-strong [--busy-ink:var(--color-on-accent)]',
            'secondary' => 'border border-line-strong bg-surface text-ink hover:bg-subtle [--busy-ink:var(--color-ink)]',
            'destructive' => 'bg-destructive text-on-destructive hover:opacity-90 [--busy-ink:var(--color-on-destructive)]',
            'link' => 'h-auto min-h-11 px-0 text-link underline-offset-4 hover:underline [--busy-ink:var(--color-link)]',
            'link-destructive' => 'h-auto min-h-11 px-0 text-on-danger underline-offset-4 hover:underline [--busy-ink:var(--color-on-danger)]',
        };
@endphp
@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</button>
@endif
