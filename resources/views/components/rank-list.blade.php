@props([
    // A JavaScript expression for the ranked rows: [{rank, name, votes}] or E20 summary rows {rank, summary, count, votes}.
    'rows',
    // Optional expression deciding whether a row is shown yet (E2 reveal).
    'shown' => 'true',
    // E11: optional expression for a row's move, {direction: up|down|new, steps, label} or null.
    'move' => null,
    // U34 (audit UI-4): the move's size; a phone passes a named size so it never drops below 15 px.
    'moveSize' => 'text-[0.8em]',
])
{{-- F20, E1, E17: the ranking list. Only rank 1 is filled; ranks 2-5 color their number. --}}
{{-- U8: a name that changes rank slides to its new place (x-flip, keyed by name). --}}
<ol x-flip.moves {{ $attributes->merge(['class' => 'flex flex-col gap-2']) }}>
    <template x-for="row in {{ $rows }}" :key="row.rank + (row.summary ? ':summary' : row.name)">
        <li :data-flip-key="row.summary ? 'summary:' + row.rank : row.name" class="row-enter flex items-center gap-4 rounded-card px-4 py-2 transition-opacity duration-500"
            :class="[row.rank === 1 ? 'bg-rank-1 text-on-rank-1' : 'bg-surface', ({{ $shown }}) ? 'opacity-100' : 'opacity-0']">
            <span class="w-10 shrink-0 text-center font-display text-[1.4em] font-bold tabular-nums"
                :class="({ 2: 'text-rank-2', 3: 'text-rank-3', 4: 'text-rank-4', 5: 'text-rank-5' })[row.rank] ?? ''" x-text="row.rank"></span>
            {{-- E20: a large tie is one row on the projector. --}}
            <span class="min-w-0 grow truncate font-semibold"
                x-text="row.summary ? $store.realtime.count(@js(__('pentahoot.tied_names')), row.count) : row.name"></span>
            @if ($move)
                {{-- E11: a colored arrow with the steps, or a NEW badge. The shape and the number
                     say it without the color; screen readers hear the label ("up 2"). On the
                     yellow rank 1 row the arrows keep the row's own text color for contrast. --}}
                <span x-show="{{ $move }}" class="flex shrink-0 items-center font-display font-bold tabular-nums {{ $moveSize }}"
                    role="img" :aria-label="({{ $move }})?.label">
                    <span x-show="({{ $move }})?.direction === 'up'" aria-hidden="true" :class="row.rank === 1 ? '' : 'text-on-success'">▲<span x-text="({{ $move }})?.steps"></span></span>
                    <span x-show="({{ $move }})?.direction === 'down'" aria-hidden="true" :class="row.rank === 1 ? '' : 'text-on-danger'">▼<span x-text="({{ $move }})?.steps"></span></span>
                    <span x-show="({{ $move }})?.direction === 'new'" aria-hidden="true" class="rounded-control bg-accent px-2 py-0.5 text-[0.75em] tracking-wide text-on-accent">{{ __('tebak.new_badge') }}</span>
                </span>
            @endif
            <span class="shrink-0 font-display font-semibold tabular-nums" x-text="row.votes"></span>
        </li>
    </template>
</ol>
