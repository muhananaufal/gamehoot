@props([
    'title',
    'trigger',
    'triggerVariant' => 'secondary',
    // U2: "plus" for triggers that make something new.
    'triggerIcon' => null,
    // U5: a row icon button (CSS icon, see .row-action); the trigger text becomes its label.
    'iconOnly' => false,
    // U20: shown but off when the action does not apply right now; the page says why.
    'triggerDisabled' => false,
    // Show the dialog on page load, e.g. to display a validation error from its form.
    'open' => false,
    // U13: "md" for create forms with several fields.
    'size' => 'sm',
])
{{-- Native <dialog>: focus is trapped and Escape closes it without extra code. --}}
<div x-data x-init="@if ($open) $refs.dialog.showModal() @endif" @class(['inline-flex' => $iconOnly])>
    @if ($iconOnly)
        <button type="button" class="row-action" aria-label="{{ $trigger }}" title="{{ $trigger }}" @click="$refs.dialog.showModal()"><i data-icon="{{ $triggerIcon }}"></i></button>
    @else
        <x-button :variant="$triggerVariant" :disabled="$triggerDisabled" @click="$refs.dialog.showModal()">
            @if ($triggerIcon)
                <x-icon :name="$triggerIcon" />
            @endif
            {{ $trigger }}
        </x-button>
    @endif
    <dialog x-ref="dialog" @class([
        'm-auto rounded-panel bg-surface p-0 text-left text-ink shadow-2xl',
        'w-[min(400px,calc(100vw-2rem))]' => $size === 'sm',
        'w-[min(560px,calc(100vw-2rem))]' => $size === 'md',
    ])>
        <div class="flex flex-col gap-3 p-5.5">
            <h2 class="font-display text-[22px] font-bold">{{ $title }}</h2>
            {{ $slot }}
        </div>
    </dialog>
</div>
