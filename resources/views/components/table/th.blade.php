@props(['sort' => null, 'actions' => false])
{{-- U4: a column header of a host table. With a sort key it sorts the list (data-sort-<key> on
     the rows, one word) and shows which way in one ⇅ icon: while it sorts, the arrow of its
     direction is solid and the other faint; otherwise both are faint. The action column is
     centred. The header's top corners round like the card under it. --}}
<th scope="col" {{ $attributes->class(['px-4 py-1 first:rounded-tl-card first:pl-6 last:rounded-tr-card', 'text-center' => $actions]) }} @if ($sort) :aria-sort="ariaSort(@js($sort))" @endif>
    @if ($sort)
        <button type="button" class="inline-flex min-h-11 items-center gap-1.5 uppercase hover:text-ink" :class="sort === @js($sort) && 'text-ink'" @click="sortBy(@js($sort))">
            {{ $slot }}
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M8 4v16M4 8l4-4 4 4" class="transition-opacity" :class="sort === '{{ $sort }}' && dir === 'asc' ? 'opacity-100' : 'opacity-30'"></path>
                <path d="M16 20V4M12 16l4 4 4-4" class="transition-opacity" :class="sort === '{{ $sort }}' && dir === 'desc' ? 'opacity-100' : 'opacity-30'"></path>
            </svg>
        </button>
    @else
        <span class="inline-flex min-h-11 items-center">{{ $slot }}</span>
    @endif
</th>
