{{-- U5: the single "⋯" menu of a host page (resources/js/row-actions.js), placed next to the row
     button that opened it. Links go straight to their page; other actions ask for confirmation. --}}
<div x-data x-show="$store.rowMenu.open" x-cloak role="menu" :aria-label="$store.rowMenu.name"
    @click.outside="$store.rowMenu.button?.contains($event.target) || $store.rowMenu.close()"
    @keydown.escape.window="if ($store.rowMenu.open) { $store.rowMenu.button?.focus(); $store.rowMenu.close() }"
    @scroll.window.passive="$store.rowMenu.close()" @resize.window="$store.rowMenu.close()"
    :style="`top: ${$store.rowMenu.position.top}px; right: ${$store.rowMenu.position.right}px`"
    x-transition:enter="menu-enter" x-transition:enter-start="menu-from" x-transition:enter-end="menu-to"
    class="fixed z-40 flex min-w-48 flex-col rounded-card border border-line bg-surface p-1.5 text-sm font-semibold shadow-xl">
    <template x-for="item in $store.rowMenu.items" :key="item.key">
        <div class="contents">
            <template x-if="item.type === 'link'">
                <a role="menuitem" :href="item.url" x-text="item.label" class="flex min-h-11 items-center rounded-control px-3 hover:bg-subtle"></a>
            </template>
            <template x-if="item.type === 'confirm'">
                <button type="button" role="menuitem" x-text="item.label"
                    @click="$store.confirm.askFor(item.key, $store.rowMenu.name, item.url); $store.rowMenu.close()"
                    class="flex min-h-11 w-full items-center rounded-control px-3 text-left hover:bg-subtle" :class="item.danger && 'text-on-danger'"></button>
            </template>
        </div>
    </template>
</div>
