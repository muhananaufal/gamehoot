@props([
    // A JavaScript expression for {first, second, third, rest} (pentahoot.js podium()).
    'stand',
])
{{-- F20, E17: the final podium, 2 - 1 - 3, with ranks 4 and 5 as small rows below. A tie can
     put several names on one step or leave a step empty. --}}
<div {{ $attributes->merge(['class' => 'flex flex-col gap-[3vh]']) }}>
    <div class="grid grid-cols-3 items-end gap-[2vw]">
        @foreach (['second' => ['h-[55%]', 'text-rank-2'], 'first' => ['h-[75%]', 'text-on-rank-1'], 'third' => ['h-[40%]', 'text-rank-3']] as $step => [$height, $number])
            <div class="flex h-full flex-col items-center justify-end gap-2">
                <template x-for="row in {{ $stand }}.{{ $step }}" :key="row.name">
                    <div class="flex max-w-full flex-col items-center gap-1 text-center" :class="{{ $stand }}.{{ $step }}.length > 2 ? 'text-[0.7em]' : ''">
                        <span class="flex size-[2.6em] items-center justify-center rounded-full bg-surface font-display font-bold"
                            x-text="row.name.split(/\s+/).filter(Boolean).slice(0, 2).map((part) => part[0].toUpperCase()).join('')"></span>
                        <span class="max-w-full truncate font-semibold" x-text="row.name"></span>
                        <span class="font-display font-semibold tabular-nums" x-text="row.votes"></span>
                    </div>
                </template>
                <div @class([
                    'flex w-full items-start justify-center rounded-t-card pt-2 font-display text-[1.6em] font-bold',
                    $height,
                    'bg-rank-1' => $step === 'first',
                    'bg-surface' => $step !== 'first',
                    $number,
                ])>{{ ['first' => 1, 'second' => 2, 'third' => 3][$step] }}</div>
            </div>
        @endforeach
    </div>
    <x-rank-list :rows="$stand . '.rest'" class="text-[0.8em]" />
</div>
