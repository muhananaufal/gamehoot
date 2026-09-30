<x-layouts.phone :title="$event->name" :event="$event" :person="$person">
    <x-realtime :event="$event" :audience="\App\Enums\Audience::Public" />
    <x-connection-status class="mb-4" />
    <div x-data x-show="$store.realtime.screen() === 'live'" class="flex grow flex-col items-center justify-center gap-5 text-center">
        <x-logo :size="96" :wordmark="false" outline="stroke-canvas" />
        <h1 class="font-display text-[32px] leading-tight font-bold">{{ __('join.welcome', ['name' => $person->name]) }}</h1>
        <p class="max-w-xs text-lg text-muted">{{ __('join.waiting') }}</p>
    </div>
    <x-live-status :event="$event" />
</x-layouts.phone>
