import { guardLeaving } from './import-guard.js';

// B-4: live duplicate check on the import preview. Mirrors App\People\PersonName and
// App\People\DuplicateNames; the server checks the list again when it is saved.
export function displayName(name) {
    return name.normalize('NFC').replace(/\s+/gu, ' ').trim();
}

export function normalizeName(name) {
    return displayName(name).toLowerCase();
}

/** The longest name the server stores (people.name is 100 characters). */
export const MAX_NAME_LENGTH = 100;

/**
 * @param {string[]} names
 * @param {Record<string, string>} existing normalized name => name already in the event
 * @returns {Record<number, {row: number} | {existing: string} | {tooLong: true}>} problems keyed by list index
 */
export function findDuplicates(names, existing) {
    const seen = new Map();
    const problems = {};

    names.forEach((name, index) => {
        const key = normalizeName(name);

        // Counted in characters like PHP's max:100, not in UTF-16 units.
        if (Array.from(displayName(name)).length > MAX_NAME_LENGTH) {
            problems[index] = { tooLong: true };
        } else if (key in existing) {
            problems[index] = { existing: existing[key] };
        } else if (seen.has(key)) {
            problems[index] = { row: seen.get(key) + 1 };
        } else {
            seen.set(key, index);
        }
    });

    return problems;
}

/**
 * room: how many more names fit in the event (B-3), or null when the page does not say.
 */
export function nameReview({ names = [], existing = {}, labels = {}, room = null } = {}) {
    return {
        rows: names.map((name, i) => ({ id: i, name })),
        existing,
        labels,
        room,

        /** U24: what leaving does once the host agrees to drop the changes. */
        pending: null,
        guard: null,

        /** In the Import modal, closing it after a change asks first (import-guard.js). */
        init() {
            const layer = window.up?.layer?.current;

            if (layer?.isOverlay?.()) {
                this.guard = guardLeaving({ layer, dirty: () => this.dirty, ask: (go) => this.ask(go) });
            }
        },

        destroy() {
            this.guard?.off();
        },

        ask(go) {
            this.pending = go;
            document.getElementById('discard-import')?.showModal();
        },

        leave() {
            const go = this.pending;
            this.pending = null;
            go?.();
        },

        /** Back (or step 1) to Upload: at once, or after the question when the list was changed. */
        back(event) {
            if (!this.dirty) {
                return;
            }

            event.preventDefault();
            const url = event.currentTarget.href;
            this.ask(() => {
                this.guard?.allow();
                window.up.navigate({ url, layer: 'current' });
            });
        },

        /** U24: the list as the file gave it, so leaving after a change can ask first. */
        get dirty() {
            return this.rows.length !== names.length || this.rows.some((row, index) => row.name !== names[index]);
        },

        get overLimit() {
            return this.room !== null && this.rows.length > this.room;
        },

        // Translated texts come from the page as { one, other } with __N__ where the number goes.
        label(key, count) {
            const forms = this.labels[key] ?? { one: '', other: '' };

            return (count === 1 ? forms.one : forms.other).replace('__N__', count);
        },

        get problems() {
            return findDuplicates(
                this.rows.map((row) => row.name),
                this.existing,
            );
        },

        get problemCount() {
            return Object.keys(this.problems).length;
        },

        problemAt(index) {
            return this.problems[index] ?? null;
        },

        /** B-4: the status filter (all, ready, fix) and the search only hide rows; Save sends them all. */
        filter: 'all',
        q: '',

        get counts() {
            const fix = this.problemCount;

            return { all: this.rows.length, ready: this.rows.length - fix, fix };
        },

        shown(row, index) {
            const problem = this.problemAt(index) !== null;
            const status = this.filter === 'all' || (this.filter === 'fix' ? problem : !problem);
            const query = normalizeName(this.q);

            return status && (query === '' || normalizeName(row.name).includes(query));
        },

        get nothingShown() {
            return this.rows.length > 0 && !this.rows.some((row, index) => this.shown(row, index));
        },

        reset() {
            this.filter = 'all';
            this.q = '';
        },

        remove(id) {
            this.rows = this.rows.filter((row) => row.id !== id);
        },
    };
}
