// F20: the Alpine components of the three Pentahoot screens. They only read the shared
// realtime store; the rules live in pentahoot.js and on the server.
import { canVote, capRows, fill, phaseOf, podium, revealSteps, searchPeople, secondsLeft } from './pentahoot.js';

/** Redraw rate of countdowns and the closed phase (F4). */
const TICK_MS = 250;

/** E2, E17: pause between reveal steps, and the longer pause before rank 1. */
export const STEP_MS = 1_800;
export const FINAL_PAUSE_MS = 2_600;

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

async function postJson(url, body = null) {
    const response = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken() },
        body: body === null ? null : JSON.stringify(body),
    });

    return { status: response.status, body: await response.json().catch(() => ({})) };
}

/**
 * The fields every Pentahoot screen shares. Getters are defined per component: spreading an
 * object would read them once instead of keeping them live.
 */
function base(labels) {
    return {
        labels,
        tick: 0,
        clock: null,

        startClock() {
            this.clock = setInterval(() => {
                this.tick = Date.now();
            }, TICK_MS);
        },

        destroy() {
            clearInterval(this.clock);
        },

        label(key, values = {}) {
            return fill(this.labels[key] ?? '', values);
        },
    };
}

/**
 * The phone: wait, pick a name and vote (F3, F9, E3), see the sent answer (E4), then the
 * same top 5 as the Public View (E4b).
 */
export function pentahootPhone({ peopleUrl, voteUrl, labels }) {
    return {
        ...base(labels),
        people: null,
        query: '',
        chosen: null,
        sending: false,
        error: '',

        init() {
            this.startClock();
            this.$watch(
                () => `${this.question?.id}:${this.question?.attempt}`,
                () => {
                    this.chosen = null;
                    this.query = '';
                    this.error = '';
                },
            );
            this.$watch(
                () => this.view,
                (view) => view === 'vote' && this.loadPeople(),
            );

            if (this.view === 'vote') {
                this.loadPeople();
            }
        },

        get store() {
            return this.$store.realtime;
        },
        /** F13: these components draw Pentahoot games only; the Tebak screens have their own. */
        get isPentahoot() {
            return this.store.snapshot?.game?.type === 'pentahoot';
        },
        /** G7, E12: a finished game stays on screen until the next one starts. */
        get finished() {
            return this.store.snapshot?.game?.status === 'finished';
        },
        get question() {
            return this.store.snapshot?.game?.question ?? null;
        },
        get phase() {
            void this.tick;

            return phaseOf(this.store, this.question);
        },
        get seconds() {
            void this.tick;

            return secondsLeft(this.store, this.question);
        },
        get myVote() {
            const vote = this.store.me?.vote;
            const question = this.question;

            return vote && question && vote.question_id === question.id && vote.attempt === question.attempt
                ? vote
                : null;
        },
        get view() {
            if (!this.store.snapshot?.game) {
                return 'waiting';
            }

            if (!this.isPentahoot) {
                return 'other';
            }

            if (this.finished) {
                return 'finished';
            }

            switch (this.phase) {
                case 'live':
                    if (this.myVote) {
                        return 'sent';
                    }

                    void this.tick;

                    return canVote(this.store, this.question) ? 'vote' : 'time_up';
                case 'closed':
                    return this.myVote ? 'sent' : 'time_up';
                case 'revealed':
                case 'done':
                    return 'results';
                default:
                    return 'next';
            }
        },
        get matches() {
            return searchPeople(this.people ?? [], this.query);
        },
        get results() {
            return this.question?.results ?? [];
        },
        get stand() {
            return podium(this.results);
        },

        async loadPeople() {
            if (this.people !== null) {
                return;
            }

            try {
                const response = await fetch(peopleUrl, {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                });

                if (response.ok) {
                    this.people = (await response.json()).people;
                }
            } catch {
                this.error = this.labels.network_error;
            }
        },

        pick(person) {
            this.chosen = person;
            this.query = person.name;
        },

        async send() {
            const question = this.question;

            if (!this.chosen || this.sending || !canVote(this.store, question)) {
                return;
            }

            this.sending = true;
            this.error = '';

            try {
                const { status, body } = await postJson(voteUrl.replace('__QUESTION__', question.id), {
                    target: this.chosen.id,
                    attempt: question.attempt,
                });

                if (status === 201) {
                    this.store.me = {
                        ...(this.store.me ?? {}),
                        vote: { question_id: question.id, attempt: question.attempt, target: body.target.name },
                    };
                } else {
                    this.error = body.message ?? this.labels.network_error;
                }
            } catch {
                this.error = this.labels.network_error;
            } finally {
                this.sending = false;
            }
        },
    };
}

/**
 * The Public View: question with countdown and answered count (E15), then the reveal from
 * the lowest rank up (E2) ending on the podium (E17). A page opened after the reveal shows
 * the final podium at once.
 */
