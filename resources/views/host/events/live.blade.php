@php
    use App\Enums\EventPhase;

    $phaseTone = match ($phase) {
        // D-10: running green, finished yellow, everything else grey, as everywhere.
        EventPhase::Playing => 'success',
        EventPhase::Finished => 'warning',
        default => 'neutral',
    };
    $played = $games->where('status', \App\Enums\GameStatus::Finished)->count();
@endphp
{{-- F20, D-10: Live control in three columns: the steps from preparing to closing, the control for
     the step at hand, and a monitor of what the projector shows. --}}
<x-layouts.host :title="__('realtime.live.title') . ' · ' . $event->name" :event="$event"
    :breadcrumbs="[[__('host.dashboard.title'), route('host.dashboard')], [$event->name, null], [__('realtime.live.title'), null]]">
    <x-slot:actions>
        <x-chip :tone="$phaseTone" :dot="$phase === EventPhase::Playing" data-phase="{{ $phase->value }}">{{ __('live.phase.' . $phase->value) }}</x-chip>
        <x-button :href="route('join.screen', $event)" variant="secondary" target="_blank" rel="noopener">{{ __('realtime.live.open_screen') }}</x-button>
    </x-slot:actions>

    <x-realtime :event="$event" :audience="\App\Enums\Audience::Host" />

    {{-- The page is drawn per event status and game on screen: when either changes (here, by a
         co-host or on another tab), Unpoly reloads the content (U18) without reloading the page. --}}
    <div x-data="{ drawn: @js($event->status->value . '|' . ($game?->id ?? '') . '|' . ($game?->status->value ?? '')) }"
        x-effect="const s = $store.realtime.snapshot; s && (s.event.status + '|' + (s.game?.id ?? '') + '|' + (s.game?.status ?? '')) !== drawn && window.up.reload()"
        class="flex flex-col gap-5">
        <x-connection-status />

        @error('action')
            <div role="alert" class="rounded-card bg-danger px-4 py-3 text-sm font-semibold text-on-danger">{{ $message }}</div>
        @enderror

    {{-- While a game runs, its Alpine component (F13) wraps the columns: its questions sit under
         the steps, its control in the middle, its running count in the monitor. --}}
    <div @if ($running) x-data="@include('host.events.live.' . $running->type->value, ['part' => 'data', 'event' => $event, 'game' => $running, 'questions' => $questions, 'people' => $people])" @endif
        class="grid gap-5 lg:grid-cols-[240px_minmax(0,1fr)] xl:grid-cols-[240px_minmax(0,1fr)_320px]">

        {{-- Left: the steps. On phones they follow the control. --}}
        <section aria-label="{{ __('live.steps_title') }}" class="order-2 flex flex-col gap-4 self-start rounded-card bg-surface p-5 lg:order-1">
            <h2 class="text-sm font-bold text-muted">{{ __('live.steps_title') }}</h2>
            <x-timeline :steps="$steps" />
            @if ($running)
                <div class="border-t border-line-soft pt-4 [&>section]:bg-transparent [&>section]:p-0">
                    @include('host.events.live.' . $running->type->value, ['part' => 'questions', 'event' => $event, 'game' => $running, 'questions' => $questions, 'people' => $people])
                </div>
            @endif
        </section>

        {{-- Middle: only what the step at hand needs. --}}
        <section aria-label="{{ __('live.control_title') }}" class="order-1 flex min-w-0 flex-col gap-5 lg:order-2">
            @switch($phase)
                @case(EventPhase::Draft)
                    <div class="flex flex-col gap-4 rounded-card bg-surface p-6">
                        <h2 class="font-display text-xl font-semibold">{{ __('live.get_ready') }}</h2>
                        <ul class="flex flex-col gap-2">
                            <li class="flex items-center justify-between gap-3">
                                <span>{{ trans_choice('live.names', $names, ['count' => $names]) }}</span>
                                <a href="{{ route('host.events.people.index', $event) }}" class="text-sm font-semibold text-link underline-offset-4 hover:underline">{{ __('live.open_names') }}</a>
                            </li>
                            <li class="flex items-center justify-between gap-3">
                                <span>{{ ucfirst(trans_choice('live.games', $games->count(), ['count' => $games->count()])) }}</span>
                                <a href="{{ route('host.events.games.index', $event) }}" class="text-sm font-semibold text-link underline-offset-4 hover:underline">{{ __('live.open_games') }}</a>
                            </li>
                        </ul>
                        <p class="text-sm text-muted">{{ __('live.get_ready_help') }}</p>
                        <form method="POST" action="{{ route('host.events.open', $event) }}" class="flex justify-end">
                            @csrf
                            <x-button type="submit">{{ __('events.open_event') }}</x-button>
                        </form>
                    </div>
                    @break

                @case(EventPhase::Open)
                    <div class="flex flex-col gap-4 rounded-card bg-surface p-6">
                        @if ($next)
                            <div class="flex flex-col gap-1">
                                <h2 class="text-sm font-bold text-muted">{{ __('live.next_game') }}</h2>
                                <p class="font-display text-2xl font-semibold">{{ $next->position }}. {{ $next->title }}</p>
                                <p class="text-sm text-muted">{{ __('games.types.' . $next->type->value) }} · {{ trans_choice('games.questions', $next->questions_count ?? 0) }}</p>
                            </div>
                            <div class="flex flex-wrap items-center justify-between gap-2.5">
                                @include('host.events.partials.close-event', ['event' => $event, 'runningGame' => null])
                                @include('host.events.partials.start-game', ['event' => $event, 'game' => $next])
                            </div>
                            {{-- D-10: a later game, started from its step, out of order. --}}
                            @foreach ($games->filter(fn ($game) => $game->status !== \App\Enums\GameStatus::Finished && $game->id !== $event->active_game_id && ! $game->is($next)) as $later)
                                <x-confirm-dialog :title="__('live.start_out_of_order', ['title' => $later->title])" :trigger="__('games.start')" hide-trigger dialog-id="start-{{ $later->id }}">
                                    <p class="text-[15px] text-muted">{{ __('live.start_out_of_order_help', ['title' => $later->title, 'next' => $next->title]) }}</p>
                                    @if ($event->names_locked_at === null)
                                        <p class="text-[15px] text-muted">{{ __('games.start_locks_names') }}</p>
                                    @endif
                                    <form method="POST" action="{{ route('host.events.games.start', [$event, $later]) }}" class="flex justify-end gap-2.5 pt-1.5">
                                        @csrf
                                        <x-button variant="secondary" type="button" @click="$el.closest('dialog').close()">{{ __('ui.cancel') }}</x-button>
                                        <x-button type="submit" :disabled="($later->questions_count ?? 0) === 0">{{ __('games.start') }}</x-button>
                                    </form>
                                </x-confirm-dialog>
                            @endforeach
                        @else
                            <div class="flex flex-col gap-1">
                                <h2 class="font-display text-xl font-semibold">{{ __('live.no_games') }}</h2>
                                <p class="text-sm text-muted">{{ __('live.no_games_help') }}</p>
                            </div>
                            <div class="flex flex-wrap items-center justify-between gap-2.5">
                                @include('host.events.partials.close-event', ['event' => $event, 'runningGame' => null])
                                <x-button :href="route('host.events.games.index', $event)">{{ __('live.add_game') }}</x-button>
                            </div>
                        @endif
                    </div>
                    @break

                @case(EventPhase::Playing)
                    @include('host.events.live.' . $running->type->value, ['part' => 'control', 'event' => $event, 'game' => $running, 'questions' => $questions, 'people' => $people])
                    @break

                @case(EventPhase::AllPlayed)
                    <div class="flex flex-col gap-4 rounded-card bg-surface p-6">
                        <div class="flex flex-col gap-1">
                            <h2 class="font-display text-xl font-semibold">{{ __('live.all_played') }}</h2>
                            <p class="text-sm text-muted">{{ __('live.all_played_help', ['done' => $played, 'total' => $games->count()]) }}</p>
                        </div>
                        <div class="flex flex-wrap items-center justify-between gap-2.5">
                            <x-button :href="route('host.events.games.index', $event)" variant="secondary">{{ __('live.add_game') }}</x-button>
                            @include('host.events.partials.close-event', ['event' => $event, 'runningGame' => null])
                        </div>
                    </div>
                    @break

                @case(EventPhase::Finished)
                    <div class="flex flex-col gap-4 rounded-card bg-surface p-6">
                        <div class="flex flex-col gap-1">
                            <h2 class="font-display text-xl font-semibold">{{ __('live.event_closed') }}</h2>
                            <p class="text-sm text-muted">{{ __('live.event_closed_help') }}</p>
                        </div>
                        <div class="flex flex-wrap items-center justify-between gap-2.5">
                            <x-button :href="route('host.events.results', $event)" variant="secondary">{{ __('live.results') }}</x-button>
                            @can('reopen', $event)
                                <form method="POST" action="{{ route('host.events.reopen', $event) }}">
                                    @csrf
                                    <x-button type="submit">{{ __('events.reopen') }}</x-button>
                                </form>
                            @endcan
                        </div>
                    </div>
                    @break
            @endswitch
        </section>

        {{-- Right: what the projector shows, who joined, and the links. Below the rest on narrower screens. --}}
        <section aria-label="{{ __('live.monitor_title') }}" class="order-3 flex flex-col gap-4 self-start lg:col-span-2 xl:col-span-1">
            <div class="flex flex-col gap-2 rounded-card bg-surface p-5">
                <h2 class="text-sm font-bold text-muted">{{ __('live.preview') }}</h2>
                {{-- D-10: the real Public View, scaled down from 1280 × 720 to the column. --}}
                <div x-data="{ scale: 0.25 }" x-init="new ResizeObserver(([entry]) => scale = entry.contentRect.width / 1280).observe($el)"
                    class="relative aspect-video overflow-hidden rounded-control border border-line-soft bg-canvas">
                    <iframe src="{{ route('join.screen', $event) }}" title="{{ __('live.preview') }}" tabindex="-1"
                        class="pointer-events-none absolute top-0 left-0 h-[720px] w-[1280px] origin-top-left border-0"
                        :style="`transform: scale(${scale})`"></iframe>
                </div>
                <p class="text-xs text-muted">{{ __('live.preview_note') }}</p>
            </div>
            @if ($running)
                @include('host.events.live.' . $running->type->value, ['part' => 'board', 'event' => $event, 'game' => $running, 'questions' => $questions, 'people' => $people])
            @endif
            <div class="flex flex-col gap-3 rounded-card bg-surface p-5">
                <div class="flex items-baseline justify-between gap-3">
                    <h2 class="text-sm font-bold text-muted">{{ __('realtime.live.joined') }}</h2>
                    <p class="flex items-baseline gap-2">
                        <span data-test="joined-count" class="font-display text-3xl font-semibold tabular-nums" x-data x-text="$store.realtime.snapshot?.lobby.joined ?? 0"></span>
                        <span class="text-sm text-muted" x-data x-text="$store.realtime.count(@js(__('realtime.live.names_count')), $store.realtime.snapshot?.host?.names ?? 0)"></span>
                    </p>
                </div>
                <dl class="flex flex-col gap-3 border-t border-line-soft pt-3 text-sm">
                    @foreach ([[__('realtime.live.join_link'), route('join.index', $event)], [__('realtime.live.screen_link'), route('join.screen', $event)]] as [$label, $url])
                        <div class="flex min-w-0 flex-col gap-1" x-data="{ copied: false }">
                            <dt class="font-bold text-muted">{{ $label }}</dt>
                            <dd class="flex items-center gap-2">
                                <a href="{{ $url }}" class="min-w-0 truncate text-link underline-offset-4 hover:underline" target="_blank" rel="noopener">{{ $url }}</a>
                                <button type="button" class="row-action shrink-0" :aria-label="copied ? @js(__('live.copied')) : @js(__('live.copy') . ' ' . $label)" :title="copied ? @js(__('live.copied')) : @js(__('live.copy'))"
                                    @click="navigator.clipboard?.writeText(@js($url)).then(() => { copied = true; setTimeout(() => copied = false, 1500) })"><i data-icon="copy"></i></button>
                            </dd>
                        </div>
                    @endforeach
                </dl>
            </div>
        </section>
    </div>
    </div>
</x-layouts.host>
