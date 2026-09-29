@props([
    'title',
    'trigger',
    'triggerVariant' => 'secondary',
    // Show the dialog on page load, e.g. to display a validation error from its form.
    'open' => false,
])
{{-- Native <dialog>: focus is trapped and Escape closes it without extra code. --}}
<div x-data x-init="@if ($open) $refs.dialog.showModal() @endif">
    <x-button :variant="$triggerVariant" @click="$refs.dialog.showModal()">{{ $trigger }}</x-button>
    <dialog x-ref="dialog" class="m-auto w-[min(400px,calc(100vw-2rem))] rounded-panel bg-surface p-0 text-ink shadow-2xl backdrop:bg-inverse/60">
        <div class="flex flex-col gap-3 p-5.5">
            <h2 class="font-display text-[22px] font-bold">{{ $title }}</h2>
            {{ $slot }}
        </div>
    </dialog>
</div>
