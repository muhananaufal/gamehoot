import { prefersReducedMotion } from './flip.js';
import { podium } from './pentahoot.js';
import { BOARD_HOLD_MS } from './tebak.js';

/** E20: the projector shows at most ten names. */
const SCREEN_ROWS = 10;

/**
 * D-11: what the Public View draws for the past moment the host replays. A Pentahoot category
 * and a final Tebak podium stand on the podium, a Tebak leaderboard is the ranked list, a Tebak
 * question shows its answer with its winner. Scores are read as `votes`, like the ranking
 * components (x-podium, x-rank-list).
 *
 * E11: a leaderboard also carries the board before that question, which the list moves from.
 *
 * @param {{ kind: string, type: string, game: string, number: ?number, title: string, prompt?: string, rows: Array<{ rank: number, name: string, score: number }>, before?: Array<{ rank: number, name: string, score: number }> } | null | undefined} replay
 */
export function replayView(replay) {
    if (!replay) {
        return null;
    }

    const votes = (list) => (list ?? []).map((row) => ({ rank: row.rank, name: row.name, votes: row.score }));
    const rows = votes(replay.rows);
    const shape =
        replay.kind === 'leaderboard'
            ? 'list'
            : replay.kind === 'final' || replay.type === 'pentahoot'
              ? 'podium'
              : 'winner';

    return {
        ...replay,
        rows,
        before: votes(replay.before),
        shape,
        stand: shape === 'podium' ? podium(rows, SCREEN_ROWS) : null,
        winner: shape === 'winner' ? (rows[0] ?? null) : null,
    };
}

/** The Alpine component of the replay layer on the Public View; it reads the shared store only. */
export function replayScreen() {
    return {
        // E11: a replayed leaderboard moves from the board before that question, like a live one.
        listPhase: 'after',
        listTimer: null,

        init() {
            this.$watch(
                () => this.listKey,
                () => this.playList(),
            );
            this.playList();
        },

        get view() {
            return replayView(this.$store.realtime.snapshot?.replay);
        },
        get listKey() {
            const view = this.view;

            return view?.shape === 'list' ? JSON.stringify([view.rows, view.before]) : null;
        },
        get listRows() {
            const view = this.view;

            if (!view) {
                return [];
            }

            return this.listPhase === 'before' && view.before.length > 0 ? view.before : view.rows;
        },

        /** E11, U27: hold the board before for a moment, then let x-flip move the cards. */
        playList() {
            clearTimeout(this.listTimer);

            if (!this.listKey || this.view.before.length === 0 || prefersReducedMotion()) {
                this.listPhase = 'after';

                return;
            }

            this.listPhase = 'before';
            this.listTimer = setTimeout(() => {
                this.listPhase = 'after';
            }, BOARD_HOLD_MS);
        },
    };
}
