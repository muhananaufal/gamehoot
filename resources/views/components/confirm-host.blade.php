{{-- U5: the single confirmation dialog of a host page (resources/js/confirm-store.js). Rows call
     $store.confirm.ask({...}); the native <dialog> traps focus and closes on Escape. --}}
<dialog x-data x-effect="$store.confirm.open ? ($el.open || $el.showModal()) : ($el.open && $el.close())"
    @close="$store.confirm.close()" aria-labelledby="confirm-title"
    class="m-auto w-[min(420px,calc(100vw-2rem))] rounded-panel bg-surface p-0 text-ink shadow-2xl">
    <div class="flex flex-col gap-3 p-5.5">
        <h2 id="confirm-title" class="font-display text-[22px] font-bold" x-text="$store.confirm.title"></h2>
        <p class="text-[15px] text-muted" x-show="$store.confirm.body !== ''" x-text="$store.confirm.body"></p>
        {{-- U18: the dialog is not part of the page Unpoly replaces, so it closes itself once sent. --}}
        <form method="POST" :action="$store.confirm.action" class="flex flex-col gap-3" @submit="$nextTick(() => $store.confirm.close())">
            @csrf
            <template x-if="$store.confirm.method !== 'POST'">
                <input type="hidden" name="_method" :value="$store.confirm.method">
            </template>
            <template x-for="[name, value] in Object.entries($store.confirm.data)" :key="name">
                <input type="hidden" :name="name" :value="value">
            </template>
            {{-- U5: one field to fill in (rename), reopened with the server's error when refused. --}}
            <template x-if="$store.confirm.field">
                <div class="flex flex-col gap-1.5">
                    <template x-if="$store.confirm.field.form">
                        <input type="hidden" name="form" :value="$store.confirm.field.form">
                    </template>
                    <label for="confirm-field" class="text-sm font-semibold" x-text="$store.confirm.field.label"></label>
                    <input id="confirm-field" :name="$store.confirm.field.name" x-model="$store.confirm.field.value" :maxlength="$store.confirm.field.maxlength"
                        required autocomplete="off" x-init="$nextTick(() => $el.select())"
                        :aria-invalid="$store.confirm.field.error ? 'true' : null" :aria-describedby="$store.confirm.field.error ? 'confirm-field-error' : null"
                        class="h-11 rounded-control border bg-field px-3 text-[15px] text-ink"
                        :class="$store.confirm.field.error ? 'border-on-danger' : 'border-line-strong'">
                    <p id="confirm-field-error" x-show="$store.confirm.field.error" class="text-sm font-semibold text-on-danger" x-text="$store.confirm.field.error"></p>
                </div>
            </template>
            <div class="flex justify-end gap-2.5 pt-1.5">
            <x-button variant="secondary" type="button" @click="$el.closest('dialog').close()">{{ __('ui.cancel') }}</x-button>
            <button type="submit" x-text="$store.confirm.confirmLabel"
                class="inline-flex h-11 items-center justify-center rounded-control px-4 text-sm font-bold whitespace-nowrap transition-colors"
                :class="$store.confirm.tone === 'destructive' ? 'bg-destructive text-on-destructive hover:opacity-90 [--busy-ink:var(--color-on-destructive)]' : 'bg-accent text-on-accent hover:bg-accent-strong [--busy-ink:var(--color-on-accent)]'"></button>
            </div>
        </form>
    </div>
</dialog>
