@props([
    // [['value' => ..., 'label' => ..., 'count' => optional], ...]
    'options',
    // The accessible name; the page shows its own visible label where it needs one.
    'label',
    // Sends the choice with a form; leave out when bound with x-model.
    'name' => null,
    'value' => null,
])
@php
    $id = 'select-' . \Illuminate\Support\Str::random(8);
@endphp
{{-- U22: the themed dropdown (resources/js/select-box.js), W3C APG select-only combobox. --}}
<div x-data="selectBox({ options: {{ \Illuminate\Support\Js::from($options) }}, value: {{ \Illuminate\Support\Js::from($value ?? ($options[0]['value'] ?? null)) }} })" x-modelable="value"
    @keydown.escape.stop="open && close(true)" @click.outside="open && close()"
    {{ $attributes->merge(['class' => 'relative inline-flex']) }}>
    @if ($name)
        <input type="hidden" name="{{ $name }}" :value="value">
    @endif
    <button type="button" x-ref="button" role="combobox" aria-label="{{ $label }}" aria-haspopup="listbox" aria-controls="{{ $id }}"
        :aria-expanded="open.toString()" @click="toggle()" @keydown="onKey($event)"
        class="inline-flex h-11 w-full min-w-28 cursor-pointer items-center justify-between gap-3 rounded-control border border-line-strong bg-field px-3.5 text-[15px] font-semibold text-ink tabular-nums hover:bg-subtle">
        <span class="truncate" x-text="selectedLabel"></span>
        <x-icon name="chevron-down" class="size-4 shrink-0 text-muted transition-transform" ::class="open && 'rotate-180'" />
    </button>
    <div x-show="open" x-cloak x-transition:enter="menu-enter" x-transition:enter-start="menu-from" x-transition:enter-end="menu-to"
        class="absolute top-full left-0 z-30 mt-1 flex max-h-72 min-w-full flex-col gap-1 overflow-hidden rounded-card border border-line bg-surface p-1.5 shadow-xl">
        <template x-if="searchable">
            <input type="search" x-ref="search" x-model="q" @keydown="onKey($event)" @input="active = 0" aria-label="{{ __('ui.search') }}" placeholder="{{ __('ui.search') }}" autocomplete="off"
                class="h-10 w-full shrink-0 rounded-control border border-line-strong bg-field px-3 text-sm text-ink">
        </template>
        <ul id="{{ $id }}" x-ref="list" role="listbox" tabindex="-1" aria-label="{{ $label }}" @keydown="onKey($event)"
            :aria-activedescendant="'{{ $id }}-' + active" class="flex flex-col overflow-y-auto">
            <template x-for="(option, index) in visible" :key="option.value">
                <li role="option" :id="'{{ $id }}-' + index" :data-index="index" :aria-selected="(option.value === value).toString()"
                    @click="choose(option)" @mouseenter="active = index"
                    class="flex min-h-10 cursor-pointer items-center justify-between gap-4 rounded-control px-3 text-sm font-semibold whitespace-nowrap"
                    :class="index === active ? 'bg-subtle text-ink' : 'text-ink'">
                    <span class="flex items-center gap-2">
                        <x-icon name="check" class="size-4" ::class="option.value === value ? 'text-accent' : 'invisible'" />
                        <span x-text="option.label"></span>
                    </span>
                    <span x-show="option.count !== undefined" class="text-muted tabular-nums" x-text="option.count"></span>
                </li>
            </template>
            <li x-show="visible.length === 0" class="px-3 py-2 text-sm text-muted">{{ __('ui.no_match') }}</li>
        </ul>
    </div>
</div>
