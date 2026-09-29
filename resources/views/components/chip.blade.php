@props([
    'tone' => 'neutral',
    'dot' => false,
])
@php
    $tones = [
        'neutral' => 'bg-subtle text-on-subtle',
        'success' => 'bg-success text-on-success',
        'warning' => 'bg-warning text-on-warning',
        'danger' => 'bg-danger text-on-danger',
    ];
@endphp
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[13px] font-bold ' . $tones[$tone]]) }}>
    @if ($dot)
        <span class="size-2 rounded-full bg-success-dot" aria-hidden="true"></span>
    @endif
    {{ $slot }}
</span>
