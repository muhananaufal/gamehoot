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

            const order = moveItem(this.ids(), from, index);
            const form = this.$refs.orderForm;
            form.querySelectorAll('input[name="order[]"]').forEach((input) => input.remove());

            for (const id of order) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'order[]';
                input.value = id;
                form.appendChild(input);
            }

            form.requestSubmit();
        },

        end() {
            this.from = null;
            this.over = null;
        },
    };
}
