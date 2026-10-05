@props([
    'name',
    'label',
    'type' => 'text',
    'value' => null,
    'hint' => null,
    // Fixed text shown before the input, e.g. the site address in front of an event link.
    'prefix' => null,
    'id' => null,
    // False when several forms on one page share a field name and another one was submitted:
    // its old input and errors then do not belong here.
    'submitted' => true,
])
@php
    $id ??= 'field-' . str_replace(['.', '[', ']'], '-', $name);
    $error = $submitted ? $errors->first($name) : null;
    $describedBy = trim(($hint ? "{$id}-hint " : '') . ($error ? "{$id}-error" : ''));
    $border = $error ? 'border-on-danger' : 'border-line-strong';
@endphp
<div class="flex flex-col gap-1.5">
    <label for="{{ $id }}" class="text-sm font-semibold">{{ $label }}</label>
    {{-- U17: a password can be shown and hidden again; the input type follows the button. --}}
    {{-- U20: a field that cannot change now (e.g. the link of an open event) looks switched off. --}}
    <div @class(['flex h-11 overflow-hidden rounded-control border bg-field has-[input:read-only]:bg-subtle', $border]) @if ($type === 'password') x-data="{ shown: false }" @endif>
        @if ($prefix)
            <span class="flex items-center border-r border-line-strong bg-subtle px-3 text-[15px] text-muted" aria-hidden="true">{{ $prefix }}</span>
        @endif
        <input id="{{ $id }}" name="{{ $name }}" type="{{ $type }}"
            @if ($type !== 'password') value="{{ $submitted ? old($name, $value) : $value }}" @endif
            @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
            @if ($error) aria-invalid="true" @endif
            @if ($type === 'password') :type="shown ? 'text' : 'password'" @endif
            {{ $attributes->merge(['class' => 'min-w-0 grow bg-transparent px-3 text-[15px] text-ink read-only:cursor-not-allowed read-only:text-muted']) }}>
        @if ($type === 'password')
            <button type="button" @click="shown = ! shown" :aria-pressed="shown.toString()" aria-label="{{ __('accounts.show_password') }}" title="{{ __('accounts.show_password') }}"
                class="flex w-11 shrink-0 items-center justify-center text-muted hover:text-ink">
                <span x-show="! shown" class="flex"><x-icon name="eye" /></span>
                <span x-show="shown" x-cloak class="flex"><x-icon name="eye-off" /></span>
            </button>
        @endif
    </div>
    @if ($hint)
        <p id="{{ $id }}-hint" class="text-sm text-muted">{{ $hint }}</p>
    @endif
    @if ($error)
        <p id="{{ $id }}-error" class="text-sm font-semibold text-on-danger">{{ $error }}</p>
    @endif
</div>
