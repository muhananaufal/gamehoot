{{-- U3: confirmations as toasts (resources/js/toasts.js), bottom right on wide screens and top
     center on phones. The saved message of the last request becomes the first toast. Errors and
     validation are never shown here. --}}
<div id="toasts" up-hungry x-data @if (session('status')) x-init="$store.toasts.push(@js(session('status')))" @endif
    aria-live="polite" aria-atomic="false"
    class="pointer-events-none fixed inset-x-4 top-4 z-50 flex flex-col items-center gap-2 sm:inset-x-auto sm:top-auto sm:right-6 sm:bottom-6 sm:items-end">
    <template x-for="toast in $store.toasts.items" :key="toast.id">
        <div role="status"
            x-transition:enter="toast-enter" x-transition:enter-start="toast-from" x-transition:enter-end="toast-to"
            x-transition:leave="toast-leave" x-transition:leave-start="toast-to" x-transition:leave-end="toast-from"
            @mouseenter="$store.toasts.hold(toast.id)" @mouseleave="$store.toasts.release(toast.id)"
            @focusin="$store.toasts.hold(toast.id)" @focusout="$store.toasts.release(toast.id)"
            class="pointer-events-auto flex w-full max-w-sm items-center gap-3 rounded-card bg-inverse py-2 pr-2 pl-4 text-body-s font-semibold text-on-inverse shadow-xl">
            <x-icon name="check" class="shrink-0" />
            <span class="grow" x-text="toast.message"></span>
            <button type="button" class="flex size-11 shrink-0 items-center justify-center rounded-control hover:opacity-80"
                @click="$store.toasts.dismiss(toast.id)" aria-label="{{ __('ui.close') }}">
                <x-icon name="close" />
            </button>
        </div>
    </template>
</div>
