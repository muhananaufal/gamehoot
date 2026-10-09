@props(['title', 'event'])
{{-- F21: the Public View uses the theme the host picked for the event, and follows changes live.
     U31: the same for the Screen animations setting, read by Penta and the confetti. --}}
<x-layouts.base :title="$title" :theme="$event->screen_theme->value" :remember-motion="false" :screen-animations="$event->screen_animations" class="flex flex-col bg-canvas text-ink"
    x-data x-effect="if ($store.realtime.snapshot) {
        document.documentElement.setAttribute('data-theme', $store.realtime.snapshot.event.screen_theme);
        document.documentElement.dataset.screenAnimations = $store.realtime.snapshot.event.screen_animations === false ? 'off' : 'on';
    }">
    {{ $slot }}
</x-layouts.base>
