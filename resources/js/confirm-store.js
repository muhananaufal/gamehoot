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

        ask({ title, body = '', action, method = 'POST', confirmLabel, tone = 'primary' }) {
            Object.assign(this, { title, body, action, method, confirmLabel, tone, open: true });
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

        close() {
            this.open = false;
        },
    };
}
