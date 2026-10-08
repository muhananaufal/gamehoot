{{-- U24: a change of state asks first. The trigger opens a dialog that names what happens; the
     form is sent only from the dialog. $trigger, $title, $body, $action, $confirm, optional
     $variant (trigger), $tone (confirm button), $fields (hidden name => value). --}}
<x-confirm-dialog :title="$title" :trigger="$trigger" :trigger-variant="$variant ?? 'primary'">
    <p class="text-[15px] text-muted">{{ $body }}</p>
    <form method="POST" action="{{ $action }}" class="flex justify-end gap-2.5 pt-1.5">
        @csrf
        @foreach ($fields ?? [] as $name => $value)
            <input type="hidden" name="{{ $name }}" value="{{ $value }}">
        @endforeach
        <x-button variant="secondary" type="button" @click="$el.closest('dialog').close()">{{ __('ui.cancel') }}</x-button>
        <x-button type="submit" :variant="$tone ?? 'primary'">{{ $confirm }}</x-button>
    </form>
</x-confirm-dialog>
