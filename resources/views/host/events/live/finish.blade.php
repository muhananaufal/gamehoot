{{-- D-8, E13: finishing a game; unfinished questions do not count and it is logged. --}}
<x-confirm-dialog :title="$label . '?'" :trigger="$label">
    <p class="text-[15px] text-muted">{{ __('games.finish_confirm', ['title' => $game->title]) }}</p>
    <form method="POST" action="{{ route('host.events.games.finish', [$event, $game]) }}" class="flex justify-end gap-2.5 pt-1.5">
        @csrf
        <x-button variant="secondary" type="button" @click="$el.closest('dialog').close()">{{ __('ui.cancel') }}</x-button>
        <x-button type="submit">{{ $label }}</x-button>
    </form>
</x-confirm-dialog>
