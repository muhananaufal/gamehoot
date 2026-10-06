{{-- D-1, B-3: starting a game. The first game locks the name list for good, so it is confirmed;
     a game without questions, or while another one runs, cannot start. --}}
@php $blocked = ($game->questions_count ?? 0) === 0 || ($gameRunning ?? false); @endphp
@if ($event->names_locked_at === null && ! $blocked)
    <x-confirm-dialog :title="__('games.start') . '?'" :trigger="__('games.start')" trigger-variant="primary">
        <p class="text-[15px] text-muted">{{ __('games.start_locks_names') }}</p>
        <form method="POST" action="{{ route('host.events.games.start', [$event, $game]) }}" class="flex justify-end gap-2.5 pt-1.5">
            @csrf
            <x-button variant="secondary" type="button" @click="$el.closest('dialog').close()">{{ __('ui.cancel') }}</x-button>
            <x-button type="submit">{{ __('games.start') }}</x-button>
        </form>
    </x-confirm-dialog>
@else
    <form method="POST" action="{{ route('host.events.games.start', [$event, $game]) }}">
        @csrf
        <x-button type="submit" :disabled="$blocked">{{ __('games.start') }}</x-button>
    </form>
@endif
