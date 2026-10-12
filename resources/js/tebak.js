// Screen logic both Tebak games share (Tebak Kata and Tebak Gambar), for the Public View, the
// phone mirror (E14) and Live control. Pure functions over the game part of the snapshot;
// covered by tests/js/tebak.test.js.
import { fill } from './pentahoot.js';

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
 * E11: a row's move as the screens draw it, a colored arrow with its steps or a NEW badge, with
 * the words screen readers hear. Nothing for a name that kept its place, or before the cards moved.
 */
export function moveBadge(row, labels) {
    if (row.settled === false) {
        return null;
    }

    const move = movementOf(row);

    if (move.direction === 'same') {
        return null;
    }

    const label = {
        up: fill(labels.moved_up ?? '', { N: move.steps }),
        down: fill(labels.moved_down ?? '', { N: move.steps }),
        new: labels.new_entry ?? '',
    }[move.direction];

    return { ...move, label };
}

/** E11: how long the board before the last win stays before the cards move to their new places. */
export const BOARD_HOLD_MS = 1200;

/** E11: whether there is a board before the last win to move from (none after the first win). */
export function hasBoardBefore(before) {
    return Array.isArray(before) && before.length > 0;
}

/**
 * E11: the rows of a leaderboard in each phase of its transition: 'before' is the board before
 * the last win, not settled yet (no moves shown); 'after' the board now, settled, with its moves.
 */
export function boardRows(before, now, phase) {
    if (phase === 'before' && hasBoardBefore(before)) {
        return before.map((row) => ({ ...row, settled: false }));
    }

    return (now ?? []).map((row) => ({ ...row, settled: true }));
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
