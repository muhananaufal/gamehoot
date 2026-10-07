@php
    use App\Actions\Games\ReorderGames;
    use App\Enums\GameStatus;

    // D-1, G7: a game still running blocks starting another; a finished one on screen does not.
    $gameRunning = $games->contains(fn ($game) => $game->id === $event->active_game_id && $game->status !== GameStatus::Finished);
    // D-9: played games keep their places; the others can take a new order.
    [$upNext, $played] = $games->partition(fn ($game) => ReorderGames::stillToPlay($event, $game));
@endphp
{{-- D-9, T8: the games of an event, in play order. --}}
<x-layouts.host :title="__('games.title') . ' · ' . $event->name" :event="$event"
    :breadcrumbs="[[__('host.dashboard.title'), route('host.dashboard')], [$event->name, null], [__('games.title'), null]]">

    @error('action')
        <div role="alert" class="rounded-card bg-danger px-4 py-3 text-sm font-semibold text-on-danger">{{ $message }}</div>
    @enderror

    <x-realtime :event="$event" :audience="\App\Enums\Audience::Host" />
    {{-- U23: the pack list of Question packs on the left, where a click adds a pack; the games on the right. --}}
    <div class="grid grid-cols-[minmax(0,1fr)] gap-5 lg:grid-cols-[260px_minmax(0,1fr)]">
        @if ($packs->isEmpty())
            <section class="flex flex-col gap-3 self-start rounded-card bg-surface p-4" aria-label="{{ __('games.add_from_pack') }}">
                <p class="text-sm text-muted">{{ __('games.no_packs') }}</p>
                <x-button :href="route('host.packs.index')" variant="secondary">{{ __('host.nav.question_packs') }}</x-button>
            </section>
        @else
            {{-- A grid, not a flex column: the list keeps its self-start without shrinking to its content. --}}
            <div class="grid gap-2 self-start">
                <x-pack-list :packs="$packs" :types="$types" :add-to="route('host.events.games.store', $event)" />
                @error('pack_ids.*')
                    <p class="px-1 text-sm font-semibold text-on-danger">{{ $message }}</p>
                @enderror
            </div>
        @endif

        {{-- U12: game status follows starts and finishes made from Live control or by a co-host. --}}
        <section id="games-live" x-data="liveRegion" class="flex min-w-0 flex-col gap-5 self-start rounded-card bg-surface p-6" aria-label="{{ __('games.title') }}">
            <p class="text-sm text-muted">{{ $packs->isEmpty() ? __('games.subtitle') : __('games.add_hint') }}</p>

            @if ($games->isEmpty())
                <x-empty-state :title="__('games.empty_title')" :body="__('games.empty')" class="bg-subtle py-8" />
            @endif

            @if ($played->isNotEmpty())
                {{-- D-9: played games stay where they are. --}}
                <div class="flex flex-col gap-2">
                    <h2 class="text-xs font-bold tracking-[0.06em] text-muted uppercase">{{ __('games.played') }}</h2>
                    <ol class="flex flex-col gap-2">
                        @foreach ($played as $game)
                            @php $running = $event->active_game_id === $game->id && $game->status !== GameStatus::Finished; @endphp
                            <li class="flex items-center gap-3 rounded-control border border-line-soft px-3 py-2">
                                <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-subtle text-sm font-bold">{{ $game->position }}</span>
                                @include('host.games.summary', ['game' => $game])
                                <x-chip :tone="$running ? 'success' : 'warning'" :dot="$running">{{ __('games.status.' . ($running ? 'running' : 'finished')) }}</x-chip>
                                @if ($running)
                                    <x-button :href="route('host.events.live', $event)" variant="secondary">{{ __('realtime.live.title') }}</x-button>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                </div>
            @endif

            @if ($upNext->isNotEmpty())
                {{-- D-9, U9, U14: the games still to play: drag by the handle, or move with the arrows;
                     the order is saved once after the last click. --}}
                <div class="flex flex-col gap-2" x-data="sortableList" data-reload="#games-live" data-save-failed="{{ __('games.order_failed') }}">
                    <h2 class="text-xs font-bold tracking-[0.06em] text-muted uppercase">{{ __('games.up_next') }}</h2>
                    <form method="POST" action="{{ route('host.events.games.reorder', $event) }}" up-target="#games-live" x-ref="orderForm" class="hidden">
                        @csrf
                    </form>
                    <ol class="flex flex-col gap-2" x-flip.moves data-places="{{ json_encode($upNext->pluck('position')->values()) }}">
                        @foreach ($upNext as $game)
                            <li data-id="{{ $game->id }}" draggable="true"
                                @dragstart="start($event, {{ $loop->index }})" @dragenter.prevent="enter({{ $loop->index }})" @dragover.prevent
                                @drop.prevent="drop({{ $loop->index }})" @dragend="end()"
                                :class="{ 'opacity-50': from === {{ $loop->index }}, 'border-accent bg-subtle': over === {{ $loop->index }} && from !== {{ $loop->index }} }"
                                class="flex flex-wrap items-center gap-2 rounded-control border border-line-soft px-2 py-2 transition-colors">
                                <span class="flex h-11 w-6 shrink-0 cursor-grab items-center justify-center text-muted active:cursor-grabbing" title="{{ __('games.drag') }}" aria-hidden="true">
                                    <x-icon name="grip" />
                                </span>
                                <span data-number class="flex size-8 shrink-0 items-center justify-center rounded-full bg-subtle text-sm font-bold">{{ $game->position }}</span>
                                @include('host.games.summary', ['game' => $game])
                                {{-- D-10: actions on the right, the main one first in reading order of its group. --}}
                                <div class="ml-auto flex shrink-0 items-center gap-1">
                                    @include('host.events.partials.start-game', ['event' => $event, 'game' => $game, 'gameRunning' => $gameRunning])
                                    @foreach ([['up', -1, ! $loop->first], ['down', 1, ! $loop->last]] as [$direction, $delta, $enabled])
                                        <button type="button" data-move="{{ $direction }}" @click="nudge($el, {{ $delta }})" @disabled(! $enabled)
                                            aria-label="{{ __('games.move_' . $direction, ['title' => $game->title]) }}" title="{{ __('games.move_' . $direction, ['title' => $game->title]) }}"
                                            class="flex size-11 items-center justify-center rounded-control text-muted hover:bg-subtle disabled:opacity-30">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                <path d="{{ $direction === 'up' ? 'M6 15l6-6 6 6' : 'M6 9l6 6 6-6' }}"></path>
                                            </svg>
                                        </button>
                                    @endforeach
                                    @if ($game->packChanged())
                                        {{-- D-9, D-2: reloading replaces the copies; it warns when they were edited here. --}}
                                        <x-confirm-dialog :title="__('games.reload') . '?'" :trigger="__('games.reload_named', ['title' => $game->title])" trigger-icon="refresh" icon-only>
                                            <p class="text-[15px] text-muted">{{ __('games.reload_confirm', ['title' => $game->title]) }}@if ($game->edited_at) {{ __('games.reload_edits_lost') }}@endif</p>
                                            <form method="POST" action="{{ route('host.events.games.reload', [$event, $game]) }}" class="flex justify-end gap-2.5 pt-1.5">
                                                @csrf
                                                <x-button variant="secondary" type="button" @click="$el.closest('dialog').close()">{{ __('ui.cancel') }}</x-button>
                                                <x-button type="submit">{{ __('games.reload') }}</x-button>
                                            </form>
                                        </x-confirm-dialog>
                                    @endif
                                    <x-confirm-dialog :title="__('games.delete') . '?'" :trigger="__('games.delete_named', ['title' => $game->title])" trigger-icon="trash" icon-only>
                                        <p class="text-[15px] text-muted">{{ __('games.delete_confirm', ['title' => $game->title]) }}</p>
                                        <form method="POST" action="{{ route('host.events.games.destroy', [$event, $game]) }}" class="flex justify-end gap-2.5 pt-1.5">
                                            @csrf
                                            @method('DELETE')
                                            <x-button variant="secondary" type="button" @click="$el.closest('dialog').close()">{{ __('ui.cancel') }}</x-button>
                                            <x-button type="submit" variant="destructive">{{ __('games.delete') }}</x-button>
                                        </form>
                                    </x-confirm-dialog>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                </div>
            @endif
        </section>
    </div>
</x-layouts.host>
