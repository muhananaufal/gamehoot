@props([
    // A JavaScript expression for the ranked rows: [{rank, name, votes}] or E20 summary rows {rank, summary, count, votes}.
    'rows',
    // Optional expression deciding whether a row is shown yet (E2 reveal).
    'shown' => 'true',
    // Optional expression for a short note after the name, such as a leaderboard move (E11).
    'note' => null,
])
{{-- F20, E1, E17: the ranking list. Only rank 1 is filled; ranks 2-5 color their number. --}}
<ol {{ $attributes->merge(['class' => 'flex flex-col gap-2']) }}>
    <template x-for="row in {{ $rows }}" :key="row.rank + (row.summary ? ':summary' : row.name)">
        <li class="flex items-center gap-4 rounded-card px-4 py-2 transition-opacity duration-500"
            :class="[row.rank === 1 ? 'bg-rank-1 text-on-rank-1' : 'bg-surface', ({{ $shown }}) ? 'opacity-100' : 'opacity-0']">
            <span class="w-10 shrink-0 text-center font-display text-[1.4em] font-bold tabular-nums"
                :class="({ 2: 'text-rank-2', 3: 'text-rank-3', 4: 'text-rank-4', 5: 'text-rank-5' })[row.rank] ?? ''" x-text="row.rank"></span>
            {{-- E20: a large tie is one row on the projector. --}}
            <span class="min-w-0 grow truncate font-semibold"
                x-text="row.summary ? $store.realtime.count(@js(__('pentahoot.tied_names')), row.count) : row.name"></span>
            @if ($note)
                <span class="shrink-0 text-[0.8em] opacity-80" x-text="{{ $note }}"></span>
            @endif
            <span class="shrink-0 font-display font-semibold tabular-nums" x-text="row.votes"></span>
        </li>
    </template>
</ol>
