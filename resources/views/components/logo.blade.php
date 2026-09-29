@props([
    // Pixel size of the mark. Below 48 px the eyes grow so they stay visible (E19, "Brand" artboard).
    'size' => 32,
    'wordmark' => true,
    // Stroke between the segments: the color of the surface behind the logo.
    'outline' => 'stroke-nav',
])
@php
    $small = $size < 48;
    $eye = $small ? 23 : 21;
    $pupil = $small ? 11 : 9;
@endphp
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-2.5']) }}>
    <svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 200 200" role="img" aria-label="{{ config('app.name') }}"
        class="shrink-0">
        <g class="{{ $outline }}" stroke-width="7" stroke-linejoin="round">
            <path d="M100 100 L100 10 L185.6 72.2 Z" class="fill-brand-yellow"></path>
            <path d="M100 100 L185.6 72.2 L152.9 172.8 Z" class="fill-brand-coral"></path>
            <path d="M100 100 L152.9 172.8 L47.1 172.8 Z" class="fill-brand-mint"></path>
            <path d="M100 100 L47.1 172.8 L14.4 72.2 Z" class="fill-brand-blue"></path>
            <path d="M100 100 L14.4 72.2 L100 10 Z" class="fill-brand-pink"></path>
        </g>
        <circle cx="74" cy="98" r="{{ $eye }}" class="fill-brand-eye"></circle>
        <circle cx="126" cy="98" r="{{ $eye }}" class="fill-brand-eye"></circle>
        <circle cx="78" cy="101" r="{{ $pupil }}" class="fill-brand-pupil"></circle>
        <circle cx="130" cy="101" r="{{ $pupil }}" class="fill-brand-pupil"></circle>
    </svg>
    @if ($wordmark)
        <span class="font-display font-bold tracking-tight" aria-hidden="true">pentahoot</span>
    @endif
</span>
