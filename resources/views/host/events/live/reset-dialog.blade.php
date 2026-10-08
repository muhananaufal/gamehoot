{{-- T1, E13: Reset is irreversible and logged; it opens from the game card menu. --}}
<x-confirm-dialog :title="__('pentahoot.reset_title')" :trigger="__('pentahoot.reset')" hide-trigger dialog-id="reset-question">
    <p class="text-[15px] text-muted">{{ __('pentahoot.reset_help') }}</p>
    <form method="dialog" class="flex justify-end gap-2.5 pt-1.5">
        <x-button variant="secondary" type="submit">{{ __('ui.cancel') }}</x-button>
        <x-button type="submit" variant="destructive" @click="act('reset')">{{ __('pentahoot.reset') }}</x-button>
    </form>
</x-confirm-dialog>
