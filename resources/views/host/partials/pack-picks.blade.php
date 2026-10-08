{{-- D-9, U26: what is picked from the pack list, in order; each can be taken out again. Used by
     Add games and the New event games step, inside a component with chips() and pick(). --}}
<div x-show="picked.length > 0" x-cloak class="flex flex-wrap items-center gap-2 border-t border-line-soft pt-3">
    <span class="text-sm font-semibold text-muted">{{ __('games.selected') }}</span>
    <template x-for="chip in chips()" :key="chip.id">
        <span class="inline-flex items-center gap-1 rounded-full bg-subtle py-1 pr-1 pl-2.5 text-[13px] font-bold text-on-subtle">
            <span x-text="chip.number + '. ' + chip.title"></span>
            <button type="button" class="flex size-6 items-center justify-center rounded-full hover:bg-line" @click="pick(chip.id, chip.title)"
                :aria-label="{{ \Illuminate\Support\Js::from(__('games.unpick', ['title' => '__T__'])) }}.replace('__T__', chip.title)"><x-icon name="close" class="size-3.5" /></button>
        </span>
    </template>
</div>
