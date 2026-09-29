@props([
    'href',
    'icon',
    'active' => false,
])
{{-- F20: the active item is marked with a background block, never an accent line. --}}
<a href="{{ $href }}" @if ($active) aria-current="page" @endif
    @class([
        'flex h-11 items-center gap-3 rounded-control px-3',
        'bg-nav-active font-bold text-nav-ink' => $active,
        'font-medium text-nav-muted hover:bg-nav-active hover:text-nav-ink' => ! $active,
    ])>
    <x-icon :name="$icon" :class="$active ? 'text-brand-yellow' : 'text-nav-icon'" />
    <span class="grow">{{ $slot }}</span>
</a>
