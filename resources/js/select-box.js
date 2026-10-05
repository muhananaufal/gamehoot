// U22: the dropdown of the host panel, instead of the browser's <select>: themed, with the arrow
// lined up with the padding, and the W3C APG select-only combobox keys (arrows, Home, End,
// Enter, Escape, type a letter to jump). A search box shows when there are more than seven
// choices. Bound to a parent with x-model (x-modelable="value"), or sent with a form through a
// hidden input.

const SEARCH_FROM = 8;

export function filterOptions(options, q) {
    const words = q.trim().toLowerCase().split(/\s+/).filter(Boolean);

    return options.filter((option) => words.every((word) => String(option.label).toLowerCase().includes(word)));
}

export function moveActive(index, key, length) {
    switch (key) {
        case 'ArrowDown':
            return Math.min(index + 1, length - 1);
        case 'ArrowUp':
            return Math.max(index - 1, 0);
        case 'Home':
            return 0;
        case 'End':
            return length - 1;
        default:
            return index;
    }
}

/** The next choice after `from` whose label starts with the typed letter; else stays. */
export function typeAhead(options, letter, from) {
    const lower = letter.toLowerCase();

    for (let step = 1; step <= options.length; step++) {
        const index = (from + step) % options.length;

        if (String(options[index].label).toLowerCase().startsWith(lower)) {
            return index;
        }
    }

    return from;
}

export function selectBox({ options, value }) {
    return {
        options,
        value,
        open: false,
        q: '',
        active: 0,

        get searchable() {
            return this.options.length >= SEARCH_FROM;
        },

        get visible() {
            return filterOptions(this.options, this.q);
        },

        get selected() {
            return this.options.find((option) => option.value === this.value) ?? null;
        },

        get selectedLabel() {
            const option = this.selected;

            if (!option) {
                return '';
            }

            return option.count === undefined ? String(option.label) : `${option.label} · ${option.count}`;
        },

        toggle() {
            if (this.open) {
                this.close();
            } else {
                this.show();
            }
        },

        show() {
            this.q = '';
            this.active = Math.max(
                0,
                this.visible.findIndex((option) => option.value === this.value),
            );
            this.open = true;
            this.$nextTick?.(() => (this.searchable ? this.$refs.search?.focus() : this.$refs.list?.focus()));
        },

        close(focusButton = false) {
            this.open = false;

            if (focusButton) {
                this.$refs?.button?.focus();
            }
        },

        choose(option) {
            this.value = option.value;
            this.close(true);
        },

        onKey(event) {
            const key = event.key;

            if (!this.open) {
                if (['ArrowDown', 'ArrowUp', 'Enter', ' '].includes(key)) {
                    event.preventDefault();
                    this.show();
                }

                return;
            }

            if (key === 'Escape') {
                event.preventDefault();
                this.close(true);
            } else if (key === 'Enter') {
                event.preventDefault();
                const option = this.visible[this.active];

                if (option) {
                    this.choose(option);
                }
            } else if (['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(key)) {
                event.preventDefault();
                this.active = moveActive(this.active, key, this.visible.length);
                this.$nextTick?.(() =>
                    this.$refs.list
                        ?.querySelector(`[data-index="${this.active}"]`)
                        ?.scrollIntoView({ block: 'nearest' }),
                );
            } else if (!this.searchable && key.length === 1) {
                this.active = typeAhead(this.visible, key, this.active);
            } else if (key === 'Tab') {
                this.close();
            }
        },
    };
}
