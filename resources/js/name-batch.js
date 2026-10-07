// B-4: Add names takes one name per line and checks them before sending, with the rules the
// server applies again (AddNamesRequest, DuplicateNames, PersonName): names already in the
// event or typed twice, names over 100 characters, and no more than 500 names in all (B-3).

import { displayName, MAX_NAME_LENGTH, normalizeName } from './name-review.js';

export const MAX_LENGTH = MAX_NAME_LENGTH;
export const MAX_NAMES = 500;

// One copy of the rules of PersonName, shared with the import review.
export { displayName, normalizeName } from './name-review.js';

/**
 * @param {string} text one name per line
 * @param {string[]} existing the names already in the event
 * @returns {{ names: string[], problems: Array<{ line: number, name: string, kind: 'existing'|'repeat'|'too_long', other: string|number|null }>, tooMany: boolean, ok: boolean }}
 *   names: the names to send; a problem names its line in the text area, empty lines counted
 */
export function checkNames(text, existing) {
    const inEvent = new Map(existing.map((name) => [normalizeName(name), name]));
    const seen = new Map();
    const names = [];
    const problems = [];

    text.split(/\r\n|\n|\r/).forEach((typed, index) => {
        const name = displayName(typed);
        const key = normalizeName(name);
        const line = index + 1;

        if (name === '') {
            return;
        }

        names.push(name);

        if ([...name].length > MAX_LENGTH) {
            problems.push({ line, name, kind: 'too_long', other: null });
        } else if (inEvent.has(key)) {
            problems.push({ line, name, kind: 'existing', other: inEvent.get(key) });
        } else if (seen.has(key)) {
            problems.push({ line, name, kind: 'repeat', other: seen.get(key) });
        } else {
            seen.set(key, line);
        }
    });

    const tooMany = existing.length + names.length > MAX_NAMES;

    return { names, problems, tooMany, ok: names.length > 0 && problems.length === 0 && !tooMany };
}

/**
 * A problem in the words of the server (AddNamesRequest, NameClash::lineMessage). The texts come
 * from the language files with __L__ (line), __P__ (problem), __N__ (name), __R__ (other line).
 */
export function problemText(problem, texts) {
    const said = {
        existing: () => texts.existing.replace('__N__', problem.other),
        repeat: () => texts.repeat.replace('__R__', problem.other).replace('__N__', problem.name),
        too_long: () => texts.too_long,
    }[problem.kind]();

    return texts.line.replace('__L__', problem.line).replace('__P__', said);
}

/** @param {{ one: string, other: string }} forms with __N__ where the count goes */
export function countText(forms, count) {
    return (count === 1 ? forms.one : forms.other).replace('__N__', String(count));
}

/** The Alpine component of the Add names dialog. */
export function nameBatch({ existing = [], text = '', texts = {} } = {}) {
    return {
        text,

        get check() {
            return checkNames(this.text, existing);
        },

        problemText(problem) {
            return problemText(problem, texts);
        },

        get summary() {
            return countText(texts.count, this.check.names.length);
        },

        get addLabel() {
            const count = this.check.names.length;

            return count === 0 ? texts.empty : countText(texts.add, count);
        },
    };
}
