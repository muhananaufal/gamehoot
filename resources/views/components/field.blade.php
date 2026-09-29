@props([
    'name',
    'label',
    'type' => 'text',
    'value' => null,
    'hint' => null,
])
@php
    $id = 'field-' . str_replace(['.', '[', ']'], '-', $name);
    $error = $errors->first($name);
    $describedBy = trim(($hint ? "{$id}-hint " : '') . ($error ? "{$id}-error" : ''));
@endphp
<div class="flex flex-col gap-1.5">
    <label for="{{ $id }}" class="text-sm font-semibold">{{ $label }}</label>
    <input id="{{ $id }}" name="{{ $name }}" type="{{ $type }}"
        @if ($type !== 'password') value="{{ old($name, $value) }}" @endif
        @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
        @if ($error) aria-invalid="true" @endif
        {{ $attributes->merge(['class' => 'h-11 rounded-control border bg-field px-3 text-[15px] text-ink ' . ($error ? 'border-on-danger' : 'border-line-strong')]) }}>
    @if ($hint)
        <p id="{{ $id }}-hint" class="text-sm text-muted">{{ $hint }}</p>
    @endif
    @if ($error)
        <p id="{{ $id }}-error" class="text-sm font-semibold text-on-danger">{{ $error }}</p>
    @endif
</div>