export function pentahootScreen({ labels, reducedMotion = false }) {
    return {
        ...base(labels),
        liveKey: null,
        revealKey: null,
        shown: 0,
        final: false,
        timers: [],

        init() {
            this.startClock();
            this.$watch(
                () => `${this.phase}|${this.question?.id}|${this.question?.attempt}`,
                () => this.follow(),
            );
            this.follow();
        },

        destroy() {
            clearInterval(this.clock);
            this.timers.forEach(clearTimeout);
        },

        get store() {
            return this.$store.realtime;
        },
        /** F13: these components draw Pentahoot games only; the Tebak screens have their own. */
        get isPentahoot() {
            return this.store.snapshot?.game?.type === 'pentahoot';
        },
        /** G7, E12: a finished game stays on screen until the next one starts. */
        get finished() {
            return this.store.snapshot?.game?.status === 'finished';
        },
        get game() {
            return this.store.snapshot?.game ?? null;
        },
        get question() {
            return this.game?.question ?? null;
        },
        get phase() {
            void this.tick;

            return phaseOf(this.store, this.question);
        },
        get seconds() {
            void this.tick;

            return secondsLeft(this.store, this.question);
        },
        get results() {
            return this.question?.results ?? [];
        },
        /** E20: the projector draws at most ten rows; a tie that does not fit is one summary row. */
        get rows() {
            return capRows(this.results);
        },
        get steps() {
            return revealSteps(this.rows);
        },
        get stand() {
            return podium(this.results);
        },

        /** E2: a row shows once the reveal has reached its rank. */
        isShown(row) {
            const index = this.steps.findIndex((step) => step.some((r) => r.rank === row.rank));

            return index !== -1 && index < this.shown;
        },

        follow() {
            const key = this.question ? `${this.question.id}:${this.question.attempt}` : null;

            if (this.phase === 'live' || this.phase === 'closed') {
                this.liveKey = key;
            }

            if (this.phase !== 'revealed' || key === this.revealKey) {
                return;
            }

            this.revealKey = key;
            this.timers.forEach(clearTimeout);
            this.timers = [];

            const total = this.steps.length;

            if (reducedMotion || this.liveKey !== key || total === 0) {
                this.shown = total;
                this.final = true;

                return;
            }

            this.shown = 0;
            this.final = false;
            let delay = 600;

            for (let step = 1; step <= total; step++) {
                delay += step === total ? FINAL_PAUSE_MS : STEP_MS;
                this.timers.push(setTimeout(() => (this.shown = step), delay));
            }

            this.timers.push(setTimeout(() => (this.final = true), delay + STEP_MS));
        },
    };
}

/**
 * Live control: open any question that is not done (D-5), run it, see the live count only
 * hosts see (F2), and read STALE_ACTION when the state moved on (C-3).
 */
export function pentahootHost({ actionUrl, labels }) {
    return {
        ...base(labels),
        busy: false,
        error: '',

        init() {
            this.startClock();
        },

        get store() {
            return this.$store.realtime;
        },
        /** F13: these components draw Pentahoot games only; the Tebak screens have their own. */
        get isPentahoot() {
            return this.store.snapshot?.game?.type === 'pentahoot';
        },
        /** G7, E12: a finished game stays on screen until the next one starts. */
        get finished() {
            return this.store.snapshot?.game?.status === 'finished';
        },
        get game() {
            return this.store.snapshot?.game ?? null;
        },
        get question() {
            return this.game?.question ?? null;
        },
        get phase() {
            void this.tick;

            return phaseOf(this.store, this.question);
        },
        get seconds() {
            void this.tick;

            return secondsLeft(this.store, this.question);
        },
        get answered() {
            return this.game?.answered ?? 0;
        },
        get total() {
            return this.store.snapshot?.lobby?.joined ?? 0;
        },
        get tally() {
            return this.game?.tally ?? [];
        },
        get results() {
            return this.question?.results ?? [];
        },
        /** E15: the host is told, the question is not closed automatically. */
        get allAnswered() {
            return this.phase === 'live' && this.total > 0 && this.answered >= this.total;
        },
        get busyQuestion() {
            return this.phase === 'live' || this.phase === 'closed' || this.phase === 'revealed';
        },

        statusOf(number) {
            const status = this.game?.statuses?.[number - 1] ?? 'ready';

            return this.question?.number === number && this.phase === 'closed' ? 'closed' : status;
        },

        can(action) {
            switch (action) {
                case 'stop':
                    return this.phase === 'live' && this.seconds > 0;
                case 'reveal':
                    return this.phase === 'closed';
                case 'next':
                    return this.phase === 'revealed';
                case 'reset':
                    return this.busyQuestion;
                default:
                    return false;
            }
        },

        /** F20: the timeline state of a question in the list. */
        timelineState(number) {
            const status = this.statusOf(number);

            if (status === 'done') {
                return 'done';
            }

            return status === 'ready' ? 'todo' : 'current';
        },

        canOpen(number) {
            return !this.busyQuestion && this.statusOf(number) === 'ready';
        },

        async act(action, questionId = this.question?.id) {
            if (this.busy || !questionId) {
                return;
            }

            this.busy = true;
            this.error = '';

            try {
                const { status, body } = await postJson(
                    actionUrl.replace('__QUESTION__', questionId).replace('__ACTION__', action),
                );

                if (status !== 204) {
                    this.error = body.message ?? this.labels.network_error;
                    this.store.refresh?.();
                }
            } catch {
                this.error = this.labels.network_error;
            } finally {
                this.busy = false;
            }
        },
    };
}
