{{-- U4: the foot of a host list inside a tableView: which rows show, and numbered pages
     (1 … 4 5 6 … 42) with Previous and Next. Rows per page sit above the table (x-table.toolbar).
     A long list (more than 7 pages) also takes a page number to go to.
     Hidden while nothing matches (the list shows its own "no match" line then). --}}
<div {{ $attributes->merge(['class' => 'flex flex-wrap items-center justify-between gap-3 border-t border-line-soft px-6 py-3 text-body-s text-muted']) }} x-show="shown > 0" x-cloak>
    <p aria-live="polite" x-text="rangeText(@js(__('ui.showing')))"></p>
    <div class="flex flex-wrap items-center gap-3">
        <nav x-show="pages > 1" class="flex items-center gap-1" aria-label="{{ __('ui.pages') }}">
            <button type="button" @click="previous()" :disabled="page <= 1" aria-label="{{ __('ui.previous_page') }}" title="{{ __('ui.previous_page') }}"
                class="flex size-11 items-center justify-center rounded-control text-ink hover:bg-subtle disabled:opacity-40">
                <x-icon name="chevron-left" />
            </button>
            <template x-for="(item, index) in pageNumbers" :key="index">
                <span class="flex">
                    <template x-if="item === '…'">
                        <span class="flex size-11 items-center justify-center" aria-hidden="true">…</span>
                    </template>
                    <template x-if="item !== '…'">
                        <button type="button" @click="page = item" x-text="item.toLocaleString('en-US')" :aria-current="item === page ? 'page' : null"
                            :aria-label="@js(__('ui.page_number')).replace('__P__', item)"
                            class="flex h-11 min-w-11 items-center justify-center rounded-control px-2 font-semibold tabular-nums"
                            :class="item === page ? 'bg-inverse text-on-inverse' : 'text-ink hover:bg-subtle'"></button>
                    </template>
                </span>
            </template>
            <button type="button" @click="next()" :disabled="page >= pages" aria-label="{{ __('ui.next_page') }}" title="{{ __('ui.next_page') }}"
                class="flex size-11 items-center justify-center rounded-control text-ink hover:bg-subtle disabled:opacity-40">
                <x-icon name="chevron-right" />
            </button>
        </nav>
        <label x-show="pages > 7" class="flex items-center gap-2">
            <span>{{ __('ui.go_to_page') }}</span>
            <input type="number" min="1" :max="pages" inputmode="numeric" enterkeyhint="go" :placeholder="'1–' + pages"
                @keydown.enter.prevent="goTo($el.value); $el.value = ''" @change="goTo($el.value); $el.value = ''"
                class="h-11 w-20 rounded-control border border-field-line bg-field px-3 text-ink tabular-nums">
        </label>
    </div>
</div>
