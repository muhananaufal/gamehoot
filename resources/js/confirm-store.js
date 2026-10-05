// U5: one confirmation dialog per page (x-confirm-host). A row asks for it with what to say and
// which form to send, instead of rendering its own dialog: 500 rows used to mean 500 dialogs.

export function confirmStore() {
    return {
        open: false,
        title: '',
        body: '',
        action: '',
        method: 'POST',
        confirmLabel: '',
        tone: 'primary',
        /** One field to fill in (e.g. rename): {name, label, maxlength, value, error}, or null. */
        field: null,

        ask({ title, body = '', action, method = 'POST', confirmLabel, tone = 'primary', field = null }) {
            Object.assign(this, { title, body, action, method, confirmLabel, tone, field, open: true });
        },

        /** Texts per kind of action ({title, body, label, method, tone}, with __N__ for the name), set once by the page. */
        texts: {},

        askFor(kind, name, action) {
            const text = this.texts[kind];

            if (!text) {
                return;
            }

            this.ask({
                title: text.title.replace('__N__', name),
                body: (text.body ?? '').replace('__N__', name),
                action,
                method: text.method,
                confirmLabel: text.label,
                tone: text.tone,
            });
        },

        /**
         * A row button with data-ask (its kind), data-id and data-name. The texts of that kind
         * carry the address template (__P__ for the id) and, for rename, the field. `previous`
         * reopens the dialog with what was sent and the server's error.
         */
        askRow(button, previous = {}) {
            const { ask: kind, id = '', name = '' } = button.dataset;
            const text = this.texts[kind];

            if (!text) {
                return;
            }

            this.ask({
                title: text.title.replace('__N__', name),
                body: (text.body ?? '').replace('__N__', name),
                action: (text.url ?? '').replace('__P__', id),
                method: text.method,
                confirmLabel: text.label,
                tone: text.tone,
                field: text.field
                    ? { ...text.field, value: previous.value ?? name, error: previous.error ?? '' }
                    : null,
            });
        },

        close() {
            this.open = false;
        },
    };
}
