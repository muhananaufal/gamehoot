// U4: search, filter, sort and pages of host lists in the browser, without reloading. The server
// sends every row; each row carries data-search, data-filter, data-date (YYYY-MM-DD in WIB) and
// data-sort-<key>. The view state is kept in localStorage per list, so the URL stays clean and the
// view survives a refresh and a live update (U12).

const DIRECTIONS = ['asc', 'desc'];
export const PAGE_SIZES = [10, 25, 50, 100];
const DEFAULT_SIZE = 25;
const PREFIX = 'pentahoot-table:';
const DATE = /^\d{4}-\d{2}-\d{2}$/;

/** The full state for a list, starting from its defaults and keeping only known stored values. */
export function loadState(storage, key, defaults) {
    let stored = {};

    try {
        stored = JSON.parse(storage.getItem(PREFIX + key) ?? '{}') ?? {};
    } catch {
        // Blocked storage or a broken value: start from the defaults.
    }

    const text = (value) => (typeof value === 'string' ? value : '');
    const page = Number.isInteger(stored.page) && stored.page > 0 ? stored.page : 1;

    return {
        q: text(stored.q),
        filter: text(stored.filter) || 'all',
        sort: text(stored.sort) || defaults.sort,
        dir: DIRECTIONS.includes(stored.dir) ? stored.dir : defaults.dir,
        from: DATE.test(text(stored.from)) ? stored.from : '',
        to: DATE.test(text(stored.to)) ? stored.to : '',
        page,
        size: PAGE_SIZES.includes(stored.size) ? stored.size : DEFAULT_SIZE,
    };
}

/** Stores what differs from the defaults; a list back at its defaults leaves nothing behind. */
export function saveState(storage, key, state, defaults) {
    const base = {
        q: '',
        filter: 'all',
        sort: defaults.sort,
        dir: defaults.dir,
        from: '',
        to: '',
        page: 1,
        size: DEFAULT_SIZE,
    };
    const changed = Object.fromEntries(
        Object.entries(base)
            .map(([name, fallback]) => [name, name === 'q' ? state.q.trim() : state[name], fallback])
            .filter(([, value, fallback]) => value !== fallback)
            .map(([name, value]) => [name, value]),
    );

    try {
        if (Object.keys(changed).length === 0) {
            storage.removeItem(PREFIX + key);
        } else {
            storage.setItem(PREFIX + key, JSON.stringify(changed));
        }
    } catch {
        // Blocked storage: the view works, it just is not remembered.
    }
}

/** Whether anything narrows the list (sort order and page size do not). */
export function filtersActive(state) {
    return state.q.trim() !== '' || state.filter !== 'all' || state.from !== '' || state.to !== '';
}

export function matchesRow(row, { q, filter, from = '', to = '' }) {
    const words = q.trim().toLowerCase().split(/\s+/).filter(Boolean);
    const text = row.search.toLowerCase();
    const date = row.date ?? '';
    const inRange =
        (from === '' && to === '') || (date !== '' && (from === '' || date >= from) && (to === '' || date <= to));

    return (filter === 'all' || row.filter === filter) && inRange && words.every((word) => text.includes(word));
}

/** The rows of one page; the page is kept inside the list when rows disappear. */
export function pageOf(total, page, size) {
    const pages = Math.max(1, Math.ceil(total / size));
    const current = Math.min(Math.max(1, page), pages);
    const start = (current - 1) * size;

    return { page: current, pages, start, end: Math.min(start + size, total) };
}

const collator = new Intl.Collator(undefined, { numeric: true, sensitivity: 'base' });

/** Empty values always go last, so rows without a date never push real ones down. */
export function compareRows(a, b, key, dir) {
    const x = a.sort[key] ?? '';
    const y = b.sort[key] ?? '';

    if (x === '' || y === '') {
        return x === y ? 0 : x === '' ? 1 : -1;
    }

    const order = collator.compare(x, y);

    return dir === 'desc' ? -order : order;
}

/**
 * The Alpine component around a <table>. Rows are the <tr data-row> of its <tbody>. `key` names
 * the list in localStorage, e.g. "names:<event id>".
 */
export function tableView({ key, sort, dir = 'asc', copied = '', copyBase = '', storage = globalThis.localStorage }) {
    const defaults = { sort, dir };

    return {
        ...loadState(storage, key, defaults),
        sizes: PAGE_SIZES,
        shown: 0,
        pages: 1,
        start: 0,
        end: 0,

        get active() {
            return filtersActive(this);
        },

        /**
         * U5: one listener for every row button. [data-menu] opens the page's row menu;
         * [data-copy] copies its text and confirms with a toast (U3).
         */
        onRowClick(event) {
            const menu = event.target.closest('[data-menu]');

            if (menu) {
                this.$store.rowMenu.openFrom(menu);

                return;
            }

            const copy = event.target.closest('[data-copy]');

            if (copy) {
                navigator.clipboard
                    .writeText(copyBase + copy.dataset.copy)
                    .then(() => this.$store.toasts.push(copied.replace('__N__', copy.dataset.name ?? '')));
            }
        },

        init() {
            this.apply();
            // A new search, filter, order or page size starts again at page 1.
            this.$watch(
                () => [this.q, this.filter, this.sort, this.dir, this.from, this.to, this.size].join('|'),
                () => {
                    this.page = 1;
                    this.apply();
                },
            );
            this.$watch('page', () => this.apply());
        },

        rows() {
            return [...this.$root.querySelectorAll('tr[data-row]')];
        },

        sortBy(name) {
            this.dir = this.sort === name && this.dir === 'asc' ? 'desc' : 'asc';
            this.sort = name;
        },

        ariaSort(name) {
            if (this.sort !== name) {
                return 'none';
            }

            return this.dir === 'asc' ? 'ascending' : 'descending';
        },

        reset() {
            Object.assign(this, { q: '', filter: 'all', from: '', to: '' });
        },

        previous() {
            this.page = Math.max(1, this.page - 1);
        },

        next() {
            this.page = Math.min(this.pages, this.page + 1);
        },

        apply() {
            const state = { q: this.q, filter: this.filter, from: this.from, to: this.to };
            const view = this.rows().map((el) => ({
                el,
                search: el.dataset.search ?? '',
                filter: el.dataset.filter ?? '',
                date: el.dataset.date ?? '',
                sort: Object.fromEntries(
                    Object.entries(el.dataset)
                        .filter(([name]) => name.startsWith('sort'))
                        .map(([name, value]) => [name.slice(4).toLowerCase(), value]),
                ),
            }));

            view.sort((a, b) => compareRows(a, b, this.sort, this.dir));

            const matching = view.filter((row) => matchesRow(row, state));
            const slice = pageOf(matching.length, this.page, this.size);
            const onPage = new Set(matching.slice(slice.start, slice.end));

            for (const row of view) {
                row.el.hidden = !onPage.has(row);
                row.el.parentElement.appendChild(row.el);
            }

            Object.assign(this, { shown: matching.length, pages: slice.pages, start: slice.start, end: slice.end });

            if (this.page !== slice.page) {
                this.page = slice.page;
            }

            saveState(storage, key, this, defaults);
        },
    };
}
