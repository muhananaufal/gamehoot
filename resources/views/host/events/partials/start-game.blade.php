{{-- D-1, B-3, U24: starting a game always asks first; before the first game it also says that the
     name list locks for good. A game without questions, or while another one runs, cannot start. --}}
@php $blocked = ($game->questions_count ?? 0) === 0 || ($gameRunning ?? false); @endphp
<x-confirm-dialog :title="__('games.start_named', ['title' => $game->title])" :trigger="__('games.start')" trigger-variant="primary" :trigger-disabled="$blocked">
    <p class="text-body text-muted">{{ __('games.start_confirm') }}</p>
    @if ($event->names_locked_at === null)
        <p class="text-body text-muted">{{ __('games.start_locks_names') }}</p>
    @endif
    <form method="POST" action="{{ route('host.events.games.start', [$event, $game]) }}" class="flex justify-end gap-2.5 pt-1.5">
        @csrf
        <x-button variant="secondary" type="button" @click="$el.closest('dialog').close()">{{ __('ui.cancel') }}</x-button>
        <x-button type="submit">{{ __('games.start') }}</x-button>
    </form>
</x-confirm-dialog>
