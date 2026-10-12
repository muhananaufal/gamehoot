// U3: short confirmations (saved, copied, deleted) as toasts that go away by themselves.
// Errors and validation never become toasts; they stay next to the field or button.

/** How long a toast stays, counted again from the start after the pointer or focus leaves it. */
export const TOAST_MS = 4_000;

/** Older toasts make room for new ones beyond this. */
const MAX_TOASTS = 3;

export function toastStore({ setTimer = setTimeout, clearTimer = clearTimeout } = {}) {
    let next = 1;
    const timers = new Map();

    return {
        items: [],

        push(message, tone = 'success') {
            const text = String(message ?? '').trim();

            if (text === '') {
                return null;
            }

            const id = next++;
            this.items = [...this.items, { id, message: text, tone }];

            while (this.items.length > MAX_TOASTS) {
                this.dismiss(this.items[0].id);
            }

            this.release(id);

            return id;
        },

        dismiss(id) {
            clearTimer(timers.get(id));
            timers.delete(id);
            this.items = this.items.filter((toast) => toast.id !== id);
        },

        /** Pointer or keyboard focus is on the toast: it waits. */
        hold(id) {
            clearTimer(timers.get(id));
            timers.delete(id);
        },

        /** The toast is left alone again: it gets its full time. */
        release(id) {
            if (!this.items.some((toast) => toast.id === id)) {
                return;
            }

            clearTimer(timers.get(id));
            timers.set(
                id,
                setTimer(() => this.dismiss(id), TOAST_MS),
            );
        },
    };
}
