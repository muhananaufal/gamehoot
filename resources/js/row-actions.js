// U5: the "⋯" menu of table rows, rendered once per page (x-row-menu-host) instead of once per
// row. A row button carries the row name and one URL per action (data-rename, data-delete, ...);
// the page sets the list of possible actions once ($store.rowMenu.spec).

/**
 * An action with a url template (__P__ for the row id) only needs a flag on the row; otherwise
 * the row carries the full address.
 *
 * @param {{key: string, label: string, type: 'link'|'confirm', danger?: boolean, url?: string}[]} spec
 * @param {Record<string, string>} dataset
 */
export function menuItems(spec, dataset) {
    return spec
        .filter((action) => (dataset[action.key] ?? '') !== '')
        .map((action) => ({
            ...action,
            danger: action.danger === true,
            url: action.url ? action.url.replace('__P__', dataset.id ?? '') : dataset[action.key],
            // C-7: "off" shows the action switched off, with the reason from the page.
            ...(dataset[action.key] === 'off' ? { disabled: true } : {}),
        }));
}

/** Gap between the row button and the menu, in px. */
const GAP = 4;

/**
 * Audit FE-3, W3C APG menu: the item a key moves to, or null when the key does not move. The
 * arrows wrap around; Home and End go to the ends.
 *
 * @param {string} key
 * @param {number} index the item that has focus, -1 when none
 * @param {number} count
 */
export function menuKeyTarget(key, index, count) {
    if (count === 0) {
        return null;
    }

    return (
        {
            ArrowDown: (index + 1) % count,
            ArrowUp: (index - 1 + count) % count,
            Home: 0,
            End: count - 1,
        }[key] ?? null
    );
}

/**
 * The Alpine component of the shared menu (components/row-menu-host.blade.php): focus moves
 * into the menu when it opens and the keys follow the APG menu pattern.
 */
export function rowMenuHost() {
    return {
        items() {
            return [...this.$el.querySelectorAll('[role=menuitem]:not([disabled])')];
        },

        focusFirst() {
            this.$nextTick(() => this.items()[0]?.focus());
        },

        key(event) {
            const items = this.items();

            if (event.key === 'Tab') {
                // Back on the row button, so the browser's own Tab continues from there.
                const button = this.$store.rowMenu.button;
                this.$store.rowMenu.close();
                button?.focus();

                return;
            }

            // Space activates a link item like a button (a link alone would scroll the page).
            if (event.key === ' ' && document.activeElement?.tagName === 'A') {
                event.preventDefault();
                document.activeElement.click();

                return;
            }

            const next = menuKeyTarget(event.key, items.indexOf(document.activeElement), items.length);

            if (next !== null) {
                event.preventDefault();
                items[next].focus();
            }
        },
    };
}

export function rowMenuStore() {
    return {
        spec: [],
        open: false,
        id: '',
        name: '',
        items: [],
        position: { top: 0, right: 0 },
        button: null,

        openFrom(button, view = window) {
            if (this.open && this.button === button) {
                this.close();

                return;
            }

            const box = button.getBoundingClientRect();
            // A light row (U5) carries its id and name once, on the row.
            const data = { ...(button.closest?.('[data-row]')?.dataset ?? {}), ...button.dataset };
            this.button = button;
            this.id = data.id ?? '';
            this.name = data.name ?? '';
            this.items = menuItems(this.spec, data);
            // Fixed to the viewport, right-aligned with the button.
            this.position = { top: box.bottom + GAP, right: view.innerWidth - box.right };
            this.open = true;
            button.setAttribute?.('aria-expanded', 'true');
        },

        close() {
            this.button?.setAttribute?.('aria-expanded', 'false');
            this.open = false;
            this.button = null;
        },
    };
}
