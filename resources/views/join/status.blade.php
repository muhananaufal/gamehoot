@php
    $replace = [...$replace, 'event' => $event?->name ?? ''];
    $title = __("join.screens.{$screen}.title");
@endphp
<x-layouts.phone :title="$title" :event="$event">
    <div class="flex grow flex-col items-center justify-center gap-4 text-center" role="status">
        <x-logo :size="80" :wordmark="false" outline="stroke-canvas" />
        <h1 class="font-display text-[28px] leading-tight font-bold">{{ $title }}</h1>
        <p class="max-w-xs text-lg text-muted">{{ __("join.screens.{$screen}.body", $replace) }}</p>
        @if ($event && in_array($screen, ['taken', 'link_invalid'], true))
            <x-button :href="route('join.index', $event)" variant="secondary" class="mt-2">{{ __('join.back_to_names') }}</x-button>
        @endif
        @if ($code)
            <p class="text-sm text-muted">{{ __('join.error_code', ['code' => $code]) }}</p>
        @endif
    </div>
</x-layouts.phone>
