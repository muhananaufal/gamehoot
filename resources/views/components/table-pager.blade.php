{{-- U4: the foot of a host list inside a tableView: which rows show, rows per page, and paging.
     Hidden while nothing matches (the list shows its own "no match" line then). --}}
<div {{ $attributes->merge(['class' => 'flex flex-wrap items-center justify-between gap-3 border-t border-line-soft px-6 py-3 text-sm text-muted']) }} x-show="shown > 0" x-cloak>
    <p aria-live="polite" x-text="@js(__('ui.showing')).replace('__S__', start + 1).replace('__E__', end).replace('__T__', shown)"></p>
    <div class="flex flex-wrap items-center gap-3">
        <div class="flex items-center gap-2" role="group" aria-label="{{ __('ui.rows_per_page') }}">
            <span aria-hidden="true">{{ __('ui.rows_per_page') }}</span>
            <div class="inline-flex gap-1 rounded-control border border-line-strong bg-field p-1">
                <template x-for="option in sizes" :key="option">
                    <button type="button" @click="size = option" :aria-pressed="(size === option).toString()" x-text="option"
                        class="flex h-9 min-w-9 items-center justify-center rounded-[8px] px-2 font-semibold tabular-nums"
                        :class="size === option ? 'bg-inverse text-on-inverse' : 'text-muted hover:bg-subtle hover:text-ink'"></button>
                </template>
            </div>
        </div>
        <div class="flex items-center gap-1">
            <button type="button" @click="previous()" :disabled="page <= 1" aria-label="{{ __('ui.previous_page') }}" title="{{ __('ui.previous_page') }}"
                class="flex size-11 items-center justify-center rounded-control text-ink hover:bg-subtle disabled:opacity-40">
                <x-icon name="chevron-left" />
            </button>
            <span class="min-w-20 text-center tabular-nums" x-text="@js(__('ui.page_of')).replace('__P__', page).replace('__N__', pages)"></span>
            <button type="button" @click="next()" :disabled="page >= pages" aria-label="{{ __('ui.next_page') }}" title="{{ __('ui.next_page') }}"
                class="flex size-11 items-center justify-center rounded-control text-ink hover:bg-subtle disabled:opacity-40">
                <x-icon name="chevron-right" />
            </button>
        </div>
    </div>
</div>
