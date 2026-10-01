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

        remove(id) {
            this.rows = this.rows.filter((row) => row.id !== id);
        },
    };
}
