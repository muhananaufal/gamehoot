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
        /** U4: hidden form values ({name: value}), so a choice never travels in the address. */
        data: {},

        ask({ title, body = '', action, method = 'POST', confirmLabel, tone = 'primary', field = null, data = {} }) {
            Object.assign(this, { title, body, action, method, confirmLabel, tone, field, data, open: true });
        },

        /** Texts per kind of action ({title, body, label, method, tone}, with __N__ for the name), set once by the page. */
        texts: {},

        /**
         * Asks for one kind of action on a row: `action` is its address and `id` the row id.
         * For rename, the texts carry the field; `previous` reopens the dialog with what was
         * sent and the server's error.
         */
        askFor(kind, name, action, id = '', previous = {}) {
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
                data: text.data ?? {},
                // field.form names the row in the request, so a refused rename can reopen it.
                field: text.field
                    ? {
                          ...text.field,
                          ...(text.field.form ? { form: text.field.form.replace('__P__', id) } : {}),
                          value: previous.value ?? name,
                          error: previous.error ?? '',
                      }
                    : null,
            });
        },

        /**
         * A row button with data-ask (its kind); the row carries data-id and data-name once for
         * all of its buttons. The texts of that kind carry the address template (__P__ for the id).
         */
        askRow(button, previous = {}) {
            const row = button.closest?.('[data-row]')?.dataset ?? {};
            const kind = button.dataset.ask;
            const id = button.dataset.id ?? row.id ?? '';
            const name = button.dataset.name ?? row.name ?? '';

            this.askFor(kind, name, (this.texts[kind]?.url ?? '').replace('__P__', id), id, previous);
        },

        close() {
            this.open = false;
        },
    };
}
