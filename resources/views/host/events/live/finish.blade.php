{{-- D-8, D-10, E13: finishing a game. Once every question is done it is the last step and needs
     no dialog (direct); before that it is Close game: unfinished questions do not count, and it
     is confirmed and logged. In Live control Close game opens from the game card menu. --}}
@if ($direct ?? false)
    <form method="POST" action="{{ route('host.events.games.finish', [$event, $game]) }}">
        @csrf
        <x-button type="submit">{{ $label }}</x-button>
    </form>
@else
    <x-confirm-dialog :title="$label . '?'" :trigger="$label" :hide-trigger="isset($dialogId)" :dialog-id="$dialogId ?? null">
        <p class="text-body text-muted">{{ __('games.close_game_confirm', ['title' => $game->title]) }}</p>
        <form method="POST" action="{{ route('host.events.games.finish', [$event, $game]) }}" class="flex justify-end gap-2.5 pt-1.5">
            @csrf
            <x-button variant="secondary" type="button" @click="$el.closest('dialog').close()">{{ __('ui.cancel') }}</x-button>
            <x-button type="submit">{{ $label }}</x-button>
        </form>
    </x-confirm-dialog>
@endif
