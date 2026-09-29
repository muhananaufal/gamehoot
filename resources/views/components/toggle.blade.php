@props([
    'name',
    'label',
    'checked' => false,
    'hint' => null,
])
@php $id = 'toggle-' . $name; @endphp
{{-- F21: styled switch; the hidden field sends 0 when it is off. --}}
<div class="flex items-start justify-between gap-4">
    <span class="flex flex-col gap-1">
        <label for="{{ $id }}" class="text-sm font-semibold">{{ $label }}</label>
        @if ($hint)
            <span class="text-sm text-muted">{{ $hint }}</span>
        @endif
    </span>
    <input type="hidden" name="{{ $name }}" value="0">
    <span class="relative inline-flex h-11 shrink-0 items-center">
        <input id="{{ $id }}" type="checkbox" name="{{ $name }}" value="1" role="switch" class="peer sr-only" @checked(old($name, $checked))>
        <span class="h-7 w-12 rounded-full bg-line-strong transition-colors peer-checked:bg-accent peer-focus-visible:outline-3 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-accent" aria-hidden="true"></span>
        <span class="absolute left-1 size-5 rounded-full bg-surface transition-transform peer-checked:translate-x-5" aria-hidden="true"></span>
    </span>
</div>
