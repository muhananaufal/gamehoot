{{-- U20, T6, D-10: Close event is destructive (red); a running game ends first, and the dialog says
     which. In Live control it opens from the game card menu ($dialogId, no trigger of its own). --}}
<x-confirm-dialog :title="__('events.close') . '?'" :trigger="__('events.close')" trigger-variant="destructive"
    :hide-trigger="isset($dialogId)" :dialog-id="$dialogId ?? null">
    <p class="text-body text-muted">{{ __('events.close_confirm', ['name' => $event->name]) }}</p>
    @if ($runningGame)
        <p class="text-body font-semibold text-on-warning">{{ __('events.close_ends_game', ['title' => $runningGame->title]) }}</p>
    @endif
    <form method="POST" action="{{ route('host.events.close', $event) }}" class="flex justify-end gap-2.5 pt-1.5">
        @csrf
        {{-- G15: the version this host is looking at; the store's when the page follows the event live. --}}
        <input type="hidden" name="version" value="{{ $event->state_version }}" x-data x-bind:value="($store.realtime?.version ?? -1) >= 0 ? $store.realtime.version : {{ $event->state_version }}">
        <x-button variant="secondary" type="button" @click="$el.closest('dialog').close()">{{ __('ui.cancel') }}</x-button>
        <x-button type="submit" variant="destructive">{{ __('events.close') }}</x-button>
    </form>
</x-confirm-dialog>
