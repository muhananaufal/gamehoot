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
    <div @class(['flex h-11 overflow-hidden rounded-control border bg-field', $border])>
        @if ($prefix)
            <span class="flex items-center border-r border-line-strong bg-subtle px-3 text-[15px] text-muted" aria-hidden="true">{{ $prefix }}</span>
        @endif
        <input id="{{ $id }}" name="{{ $name }}" type="{{ $type }}"
            @if ($type !== 'password') value="{{ $submitted ? old($name, $value) : $value }}" @endif
            @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
            @if ($error) aria-invalid="true" @endif
            {{ $attributes->merge(['class' => 'min-w-0 grow bg-transparent px-3 text-[15px] text-ink']) }}>
    </div>
    @if ($hint)
        <p id="{{ $id }}-hint" class="text-sm text-muted">{{ $hint }}</p>
    @endif
    @if ($error)
        <p id="{{ $id }}-error" class="text-sm font-semibold text-on-danger">{{ $error }}</p>
    @endif
</div>
