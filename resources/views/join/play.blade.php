<x-layouts.phone :title="$event->name" :event="$event" :person="$person">
    <div class="flex grow flex-col items-center justify-center gap-5 text-center">
        <x-logo :size="96" :wordmark="false" outline="stroke-canvas" />
        <h1 class="font-display text-[32px] leading-tight font-bold">{{ __('join.welcome', ['name' => $person->name]) }}</h1>
        <p class="max-w-xs text-lg text-muted">{{ __('join.waiting') }}</p>
    </div>
</x-layouts.phone>
