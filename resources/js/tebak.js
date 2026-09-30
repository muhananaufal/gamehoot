// Screen logic both Tebak games share (Tebak Kata and Tebak Gambar), for the Public View, the
// phone mirror (E14) and Live control. Pure functions over the game part of the snapshot;
// covered by tests/js/tebak.test.js.

/**
 * What the screen shows.
 * E12, E17: the final board once finished; E11: the leaderboard while shown; otherwise the
 * question on screen (shown, won, surrendered) or "next" between questions.
 */
export function tebakView(game) {
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

/**
 * E6: once per question, and only while another question is queued. E7: never after a Tebak
 * Gambar answer was revealed. The host snapshot lists one status letter per question (F14).
 */
export function canSkip(game) {
    const question = game?.question;

    if (!question || question.status !== 'shown' || question.skipped || question.revealed === true) {
        return false;
    }

    return (game.statuses ?? []).some((code) => code === 'q' || code === 'Q');
}

/**
 * E9: a Tebak Gambar answer is revealed once, while its question is on screen.
 */
export function canReveal(game) {
    return game?.type === 'tebak_gambar' && game.question?.status === 'shown' && game.question.revealed === false;
}
