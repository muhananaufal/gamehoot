// B-4: live duplicate check on the import preview. Mirrors App\People\PersonName and
// App\People\DuplicateNames; the server checks the list again when it is saved.
export function displayName(name) {
    return name.normalize('NFC').replace(/\s+/gu, ' ').trim();
}

export function normalizeName(name) {
    return displayName(name).toLowerCase();
}

/**
 * @param {string[]} names
 * @param {Record<string, string>} existing normalized name => name already in the event
 * @returns {Record<number, {row: number} | {existing: string}>} problems keyed by list index
 */
export function findDuplicates(names, existing) {
    const seen = new Map();
    const problems = {};

    names.forEach((name, index) => {
        const key = normalizeName(name);

        if (key in existing) {
            problems[index] = { existing: existing[key] };
        } else if (seen.has(key)) {
            problems[index] = { row: seen.get(key) + 1 };
        } else {
            seen.set(key, index);
        }
    });

    return problems;
}

export function nameReview({ names = [], existing = {}, labels = {} } = {}) {
    return {
        rows: names.map((name, i) => ({ id: i, name })),
        existing,
        labels,

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

        remove(id) {
            this.rows = this.rows.filter((row) => row.id !== id);
        },
    };
}
