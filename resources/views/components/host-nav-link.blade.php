@props([
    'href',
    'icon',
    'active' => false,
])
{{-- F20: the active item is marked with a background block, never an accent line. U1: in the
     narrow sidebar only the icon shows; the label stays for screen readers and, U33, as a
     Pentahoot tooltip to the right (resources/js/tooltip.js). --}}
<a href="{{ $href }}" title="{{ trim($slot) }}" data-tip-collapsed data-tip-side="right" @if ($active) aria-current="page" @endif
    @class([
        'flex h-11 shrink-0 items-center gap-3 rounded-control px-3 lg:group-data-[collapsed=true]:justify-center lg:group-data-[collapsed=true]:px-0',
        'bg-nav-active font-bold text-nav-ink' => $active,
        'font-medium text-nav-muted hover:bg-nav-active hover:text-nav-ink' => ! $active,
    ])>
    <x-icon :name="$icon" :class="$active ? 'shrink-0 text-nav-accent' : 'shrink-0 text-nav-icon'" />
    <span class="grow truncate lg:group-data-[collapsed=true]:sr-only">{{ $slot }}</span>
</a>
