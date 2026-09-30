@php
    use App\Enums\GameStatus;
@endphp
{{-- D-9, T8: the games of an event, in play order. --}}
<x-layouts.host :title="__('games.title') . ' · ' . $event->name" :event="$event"
    :breadcrumbs="[[__('host.dashboard.title'), route('host.dashboard')], [$event->name, null], [__('games.title'), null]]">

    @error('action')
        <div role="alert" class="rounded-card bg-danger px-4 py-3 text-sm font-semibold text-on-danger">{{ $message }}</div>
    @enderror

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_minmax(0,380px)]">
        <section class="flex flex-col gap-4 rounded-card bg-surface p-6" aria-labelledby="games-heading">
            <div class="flex flex-col gap-1">
                <h2 id="games-heading" class="font-display text-xl font-semibold">{{ __('games.title') }}</h2>
                <p class="text-sm text-muted">{{ __('games.subtitle') }}</p>
            </div>

            @forelse ($games as $game)
                @php
                    $running = $event->active_game_id === $game->id;
                    $status = $running ? 'running' : ($game->status === GameStatus::Finished ? 'finished' : 'waiting');
                @endphp
                <article class="flex flex-wrap items-center gap-3 border-t border-line-soft pt-4">
                    <div class="flex min-w-0 grow flex-col gap-0.5">
                        <h3 class="truncate font-bold">{{ $game->position }}. {{ $game->title }}</h3>
                        <p class="text-sm text-muted">{{ __('games.types.' . $game->type->value) }} · {{ trans_choice('games.questions', $game->questions_count) }}</p>
                    </div>
                    <x-chip :tone="match ($status) { 'running' => 'success', 'finished' => 'warning', default => 'neutral' }" :dot="$running">
                        {{ __('games.status.' . $status) }}
                    </x-chip>
                    @if ($status === 'waiting')
                        <div class="flex flex-wrap items-center gap-2">
                            <form method="POST" action="{{ route('host.events.games.start', [$event, $game]) }}">
                                @csrf
                                <x-button type="submit" :disabled="$game->questions_count === 0 || $event->active_game_id !== null">{{ __('games.start') }}</x-button>
                            </form>
                            @if ($game->source_pack_id)
                                <form method="POST" action="{{ route('host.events.games.reload', [$event, $game]) }}">
                                    @csrf
                                    <x-button type="submit" variant="secondary" :title="__('games.reload_help')">{{ __('games.reload') }}</x-button>
                                </form>
                            @endif
                            <x-confirm-dialog :title="__('games.delete') . '?'" :trigger="__('games.delete')" trigger-variant="secondary">
                                <p class="text-[15px] text-muted">{{ __('games.delete_confirm', ['title' => $game->title]) }}</p>
                                <form method="POST" action="{{ route('host.events.games.destroy', [$event, $game]) }}" class="flex justify-end gap-2.5 pt-1.5">
                                    @csrf
                                    @method('DELETE')
                                    <x-button variant="secondary" formmethod="dialog" type="submit">{{ __('ui.cancel') }}</x-button>
                                    <x-button type="submit" variant="destructive">{{ __('games.delete') }}</x-button>
                                </form>
                            </x-confirm-dialog>
                        </div>
                    @elseif ($running)
                        <x-button :href="route('host.events.live', $event)" variant="secondary">{{ __('realtime.live.title') }}</x-button>
                    @endif
                </article>
            @empty
                <p class="border-t border-line-soft pt-4 text-muted">{{ __('games.empty') }}</p>
            @endforelse
        </section>

        <section class="flex flex-col gap-4 rounded-card bg-surface p-6" aria-labelledby="add-heading">
            <h2 id="add-heading" class="font-display text-xl font-semibold">{{ __('games.add_from_pack') }}</h2>
            @if ($packs->isEmpty())
                <p class="text-sm text-muted">{{ __('games.no_packs') }}</p>
                <x-button :href="route('host.packs.index')" variant="secondary">{{ __('host.nav.question_packs') }}</x-button>
            @else
                <form method="POST" action="{{ route('host.events.games.store', $event) }}" class="flex flex-col gap-4" novalidate>
                    @csrf
                    <fieldset class="flex flex-col gap-2">
                        <legend class="mb-1 text-sm font-bold">{{ __('games.choose_pack') }}</legend>
                        @foreach ($packs as $pack)
                            <label class="flex min-h-11 cursor-pointer items-center gap-3 rounded-control border border-line px-3 py-2 has-checked:border-accent">
                                <input type="radio" name="pack_id" value="{{ $pack->id }}" @checked(old('pack_id') === $pack->id) required>
                                <span class="flex min-w-0 grow flex-col">
                                    <span class="truncate font-semibold">{{ $pack->title }}</span>
                                    <span class="text-sm text-muted">{{ __('games.types.' . $pack->game_type->value) }} · {{ trans_choice('games.questions', $pack->questions_count) }}</span>
                                </span>
                            </label>
                        @endforeach
                        @error('pack_id')
                            <p class="text-sm font-semibold text-on-danger">{{ $message }}</p>
                        @enderror
                    </fieldset>
                    <x-button type="submit">{{ __('games.add') }}</x-button>
                </form>
            @endif
            <p class="text-sm text-muted">{{ __('games.playable_later') }}</p>
        </section>
    </div>
</x-layouts.host>
