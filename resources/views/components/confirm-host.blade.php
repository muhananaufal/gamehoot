{{-- U5: the single confirmation dialog of a host page (resources/js/confirm-store.js). Rows call
     $store.confirm.ask({...}); the native <dialog> traps focus and closes on Escape. --}}
<dialog x-data x-effect="$store.confirm.open ? ($el.open || $el.showModal()) : ($el.open && $el.close())"
    @close="$store.confirm.close()" aria-labelledby="confirm-title"
    class="m-auto w-[min(420px,calc(100vw-2rem))] rounded-panel bg-surface p-0 text-ink shadow-2xl backdrop:bg-nav/70">
    <div class="flex flex-col gap-3 p-5.5">
        <h2 id="confirm-title" class="font-display text-[22px] font-bold" x-text="$store.confirm.title"></h2>
        <p class="text-[15px] text-muted" x-show="$store.confirm.body !== ''" x-text="$store.confirm.body"></p>
        <form method="POST" :action="$store.confirm.action" class="flex justify-end gap-2.5 pt-1.5">
            @csrf
            <template x-if="$store.confirm.method !== 'POST'">
                <input type="hidden" name="_method" :value="$store.confirm.method">
            </template>
            <x-button variant="secondary" type="button" @click="$el.closest('dialog').close()">{{ __('ui.cancel') }}</x-button>
            <button type="submit" x-text="$store.confirm.confirmLabel"
                class="inline-flex h-11 items-center justify-center rounded-control px-4 text-sm font-bold whitespace-nowrap transition-colors"
                :class="$store.confirm.tone === 'destructive' ? 'bg-destructive text-on-destructive hover:opacity-90 [--busy-ink:var(--color-on-destructive)]' : 'bg-accent text-on-accent hover:bg-accent-strong [--busy-ink:var(--color-on-accent)]'"></button>
        </form>
    </div>
</dialog>
