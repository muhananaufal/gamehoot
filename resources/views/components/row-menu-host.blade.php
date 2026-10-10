{{-- U5: the single "⋯" menu of a host page (resources/js/row-actions.js), placed next to the row
     button that opened it. Links go straight to their page (a modal over the list when the
     page says so, U13); other actions ask for confirmation. C-7: an action the row marks "off"
     shows switched off, with its reason. --}}
{{-- Audit FE-3, W3C APG menu button: focus moves to the first item when the menu opens; the arrows,
     Home and End move between items, Escape goes back to the row button, Tab leaves from there. --}}
<div x-data="rowMenuHost" x-show="$store.rowMenu.open" x-cloak role="menu" :aria-label="$store.rowMenu.name"
    x-effect="$store.rowMenu.open && focusFirst()" @keydown="key($event)"
    @click.outside="$store.rowMenu.button?.contains($event.target) || $store.rowMenu.close()"
    @keydown.escape.window="if ($store.rowMenu.open) { $store.rowMenu.button?.focus(); $store.rowMenu.close() }"
    @scroll.window.passive="$store.rowMenu.close()" @resize.window="$store.rowMenu.close()"
    :style="`top: ${$store.rowMenu.position.top}px; right: ${$store.rowMenu.position.right}px`"
    x-transition:enter="menu-enter" x-transition:enter-start="menu-from" x-transition:enter-end="menu-to"
    class="fixed z-40 flex min-w-48 flex-col rounded-card border border-line bg-surface p-1.5 text-body-s font-semibold shadow-xl">
    <template x-for="item in $store.rowMenu.items" :key="item.key">
        <div class="contents">
            <template x-if="item.type === 'link'">
                <a role="menuitem" :href="item.url" x-text="item.label" @click="$store.rowMenu.close()"
                    x-init="item.modal && $nextTick(() => window.up?.link.preload($el).catch(() => {}))"
                    :up-layer="item.modal ? 'new modal' : null" :up-history="item.modal ? 'false' : null"
                    :up-accept-location="item.accept ?? null" :up-size="item.modal ? 'medium' : null"
                    class="flex min-h-11 items-center rounded-control px-3 hover:bg-subtle"></a>
            </template>
            <template x-if="item.type === 'confirm' && ! item.disabled">
                <button type="button" role="menuitem" x-text="item.label"
                    @click="$store.confirm.askFor(item.key, $store.rowMenu.name, item.url, $store.rowMenu.id); $store.rowMenu.close()"
                    class="flex min-h-11 w-full items-center rounded-control px-3 text-left hover:bg-subtle" :class="item.danger && 'text-on-danger'"></button>
            </template>
            <template x-if="item.type === 'confirm' && item.disabled">
                <button type="button" role="menuitem" disabled aria-disabled="true" :title="item.reason"
                    class="flex min-h-11 w-full cursor-not-allowed flex-col items-start justify-center rounded-control px-3 py-1.5 text-left opacity-60">
                    <span x-text="item.label"></span>
                    <span class="max-w-60 text-caption font-normal text-muted" x-text="item.reason"></span>
                </button>
            </template>
        </div>
    </template>
</div>
