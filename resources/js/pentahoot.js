// Pentahoot screen logic, shared by the phone, the Public View and Live control.
// Pure functions over the shared store; covered by tests/js/pentahoot.test.js.
import { normalizeName } from './name-review.js';

/** E3: votes on their way are still accepted this long after ends_at (App\Games\Pentahoot\VoteWindow). */
export const TOLERANCE_MS = 1_000;

/**
 * F4: whole seconds left on the server clock, never below zero.
 */
export function secondsLeft(store, question) {
    if (!question?.ends_at) {
        return 0;
    }

    return Math.ceil(store.msUntil(question.ends_at) / 1_000);
}

/**
 * E3: the phone locks its button at exactly zero; the server keeps a second of tolerance.
 */
export function canVote(store, question) {
    return question?.status === 'live' && question.ends_at !== null && store.msUntil(question.ends_at) > 0;
}

/**
 * The phase every screen draws. "closed" is not sent by the server (spec section 05): a live
 * question is closed once no vote can arrive anymore.
 */
export function phaseOf(store, question) {
    if (!question) {
        return 'none';
    }

    if (
        question.status === 'live' &&
        question.ends_at !== null &&
        store.msUntil(question.ends_at + TOLERANCE_MS) === 0
    ) {
        return 'closed';
    }

    return question.status;
}

/**
 * E2: the reveal opens from the lowest rank up to rank 1; tied names open together.
 */
export function revealSteps(results) {
    const byRank = new Map();

    for (const row of results) {
        byRank.set(row.rank, [...(byRank.get(row.rank) ?? []), row]);
    }

    return [...byRank.keys()].sort((a, b) => b - a).map((rank) => byRank.get(rank));
}

/**
 * E17: ranks 1 to 3 stand on the podium; a tie can leave a step empty. Ranks 4 and 5 follow
 * as small rows.
 */
/** E17: a podium step shows at most this many names; more are summed up in one row (E20). */
export const PODIUM_STEP_NAMES = 3;

/** E20: the projector draws at most this many rows of the reveal list. A design choice, tuned at the rehearsal. */
export const SCREEN_ROWS = 10;

/**
 * E20: one row standing for a whole tied group: its rank, how many names and their score.
 */
function summaryOf(group) {
    return { rank: group[0].rank, summary: true, count: group.length, votes: group[0].votes };
}

/**
 * E17: ranks 1 to 3 stand on the podium; a tie can leave a step empty, and a step with more
 * than three names shows one summary row (E20). Ranks 4 and 5 follow as small rows.
 */
export function podium(results) {
    const step = (rank) => {
        const rows = results.filter((row) => row.rank === rank);

        return rows.length > PODIUM_STEP_NAMES ? [summaryOf(rows)] : rows;
    };

    return {
        first: step(1),
        second: step(2),
        third: step(3),
        rest: results.filter((row) => row.rank > 3),
    };
}

/**
 * E20: keeps whole rank groups while the list fits in max rows. The first group that would
 * not fit, and every group after it, becomes one summary row, so a tie is never cut.
 * The full list stays on phones, the results page and the CSV.
 */
export function capRows(results, max = SCREEN_ROWS) {
    const rows = [];
    let overflowing = false;

    for (const group of revealSteps(results).reverse()) {
        if (!overflowing && rows.length + group.length <= max) {
            rows.push(...group);
        } else {
            overflowing = true;
            rows.push(summaryOf(group));
        }
    }

    return rows;
}

/**
 * F9: search the downloaded name list on the phone. Names starting with the text come first.
 */
export function searchPeople(people, query, limit = 8) {
    const needle = normalizeName(query ?? '');

    if (needle === '') {
        return [];
    }

    const starts = [];
    const contains = [];

    for (const person of people) {
        const name = normalizeName(person.name);

        if (name.startsWith(needle)) {
            starts.push(person);
        } else if (name.includes(needle)) {
            contains.push(person);
        }
    }

    return [...starts, ...contains].slice(0, limit);
}

/**
 * A10: fills __N__, __TOTAL__ and other placeholders into a translated label.
 */
export function fill(label, values) {
    return Object.entries(values).reduce((text, [key, value]) => text.replaceAll(`__${key}__`, String(value)), label);
}
