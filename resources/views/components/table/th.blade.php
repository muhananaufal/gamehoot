@props(['sort' => null])
{{-- U4: a column header of a host table. With a sort key it sorts the list (data-sort-<key> on
     the rows, one word); without one it is a plain header (the actions column). --}}
<th scope="col" {{ $attributes->merge(['class' => 'px-4 py-1 first:pl-6']) }} @if ($sort) :aria-sort="ariaSort(@js($sort))" @endif>
    @if ($sort)
        <button type="button" class="inline-flex min-h-11 items-center gap-1.5 uppercase hover:text-ink" @click="sortBy(@js($sort))">{{ $slot }} <x-icon name="sort" class="size-3.5" /></button>
    @else
        <span class="inline-flex min-h-11 items-center">{{ $slot }}</span>
    @endif
</th>
