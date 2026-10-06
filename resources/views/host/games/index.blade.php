@php
    use App\Enums\GameStatus;

    // D-1, G7: a game still running blocks starting another; a finished one on screen does not.
    $gameRunning = $games->contains(fn ($game) => $game->id === $event->active_game_id && $game->status !== GameStatus::Finished);
@endphp
{{-- D-9, T8: the games of an event, in play order. --}}
<x-layouts.host :title="__('games.title') . ' · ' . $event->name" :event="$event"
    :breadcrumbs="[[__('host.dashboard.title'), route('host.dashboard')], [$event->name, null], [__('games.title'), null]]">

    @error('action')
        <div role="alert" class="rounded-card bg-danger px-4 py-3 text-sm font-semibold text-on-danger">{{ $message }}</div>
    @enderror

    <x-realtime :event="$event" :audience="\App\Enums\Audience::Host" />
    {{-- U23: the pack list of Question packs on the left, where a click adds a pack; the games on the right. --}}
    <div class="grid gap-5 lg:grid-cols-[260px_minmax(0,1fr)]">
        @if ($packs->isEmpty())
            <section class="flex flex-col gap-3 self-start rounded-card bg-surface p-4" aria-label="{{ __('games.add_from_pack') }}">
                <p class="text-sm text-muted">{{ __('games.no_packs') }}</p>
                <x-button :href="route('host.packs.index')" variant="secondary">{{ __('host.nav.question_packs') }}</x-button>
            </section>
        @else
            {{-- A grid, not a flex column: the list keeps its self-start without shrinking to its content. --}}
            <div class="grid gap-2 self-start">
                <p class="px-1 text-sm text-muted">{{ __('games.add_hint') }}</p>
                <x-pack-list :packs="$packs" :types="$types" :add-to="route('host.events.games.store', $event)" />
                @error('pack_id')
                    <p class="px-1 text-sm font-semibold text-on-danger">{{ $message }}</p>
                @enderror
            </div>
        @endif

        {{-- U12: game status follows starts and finishes made from Live control or by a co-host. --}}
        <section id="games-live" x-data="liveRegion" class="flex flex-col gap-4 rounded-card bg-surface p-6" aria-label="{{ __('games.title') }}">
            <p class="text-sm text-muted">{{ __('games.subtitle') }}</p>

            @forelse ($games as $game)
                @php
                    $running = $event->active_game_id === $game->id && $game->status !== GameStatus::Finished;
                    $status = $running ? 'running' : ($game->status === GameStatus::Finished ? 'finished' : 'waiting');
                @endphp
                <article class="flex flex-wrap items-center gap-3 border-t border-line-soft pt-4">
                    <div class="flex min-w-0 grow flex-col gap-0.5">
                        <h3 class="truncate font-bold">{{ $game->position }}. {{ $game->title }}</h3>
                        @if ($status === 'waiting' && $game->packChanged())
                            {{-- D-9: the pack moved on since the copy; Reload takes the new version. --}}
                            {{-- D-10: purple, since yellow means finished. --}}
                            <x-chip tone="accent" class="w-fit">{{ __('games.pack_changed') }}</x-chip>
                        @endif
                        <p class="text-sm text-muted">{{ __('games.types.' . $game->type->value) }} · <a href="{{ route('host.events.games.questions.index', [$event, $game]) }}" class="text-link underline-offset-4 hover:underline">{{ trans_choice('games.questions', $game->questions_count) }}</a></p>
                        {{-- U6: when it was added, or when it started (from the activity log), in WIB (T10). --}}
                        <p class="text-[13px] text-muted">
                            @if (isset($startedAt[$game->id]))
                                {{ __('games.started_at', ['time' => $startedAt[$game->id]->timezone(config('app.display_timezone'))->format('j M, H:i')]) }}
                            @else
                                {{ __('games.added_at', ['date' => $game->created_at?->timezone(config('app.display_timezone'))->format('j M')]) }}
                            @endif
                        </p>
                    </div>
                    <x-chip :tone="match ($status) { 'running' => 'success', 'finished' => 'warning', default => 'neutral' }" :dot="$running">
                        {{ __('games.status.' . $status) }}
                    </x-chip>
                    @if ($status === 'waiting')
                        <div class="flex flex-wrap items-center gap-2">
                            @include('host.events.partials.start-game', ['event' => $event, 'game' => $game, 'gameRunning' => $gameRunning])
                            @if ($game->packChanged())
                                {{-- D-9, D-2: reloading replaces the copies and their edits, so it is confirmed. --}}
                                <x-confirm-dialog :title="__('games.reload') . '?'" :trigger="__('games.reload')">
                                    <p class="text-[15px] text-muted">{{ __('games.reload_confirm', ['title' => $game->title]) }}</p>
                                    <form method="POST" action="{{ route('host.events.games.reload', [$event, $game]) }}" class="flex justify-end gap-2.5 pt-1.5">
                                        @csrf
                                        <x-button variant="secondary" type="button" @click="$el.closest('dialog').close()">{{ __('ui.cancel') }}</x-button>
                                        <x-button type="submit">{{ __('games.reload') }}</x-button>
                                    </form>
                                </x-confirm-dialog>
                            @endif
                            {{-- U23: deleting is destructive, so its trigger is red too. --}}
                            <x-confirm-dialog :title="__('games.delete') . '?'" :trigger="__('games.delete')" trigger-variant="destructive">
                                <p class="text-[15px] text-muted">{{ __('games.delete_confirm', ['title' => $game->title]) }}</p>
                                <form method="POST" action="{{ route('host.events.games.destroy', [$event, $game]) }}" class="flex justify-end gap-2.5 pt-1.5">
                                    @csrf
                                    @method('DELETE')
                                    <x-button variant="secondary" type="button" @click="$el.closest('dialog').close()">{{ __('ui.cancel') }}</x-button>
                                    <x-button type="submit" variant="destructive">{{ __('games.delete') }}</x-button>
                                </form>
                            </x-confirm-dialog>
                        </div>
                    @elseif ($running)
                        <x-button :href="route('host.events.live', $event)" variant="secondary">{{ __('realtime.live.title') }}</x-button>
                    @endif
                </article>
            @empty
                <x-empty-state :title="__('games.empty_title')" :body="__('games.empty')" class="bg-subtle py-8" />
            @endforelse
        </section>

    </div>
</x-layouts.host>
