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
        }));
}

/** Gap between the row button and the menu, in px. */
const GAP = 4;

export function rowMenuStore() {
    return {
        spec: [],
        open: false,
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
            this.button = button;
            this.name = button.dataset.name ?? '';
            this.items = menuItems(this.spec, button.dataset);
            // Fixed to the viewport, right-aligned with the button.
            this.position = { top: box.bottom + GAP, right: view.innerWidth - box.right };
            this.open = true;
        },

        close() {
            this.open = false;
            this.button = null;
        },
    };
}
