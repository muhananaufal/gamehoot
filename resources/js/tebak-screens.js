// F20: the Alpine components of the Tebak screens (Tebak Kata and Tebak Gambar). They only read
// the shared realtime store; the rules live in tebak.js, kata.js and on the server.
import { withBusyButton } from './action-busy.js';
import { legendOf } from './kata.js';
import { canReveal, canSkip, movementOf, tebakView } from './tebak.js';
import { fill, podium, promptSize, SCREEN_ROWS, searchPeople } from './pentahoot.js';

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

/**
 * The fields both Tebak screens share. Getters are defined per component: spreading an
 * object would read them once instead of keeping them live.
 */
function base(labels) {
    return {
        labels,

        label(key, values = {}) {
            return fill(this.labels[key] ?? '', values);
        },

        count(key, value) {
            const text = this.labels[key] ?? { one: '', other: '' };

            return fill(value === 1 ? text.one : text.other, { N: value });
        },

        legendOf,

        moveLabel(row) {
            const move = movementOf(row);

            return {
                up: fill(this.labels.moved_up, { N: move.steps }),
                down: fill(this.labels.moved_down, { N: move.steps }),
                new: this.labels.new_entry,
                same: '',
            }[move.direction];
        },
    };
}

/**
 * The Public View and the phone mirror (E14) of the Tebak games of the given types: the question
 * (boxes or images), the winner or surrender, the leaderboard (E11) and the final podium (E12, E17).
 * E20: only the projector (projector: true) caps the podium at ten name rows; phones list everyone.
 */
export function tebakScreen({ types, labels, projector = false }) {
    return {
        ...base(labels),

        // U31: a winner and the final podium are worth a celebration, once they happen here.
        init() {
            this.$watch(
                () => this.view,
                (view) => {
                    if (view === 'won') {
                        this.$dispatch('celebrate', { key: `won:${this.question.id}` });
                    }

                    if (view === 'final') {
                        this.$dispatch('celebrate', { key: `final:${this.game.id}` });
                    }
                },
            );
        },

        get store() {
            return this.$store.realtime;
        },
        get game() {
            const game = this.store.snapshot?.game;

            return types.includes(game?.type) ? game : null;
        },
        get view() {
            return this.game ? tebakView(this.game) : 'none';
        },
        get question() {
            return this.game?.question ?? null;
        },
        /** E15: smaller type for longer prompts (Kata up to 500 characters). */
        get promptFont() {
            return promptSize(this.question?.prompt ?? this.question?.title).tebak;
        },
        /** E14: phones mirror the questions only when the host turned it on. */
        get mirrored() {
            return this.store.snapshot?.event?.show_on_devices === true;
        },
        get board() {
            return this.game?.leaderboard ?? [];
        },
        /** E17: the final podium uses the same component as Pentahoot, with points as the score. */
        get stand() {
            return podium(
                (this.game?.final ?? []).map((row) => ({ rank: row.rank, name: row.name, votes: row.points })),
                projector ? SCREEN_ROWS : Infinity,
            );
        },
    };
}

/**
 * Live control for a Tebak game of the given type: show any queued question (D-5), open boxes
 * as hints (Kata, E5) or reveal the answer image (Gambar, E9), skip once (E6, E7), pick a winner
 * with a confirmation (D-6), surrender, and show the leaderboard (E11). STALE_ACTION is shown
 * when the state moved on (C-3).
 */
export function tebakHost({ type, actionUrl, people, labels, questionIds = [] }) {
    return {
        ...base(labels),
        busy: false,
        error: '',
        query: '',
        chosen: null,

        get store() {
            return this.$store.realtime;
        },
        get game() {
            const game = this.store.snapshot?.game;

            return game?.type === type ? game : null;
        },
        get question() {
            return this.game?.question ?? null;
        },
        get status() {
            return this.question?.status ?? null;
        },
        get codes() {
            return this.game?.statuses ?? [];
        },
        get finished() {
            return this.game?.status === 'finished';
        },
        get matches() {
            return searchPeople(people, this.query);
        },
        get liveBoard() {
            return this.game?.live_board ?? [];
        },
        get canSkip() {
            return canSkip(this.game);
        },
        get canReveal() {
            return canReveal(this.game);
        },
        get canLeaderboard() {
            return this.status === 'won' && this.game?.leaderboard === null;
        },
        /** E12: every question is resolved once nothing is queued or on screen. */
        get allResolved() {
            return this.codes.length > 0 && this.codes.every((code) => code === 'w' || code === 'x');
        },

        /** F20: the timeline state of the question at this copy index. */
        timelineState(index) {
            return { w: 'done', x: 'surrendered', s: 'current', Q: 'skipped' }[this.codes[index]] ?? 'todo';
        },

        statusOf(index) {
            return this.labels.status[this.codes[index] ?? 'q'];
        },

        /** D-5: any queued question, while none is on screen (G7). */
        /** D-10: Show question 1 starts a game; once a question was shown, Next question or the list goes on. */
        get firstToOpen() {
            return this.codes.length > 0 && this.codes.every((code) => code === 'q') ? this.nextToOpen : null;
        },

        /** D-10: the first queued question, then a skipped one (E6), for Next question. */
        get nextToOpen() {
            const queued = this.codes.indexOf('q');
            const index = queued === -1 ? this.codes.indexOf('Q') : queued;

            return index === -1 || !questionIds[index] ? null : { number: index + 1, id: questionIds[index] };
        },

        canShow(index) {
            const code = this.codes[index];

            return !this.finished && (code === 'q' || code === 'Q') && this.status !== 'shown';
        },

        canHint(cell) {
            const closed = (this.question?.boxes ?? []).flat().filter((c) => c.b !== null && c.c === null).length;

            // E5: the last closed box stays closed. Disabled while an action is on its way, which
            // would otherwise swallow the click.
            return !this.busy && this.status === 'shown' && cell.b !== null && cell.o === null && closed > 1;
        },

        pick(person) {
            this.chosen = person;
            this.query = person.name;
        },

        async act(action, questionId = this.question?.id, input = {}) {
            if (this.busy || !questionId) {
                return;
            }

            this.busy = true;
            this.error = '';

            // U16: the button that started the action shows the spinner until it ends.
            await withBusyButton(async () => {
                try {
                    const response = await fetch(
                        actionUrl.replace('__QUESTION__', questionId).replace('__ACTION__', action),
                        {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: {
                                'Content-Type': 'application/json',
                                Accept: 'application/json',
                                'X-CSRF-TOKEN': csrfToken(),
                            },
                            body: JSON.stringify(input),
                        },
                    );

                    if (response.status !== 204) {
                        const body = await response.json().catch(() => ({}));
                        this.error = body.message ?? this.labels.network_error;
                    } else if (action === 'winner') {
                        this.chosen = null;
                        this.query = '';
                    }

                    // F1: this page loads the new state itself instead of waiting for the broadcast.
                    this.store.refresh?.();
                } catch {
                    this.error = this.labels.network_error;
                } finally {
                    this.busy = false;
                }
            });
        },
    };
}
