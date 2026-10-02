@props([
    'title',
    'body' => null,
])
{{-- U10: an empty page explains the next step, with the mascot and at most one button (slot). --}}
<div {{ $attributes->merge(['class' => 'flex flex-col items-center gap-3 rounded-card bg-surface px-6 py-12 text-center']) }}>
    <x-logo :size="72" :wordmark="false" outline="stroke-canvas" class="mascot-idle" />
    <h2 class="font-display text-xl font-semibold">{{ $title }}</h2>
    @if ($body)
        <p class="max-w-md text-[15px] text-muted">{{ $body }}</p>
    @endif
    @if ($slot->isNotEmpty())
        <div class="pt-2">{{ $slot }}</div>
    @endif
</div>
