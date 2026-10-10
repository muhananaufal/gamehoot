// F9: the phone searches the whole name list it was sent, without a request per keystroke.
// B-8 (audit DATA-1): a name is found whatever accents, case or letter width the player types,
// the way the database compares names.
import { foldText } from './text-fold.js';

/**
 * @param {{ names: string[] }} options
 */
export function nameSearch({ names }) {
    const folded = names.map((name) => foldText(name));

    return {
        query: '',

        /** Whether the name at `index` matches what was typed. */
        shows(index) {
            const typed = foldText(this.query.trim());

            return typed === '' || folded[index].includes(typed);
        },

        get noMatch() {
            return this.query.trim() !== '' && !folded.some((_, index) => this.shows(index));
        },
    };
}
