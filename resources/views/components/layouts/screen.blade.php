@props(['title', 'event'])
{{-- F21: the Public View uses the theme the host picked for the event, and follows changes live. --}}
<x-layouts.base :title="$title" :theme="$event->screen_theme->value" class="flex flex-col bg-canvas text-ink"
    x-data x-effect="$store.realtime.snapshot && document.documentElement.setAttribute('data-theme', $store.realtime.snapshot.event.screen_theme)">
    {{ $slot }}
</x-layouts.base>
