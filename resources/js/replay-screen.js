import { podium } from './pentahoot.js';

/** E20: the projector shows at most ten names. */
const SCREEN_ROWS = 10;

/**
 * D-11: what the Public View draws for the past moment the host replays. A Pentahoot category
 * and a final Tebak podium stand on the podium, a Tebak leaderboard is the ranked list, a Tebak
 * question shows its answer with its winner. Scores are read as `votes`, like the ranking
 * components (x-podium, x-rank-list).
 *
 * @param {{ kind: string, type: string, game: string, number: ?number, title: string, prompt?: string, rows: Array<{ rank: number, name: string, score: number }> } | null | undefined} replay
 */
export function replayView(replay) {
    if (!replay) {
        return null;
    }

    const rows = replay.rows.map((row) => ({ rank: row.rank, name: row.name, votes: row.score }));
    const shape =
        replay.kind === 'leaderboard'
            ? 'list'
            : replay.kind === 'final' || replay.type === 'pentahoot'
              ? 'podium'
              : 'winner';

    return {
        ...replay,
        rows,
        shape,
        stand: shape === 'podium' ? podium(rows, SCREEN_ROWS) : null,
        winner: shape === 'winner' ? (rows[0] ?? null) : null,
    };
}

/** The Alpine component of the replay layer on the Public View; it reads the shared store only. */
export function replayScreen() {
    return {
        get view() {
            return replayView(this.$store.realtime.snapshot?.replay);
        },
    };
}
