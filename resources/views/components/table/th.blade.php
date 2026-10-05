@props(['sort' => null, 'actions' => false])
{{-- U4: a column header of a host table. With a sort key it sorts the list (data-sort-<key> on
     the rows, one word) and shows which way: an arrow up or down in full ink while it sorts,
     a faint sort icon otherwise. The action column is centred. The header's top corners round
     like the card under it. --}}
<th scope="col" {{ $attributes->class(['px-4 py-1 first:rounded-tl-card first:pl-6 last:rounded-tr-card', 'text-center' => $actions]) }} @if ($sort) :aria-sort="ariaSort(@js($sort))" @endif>
    @if ($sort)
        <button type="button" class="inline-flex min-h-11 items-center gap-1.5 uppercase hover:text-ink" :class="sort === @js($sort) && 'text-ink'" @click="sortBy(@js($sort))">
            {{ $slot }}
            <x-icon name="arrow-up" class="size-3.5" x-show="sort === '{{ $sort }}' && dir === 'asc'" x-cloak />
            <x-icon name="arrow-down" class="size-3.5" x-show="sort === '{{ $sort }}' && dir === 'desc'" x-cloak />
            <x-icon name="sort" class="size-3.5 opacity-40" x-show="sort !== '{{ $sort }}'" />
        </button>
    @else
        <span class="inline-flex min-h-11 items-center">{{ $slot }}</span>
    @endif
</th>
