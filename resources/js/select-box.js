// U22: the dropdown of the host panel, instead of the browser's <select>: themed, with the arrow
// lined up with the padding, and the W3C APG select-only combobox keys (arrows, Home, End,
// Enter, Escape, type a letter to jump). A search box shows when there are more than seven
// choices. Bound to a parent with x-model (x-modelable="value"), or sent with a form through a
// hidden input.

const SEARCH_FROM = 8;
/** The widest a list usually gets; a button nearer the right edge opens its list leftwards. */
const LIST_WIDTH = 240;
const GAP = 4;

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

/**
 * `placeholder` labels the button while nothing is chosen. `clearable`: choosing the chosen value
 * again empties the dropdown (D-11, the game of Replay on screen).
 */
export function selectBox({ options, value, placeholder = '', clearable = false, view = globalThis.window }) {
    // `view` stays out of the component data: Alpine would make the whole window reactive.

    return {
        options,
        value,
        placeholder,
        clearable,
        open: false,
        q: '',
        active: 0,
        // Fixed to the screen under the button, so a scrolling box (the pack list) never cuts
        // the list off; near the right edge it lines up with the button's right edge.
        position: { top: 0, left: 0, right: null, width: 0 },

        get searchable() {
            return this.options.length >= SEARCH_FROM;
        },

        get visible() {
            return filterOptions(this.options, this.q);
        },

        get selected() {
            return this.options.find((option) => option.value === this.value) ?? null;
        },

        get empty() {
            return this.selected === null;
        },

        get selectedLabel() {
            const option = this.selected;

            if (!option) {
                return this.placeholder;
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
            const box = this.$refs.button.getBoundingClientRect();
            const fromRight = box.left + LIST_WIDTH > view.innerWidth;
            this.position = {
                top: box.bottom + GAP,
                left: fromRight ? null : box.left,
                right: fromRight ? view.innerWidth - box.right : null,
                width: box.width,
            };
            this.q = '';
            this.active = Math.max(
                0,
                this.visible.findIndex((option) => option.value === this.value),
            );
            this.open = true;
            // preventScroll: a scroll would close the list again (it closes on page scroll).
            this.$nextTick?.(() =>
                (this.searchable ? this.$refs.search : this.$refs.list)?.focus({ preventScroll: true }),
            );
        },

        close(focusButton = false) {
            this.open = false;

            if (focusButton) {
                this.$refs?.button?.focus();
            }
        },

        choose(option) {
            this.value = this.clearable && option.value === this.value ? null : option.value;
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
