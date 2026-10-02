// U4: search, filter and sort of host lists in the browser, without reloading. The server sends
// every row; each row carries data-search, data-filter and data-sort-<key>. The view state is
// written to the URL with history.replaceState, so Back, refresh and shared links keep it.

const DIRECTIONS = ['asc', 'desc'];

export function readState(search, defaults) {
    const params = new URLSearchParams(search);
    const dir = params.get('dir');

    return {
        q: params.get('q') ?? '',
        filter: params.get('filter') ?? 'all',
        sort: params.get('sort') ?? defaults.sort,
        dir: DIRECTIONS.includes(dir) ? dir : defaults.dir,
    };
}

export function writeState(href, state, defaults) {
    const url = new URL(href, 'http://local');
    const set = (key, value, fallback) => {
        if (value === fallback || value === '') {
            url.searchParams.delete(key);
        } else {
            url.searchParams.set(key, value);
        }
    };

    set('q', state.q.trim(), '');
    set('filter', state.filter, 'all');
    set('sort', state.sort, defaults.sort);
    set('dir', state.dir, defaults.dir);

    const query = url.searchParams.toString();

    return url.pathname + (query === '' ? '' : `?${query}`);
}

export function matchesRow(row, { q, filter }) {
    const words = q.trim().toLowerCase().split(/\s+/).filter(Boolean);
    const text = row.search.toLowerCase();

    return (filter === 'all' || row.filter === filter) && words.every((word) => text.includes(word));
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
 * The Alpine component around a <table>. Rows are the <tr data-row> of its <tbody>.
 */
export function tableView({ sort, dir = 'asc', copied = '', copyBase = '' }) {
    const defaults = { sort, dir };

    return {
        ...readState(window.location.search, defaults),
        shown: 0,

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
            this.$watch(
                () => [this.q, this.filter, this.sort, this.dir].join('|'),
                () => this.apply(),
            );
        },

        rows() {
            return [...this.$root.querySelectorAll('tr[data-row]')];
        },

        sortBy(key) {
            this.dir = this.sort === key && this.dir === 'asc' ? 'desc' : 'asc';
            this.sort = key;
        },

        ariaSort(key) {
            if (this.sort !== key) {
                return 'none';
            }

            return this.dir === 'asc' ? 'ascending' : 'descending';
        },

        apply() {
            const state = { q: this.q, filter: this.filter };
            const elements = this.rows();
            const view = elements.map((el) => ({
                el,
                search: el.dataset.search ?? '',
                filter: el.dataset.filter ?? '',
                sort: Object.fromEntries(
                    Object.entries(el.dataset)
                        .filter(([name]) => name.startsWith('sort'))
                        .map(([name, value]) => [name.slice(4).toLowerCase(), value]),
                ),
            }));

            view.sort((a, b) => compareRows(a, b, this.sort, this.dir));

            let shown = 0;

            for (const row of view) {
                const visible = matchesRow(row, state);
                row.el.hidden = !visible;
                shown += visible ? 1 : 0;
                row.el.parentElement.appendChild(row.el);
            }

            this.shown = shown;
            window.history.replaceState(
                window.history.state,
                '',
                writeState(
                    window.location.href,
                    { q: this.q, filter: this.filter, sort: this.sort, dir: this.dir },
                    defaults,
                ),
            );
        },
    };
}
