// Tebak Kata letter boxes on the screens; covered by tests/js/kata.test.js. What both Tebak games
// share lives in tebak.js.

/**
 * E5: how a cell looks. The snapshot keys are short (F14): c letter, b box number, o how it
 * opened (i at the start, h by a hint).
 */
export function legendOf(cell) {
    if (cell.b === null) {
        return 'punctuation';
    }

    if (cell.c === null) {
        return 'closed';
    }

    return { i: 'initial', h: 'hint' }[cell.o] ?? 'revealed';
}
