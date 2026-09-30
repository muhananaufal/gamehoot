// Tebak Kata screen logic, shared by the Public View, the phone mirror (E14) and Live control.
// Pure functions over the game part of the snapshot; covered by tests/js/kata.test.js.

/**
 * What the screen shows for a Tebak Kata game.
 * E12, E17: the final board once finished; E11: the leaderboard while shown; otherwise the
 * question on screen (shown, won, surrendered) or "next" between questions.
 */
export function kataView(game) {
    if (game.status === 'finished') {
        return 'final';
    }

    if (game.leaderboard !== null && game.leaderboard !== undefined) {
        return 'leaderboard';
    }

    if (!game.question) {
        return 'next';
    }

    return { shown: 'question', won: 'won', surrendered: 'surrendered' }[game.question.status] ?? 'next';
}

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

/**
 * E11: the move of a leaderboard row since the board before the last win.
 */
export function movementOf(row) {
    if (row.movement === null || row.movement === undefined) {
        return { direction: 'new', steps: 0 };
    }

    if (row.movement === 0) {
        return { direction: 'same', steps: 0 };
    }

    return { direction: row.movement > 0 ? 'up' : 'down', steps: Math.abs(row.movement) };
}
