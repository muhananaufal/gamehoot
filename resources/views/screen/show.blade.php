@php
    $withoutScheme = fn (string $url): string => (string) preg_replace('#^https?://#', '', $url);
@endphp
{{-- F23: the projector lobby, sized for 16:9 screens of any resolution. --}}
<x-layouts.screen :title="__('realtime.screen.title') . ' · ' . $event->name" :event="$event">
    <x-realtime :event="$event" :audience="\App\Enums\Audience::Public" />
    <div x-data class="flex min-h-dvh flex-col gap-[4vh] px-[5vw] py-[5vh]">
        <header class="flex items-center justify-between gap-6">
            <x-logo :size="48" outline="stroke-canvas" class="text-[clamp(20px,2.2vw,34px)]" />
            <span class="truncate text-[clamp(18px,1.8vw,28px)] text-muted" x-text="$store.realtime.snapshot?.event.name">{{ $event->name }}</span>
        </header>

        <x-connection-status class="text-[clamp(14px,1.2vw,20px)]" />

        <main x-show="$store.realtime.screen() === 'live'" class="flex grow flex-wrap items-center justify-center gap-[5vw] text-center">
            <div class="flex min-w-0 flex-col items-center gap-[3vh]">
                <p class="text-[clamp(20px,2.4vw,40px)] text-muted">{{ __('realtime.screen.join_at') }}</p>
                <p class="font-display text-[clamp(36px,5vw,96px)] leading-tight font-bold break-all"
                    x-text="$store.realtime.snapshot?.event.link.replace(/^https?:\/\//, '')">{{ $withoutScheme(route('join.index', $event)) }}</p>
            </div>
            {{-- E18: dark modules on a light card in both themes, so phone cameras can read it. --}}
            <div data-test="join-qr" class="relative w-[min(32vh,28vw)] shrink-0 rounded-panel bg-brand-eye p-[1.2vh] text-brand-pupil">
                <div class="[&>svg]:h-auto [&>svg]:w-full" role="img" aria-label="{{ __('realtime.screen.qr') }}">{!! \App\Support\JoinQrCode::svg(route('join.index', $event)) !!}</div>
                <span class="absolute inset-0 m-auto flex size-[22%] items-center justify-center rounded-card bg-brand-eye">
                    <x-logo :size="48" :wordmark="false" outline="stroke-brand-eye" class="size-[80%] [&>svg]:size-full" />
                </span>
            </div>
        </main>

        <x-live-status :event="$event" class="[&_h1]:text-[clamp(28px,4vw,64px)] [&_p]:text-[clamp(18px,2vw,32px)]" />

        <footer x-show="$store.realtime.screen() === 'live'"
            class="flex items-center justify-between gap-6 rounded-panel bg-surface px-[2.5vw] py-[2.5vh] text-[clamp(18px,2vw,32px)]">
            <span>{{ __('realtime.screen.waiting') }}</span>
            <span class="font-display font-semibold tabular-nums" aria-live="polite"
                x-text="$store.realtime.count(@js(__('realtime.screen.joined')), $store.realtime.snapshot?.lobby.joined ?? 0)"></span>
        </footer>
    </div>
</x-layouts.screen>
