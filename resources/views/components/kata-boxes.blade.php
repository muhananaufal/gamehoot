@props([
    // A JavaScript expression for the words of boxes: [[{c, b, o}]] (TebakKataEngine snapshot).
    'words',
    // Optional expressions making closed boxes clickable, for hints on Live control (E5).
    'canOpen' => null,
    'open' => null,
])
{{-- F20, E5: the letter boxes. One word per line, never cut; punctuation shows without a box.
     Open-from-the-start, hint and revealed boxes differ in color so the room can tell them apart. --}}
<div {{ $attributes->merge(['class' => 'flex flex-col items-center gap-[0.35em]']) }}>
    <template x-for="(word, w) in {{ $words }}" :key="w">
        <div class="flex flex-wrap justify-center gap-[0.25em]">
            <template x-for="(cell, i) in word" :key="w + '-' + i">
                <span class="flex">
                    <span x-show="cell.b === null" class="flex h-[1.6em] w-[0.6em] items-center justify-center font-display font-bold" x-text="cell.c"></span>
                    @if ($canOpen)
                        <button type="button" x-show="cell.b !== null" :disabled="!({{ $canOpen }})" @click="{{ $open }}"
                            :aria-label="label('open_box', { N: cell.b + 1 })"
                            :data-test="'open-box-' + (cell.b + 1)"
                            class="flex h-[1.6em] min-h-11 w-[1.3em] min-w-11 items-center justify-center rounded-control border-2 font-display font-bold enabled:cursor-pointer enabled:hover:border-accent"
                            :class="({ initial: 'border-accent bg-accent text-on-accent', hint: 'border-rank-1 bg-rank-1 text-on-rank-1', revealed: 'border-success-dot bg-success text-on-success', closed: 'border-line-strong bg-surface' })[legendOf(cell)]"
                            x-text="cell.c ?? ''"></button>
                    @else
                        <span x-show="cell.b !== null" aria-hidden="true"
                            class="flex h-[1.6em] w-[1.3em] items-center justify-center rounded-control border-2 font-display font-bold"
                            :class="({ initial: 'border-accent bg-accent text-on-accent', hint: 'border-rank-1 bg-rank-1 text-on-rank-1', revealed: 'border-success-dot bg-success text-on-success', closed: 'border-line-strong bg-surface' })[legendOf(cell)]"
                            x-text="cell.c ?? ''"></span>
                    @endif
                </span>
            </template>
        </div>
    </template>
</div>
