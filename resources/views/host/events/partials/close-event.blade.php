{{-- U20, T6, D-10: Close event is destructive (red); a running game ends first, and the dialog says
     which. In Live control it opens from the game card menu ($dialogId, no trigger of its own). --}}
<x-confirm-dialog :title="__('events.close') . '?'" :trigger="__('events.close')" trigger-variant="destructive"
    :hide-trigger="isset($dialogId)" :dialog-id="$dialogId ?? null">
    <p class="text-[15px] text-muted">{{ __('events.close_confirm', ['name' => $event->name]) }}</p>
    @if ($runningGame)
        <p class="text-[15px] font-semibold text-on-warning">{{ __('events.close_ends_game', ['title' => $runningGame->title]) }}</p>
    @endif
    <form method="POST" action="{{ route('host.events.close', $event) }}" class="flex justify-end gap-2.5 pt-1.5">
        @csrf
        <x-button variant="secondary" type="button" @click="$el.closest('dialog').close()">{{ __('ui.cancel') }}</x-button>
        <x-button type="submit" variant="destructive">{{ __('events.close') }}</x-button>
    </form>
</x-confirm-dialog>
