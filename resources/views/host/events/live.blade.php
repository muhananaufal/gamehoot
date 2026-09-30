@php
    $statusLabels = __('host.event_status');
    $connectionLabels = [...__('realtime.connection'), 'connected' => __('realtime.live.connected')];
@endphp
{{-- F20: Live control. Runs the active game (D-5, E15, F2) and shows the event's live state. --}}
<x-layouts.host :title="__('realtime.live.title') . ' · ' . $event->name" :event="$event"
    :breadcrumbs="[[__('host.dashboard.title'), route('host.dashboard')], [$event->name, null], [__('realtime.live.title'), null]]">
    <x-slot:actions>
        <x-button :href="route('join.screen', $event)" variant="secondary" target="_blank" rel="noopener">{{ __('realtime.live.open_screen') }}</x-button>
    </x-slot:actions>

    <x-realtime :event="$event" :audience="\App\Enums\Audience::Host" />

    {{-- The question list is drawn once per game: reload when another game starts or ends. --}}
    <div x-data
        x-effect="($store.realtime.snapshot?.game?.id ?? null) !== @js($game?->id) && $store.realtime.snapshot && window.location.reload()"
        class="flex flex-col gap-5">
        <x-connection-status />

        @error('action')
            <div role="alert" class="rounded-card bg-danger px-4 py-3 text-sm font-semibold text-on-danger">{{ $message }}</div>
        @enderror

        <div class="grid gap-5 md:grid-cols-3">
            <section class="flex flex-col gap-2 rounded-card bg-surface p-6">
                <h2 class="text-sm font-bold text-muted">{{ __('realtime.live.status') }}</h2>
                <p class="font-display text-2xl font-semibold" x-text="@js($statusLabels)[$store.realtime.snapshot?.event.status] ?? ''"></p>
            </section>
            <section class="flex flex-col gap-2 rounded-card bg-surface p-6">
                <h2 class="text-sm font-bold text-muted">{{ __('realtime.live.joined') }}</h2>
                <p class="flex items-baseline gap-2">
                    <span data-test="joined-count" class="font-display text-4xl font-semibold tabular-nums" x-text="$store.realtime.snapshot?.lobby.joined ?? 0"></span>
                    <span class="text-muted" x-text="$store.realtime.count(@js(__('realtime.live.names_count')), $store.realtime.snapshot?.host?.names ?? 0)"></span>
                </p>
            </section>
            <section class="flex flex-col gap-2 rounded-card bg-surface p-6">
                <h2 class="text-sm font-bold text-muted">{{ __('realtime.live.connection') }}</h2>
                <p class="flex items-center gap-2 font-display text-2xl font-semibold">
                    <span class="size-3 rounded-full" aria-hidden="true"
                        :class="$store.realtime.connection === 'connected' ? 'bg-success-dot' : 'bg-line-strong'"></span>
                    <span x-text="@js($connectionLabels)[$store.realtime.connection]"></span>
                </p>
            </section>
        </div>

        @if ($game)
            @include('host.events.live.' . $game->type->value, ['event' => $event, 'game' => $game, 'questions' => $questions, 'people' => $people])
        @else
            <section class="flex flex-col items-start gap-3 rounded-card bg-surface p-6">
                <p class="font-semibold">{{ __('pentahoot.no_game') }}</p>
                <p class="text-sm text-muted">{{ __('pentahoot.no_game_help') }}</p>
                <x-button :href="route('host.events.games.index', $event)" variant="secondary">{{ __('pentahoot.open_games') }}</x-button>
            </section>
        @endif

        <section class="flex flex-col gap-4 rounded-card bg-surface p-6">
            <dl class="grid gap-4 md:grid-cols-2">
                <div class="flex min-w-0 flex-col gap-1">
                    <dt class="text-sm font-bold text-muted">{{ __('realtime.live.join_link') }}</dt>
                    <dd class="truncate"><a href="{{ route('join.index', $event) }}" class="text-accent underline-offset-4 hover:underline" target="_blank" rel="noopener">{{ route('join.index', $event) }}</a></dd>
                </div>
                <div class="flex min-w-0 flex-col gap-1">
                    <dt class="text-sm font-bold text-muted">{{ __('realtime.live.screen_link') }}</dt>
                    <dd class="truncate"><a href="{{ route('join.screen', $event) }}" class="text-accent underline-offset-4 hover:underline" target="_blank" rel="noopener">{{ route('join.screen', $event) }}</a></dd>
                </div>
            </dl>
        </section>
    </div>
</x-layouts.host>
