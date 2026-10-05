// U9: drag a list item to a new place. The new order is sent with the page's normal reorder
// form, so the server checks it as before; the up and down buttons stay for keyboards and
// screen readers.

/** A copy of ids with the item at `from` moved to `to`; out-of-range moves change nothing. */
export function moveItem(ids, from, to) {
    if (from === to || from < 0 || to < 0 || from >= ids.length || to >= ids.length) {
        return [...ids];
    }

    const next = [...ids];
    const [item] = next.splice(from, 1);
    next.splice(to, 0, item);

    return next;
}

/** U14: calls `save` with the latest arguments once the calls have stopped for `ms`. */
export function saveLater(save, ms) {
    let timer = null;

    return (...args) => {
        clearTimeout(timer);
        timer = setTimeout(() => save(...args), ms);
    };
}

/** U14: how long the arrows wait after the last click before the order is saved. */
const SAVE_AFTER_MS = 900;

/**
 * The Alpine component around an <ol> of items with data-id. `submit` sends the new order
 * (the page puts it into its reorder form).
 */
export function sortableList() {
    return {
        from: null,
        over: null,

        ids() {
            return [...this.$root.querySelectorAll('[data-id]')].map((item) => item.dataset.id);
        },

        start(event, index) {
            this.from = index;
            event.dataTransfer.effectAllowed = 'move';
            // Firefox only starts a drag that carries data.
            event.dataTransfer.setData('text/plain', String(index));
        },

        enter(index) {
            if (this.from !== null) {
                this.over = index;
            }
        },

        drop(index) {
            const from = this.from;
            this.from = null;
            this.over = null;

            if (from === null || from === index) {
                return;
            }

            this.send(moveItem(this.ids(), from, index));
        },

        send(order) {
            const form = this.$refs.orderForm;
            form.querySelectorAll('input[name="order[]"]').forEach((input) => input.remove());

            for (const id of order) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'order[]';
                input.value = id;
                form.appendChild(input);
            }

            // U14: a failed save reloads the panel from the server, back to the saved order.
            if (window.up) {
                window.up.submit(form).catch(() => {
                    this.$store.toasts?.push(this.$el.dataset.saveFailed ?? '');
                    window.up.reload('#pack-panel');
                });
            } else {
                form.requestSubmit();
            }
        },

        /**
         * U14: an arrow moves its row at once (the list slides, x-flip) and renumbers the rows;
         * the order is saved once, shortly after the last click.
         */
        nudge(button, delta) {
            const item = button.closest('[data-id]');
            const list = item.parentElement;
            const items = [...list.querySelectorAll(':scope > [data-id]')];
            const target = items.indexOf(item) + delta;

            if (target < 0 || target >= items.length) {
                return;
            }

            list.insertBefore(item, delta < 0 ? items[target] : items[target].nextSibling);
            this.renumber(list);
            button.focus();
            this.saveSoon ??= saveLater((order) => this.send(order), SAVE_AFTER_MS);
            this.saveSoon(this.ids());
        },

        renumber(list) {
            const items = [...list.querySelectorAll(':scope > [data-id]')];

            items.forEach((item, index) => {
                const number = item.querySelector('[data-number]');

                if (number) {
                    number.textContent = String(index + 1);
                }

                item.querySelector('[data-move="up"]')?.toggleAttribute('disabled', index === 0);
                item.querySelector('[data-move="down"]')?.toggleAttribute('disabled', index === items.length - 1);
            });
        },

        end() {
            this.from = null;
            this.over = null;
        },
    };
}
