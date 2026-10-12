import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { pentahootHost, pentahootPhone, pentahootScreen } from '../../resources/js/pentahoot-screens.js';
import { realtimeStore } from '../../resources/js/realtime.js';
import { tebakHost, tebakScreen } from '../../resources/js/tebak-screens.js';

const live = {
    version: 3,
    server_now: 0,
    event: { status: 'open' },
    game: {
        type: 'pentahoot',
        status: 'running',
        count: 2,
        question: { id: 'q1', number: 1, status: 'live', ends_at: 20_000, attempt: 1 },
    },
};

const answer = (status, body) => ({ status, json: async () => body });

beforeEach(() => {
    vi.stubGlobal('document', { querySelector: () => null });
});

afterEach(() => {
    vi.unstubAllGlobals();
});

describe('F3, E4 the phone after sending a vote', () => {
    it('shows the stored pick when the server says this phone already voted (lost response)', async () => {
        const store = realtimeStore({ now: () => 0 });
        store.accept(live);
        // The vote of the first try did reach the server; /state brings it back.
        store.refresh = vi.fn(async () => {
            store.me = { vote: { question_id: 'q1', attempt: 1, target: 'Ana Putri' } };
        });
        vi.stubGlobal(
            'fetch',
            vi.fn(async () =>
                answer(409, { code: 'ALREADY_VOTED', message: 'You have already voted for this question.' }),
            ),
        );

        const phone = pentahootPhone({
            peopleUrl: '/people',
            voteUrl: '/vote/__QUESTION__',
            labels: { network_error: 'Network' },
        });
        phone.$store = { realtime: store };
        phone.chosen = { id: 'p2', name: 'Budi Santoso' };

        await phone.send();

        expect(store.refresh).toHaveBeenCalledOnce();
        expect(phone.view).toBe('sent');
        expect(phone.myVote.target).toBe('Ana Putri');
        expect(phone.error).toBe('');
    });
});

describe('F1 Live control after its own action', () => {
    it('loads the new state at once instead of waiting for the broadcast', async () => {
        const store = realtimeStore({ now: () => 0 });
        store.accept(live);
        store.refresh = vi.fn(async () => {});
        vi.stubGlobal(
            'fetch',
            vi.fn(async () => answer(204, {})),
        );

        const host = pentahootHost({ actionUrl: '/act/__QUESTION__/__ACTION__', labels: { network_error: 'Network' } });
        host.$store = { realtime: store };

        await host.act('stop', 'q1');

        expect(store.refresh).toHaveBeenCalledOnce();
        expect(host.error).toBe('');
    });
});

describe('F1 Tebak Live control after its own action', () => {
    it('loads the new state at once instead of waiting for the broadcast', async () => {
        const store = realtimeStore({ now: () => 0 });
        store.accept({
            ...live,
            game: { type: 'tebak_kata', status: 'running', question: { id: 'q1', status: 'shown' } },
        });
        store.refresh = vi.fn(async () => {});
        vi.stubGlobal(
            'fetch',
            vi.fn(async () => answer(204, {})),
        );

        const host = tebakHost({
            type: 'tebak_kata',
            actionUrl: '/act/__QUESTION__/__ACTION__',
            people: [],
            labels: { network_error: 'Network' },
        });
        host.$store = { realtime: store };

        await host.act('skip');

        expect(store.refresh).toHaveBeenCalledOnce();
    });
});

describe('B-6 a phone whose name the host released', () => {
    it('goes back to the name list instead of staying on a vote it cannot send', async () => {
        const store = realtimeStore({ now: () => 0 });
        store.accept(live);
        store.refresh = vi.fn(async () => {});
        vi.stubGlobal(
            'fetch',
            vi.fn(async () => answer(401, { code: 'NOT_CLAIMED', message: 'Pick your name first.' })),
        );
        const navigate = vi.fn();

        const phone = pentahootPhone({
            peopleUrl: '/people',
            voteUrl: '/vote/__QUESTION__',
            joinUrl: '/demo',
            navigate,
            labels: { network_error: 'Network' },
        });
        phone.$store = { realtime: store };
        phone.chosen = { id: 'p2', name: 'Budi Santoso' };

        await phone.send();

        expect(navigate).toHaveBeenCalledWith('/demo');
    });
});

describe('D-8 Live control after the last question', () => {
    const hostWith = (game) => {
        const store = realtimeStore({ now: () => 0 });
        store.accept({ ...live, game: { ...live.game, ...game } });
        const host = pentahootHost({ actionUrl: '/act', labels: {} });
        host.$store = { realtime: store };

        return host;
    };

    it('knows every question is done once the last one moved on', () => {
        expect(hostWith({ question: null, statuses: ['done', 'done'] }).allDone).toBe(true);
    });

    it('is not done while a question is still open or waiting', () => {
        expect(hostWith({ question: null, statuses: ['done', 'ready'] }).allDone).toBe(false);
        expect(
            hostWith({
                question: { id: 'q2', number: 2, status: 'revealed', attempt: 1 },
                statuses: ['done', 'revealed'],
            }).allDone,
        ).toBe(false);
        expect(hostWith({ question: null, statuses: [] }).allDone).toBe(false);
    });
});

describe('B-6 the vote list follows the name list', () => {
    it('loads the names again once the host changed the list, and not before', async () => {
        const store = realtimeStore({ now: () => 0 });
        store.accept({ ...live, event: { ...live.event, roster: 1 } });
        const fetchPeople = vi.fn(async () => ({
            ok: true,
            ...answer(200, { people: [{ id: 'p1', name: 'Ana Putri' }] }),
        }));
        vi.stubGlobal('fetch', fetchPeople);
        const phone = pentahootPhone({ peopleUrl: '/people', voteUrl: '/vote/__QUESTION__', labels: {} });
        phone.$store = { realtime: store };

        await phone.loadPeople();
        await phone.loadPeople();
        expect(fetchPeople).toHaveBeenCalledOnce();

        store.accept({ ...live, version: live.version + 1, event: { ...live.event, roster: 2 } });
        await phone.loadPeople();
        expect(fetchPeople).toHaveBeenCalledTimes(2);
    });
});

describe('Live control opens the next question from the middle, in order', () => {
    const withGame = (make, game) => {
        const store = realtimeStore({ now: () => 0 });
        store.accept({ ...live, game: { ...live.game, ...game } });
        const host = make();
        host.$store = { realtime: store };

        return host;
    };

    it('offers the first Pentahoot question that is still ready', () => {
        const host = () => pentahootHost({ actionUrl: '/act', labels: {}, questionIds: ['q1', 'q2', 'q3'] });

        expect(withGame(host, { question: null, statuses: ['done', 'ready', 'ready'] }).nextToOpen).toEqual({
            number: 2,
            id: 'q2',
        });
        expect(withGame(host, { question: null, statuses: ['done', 'done', 'done'] }).nextToOpen).toBe(null);
    });

    it('offers the first queued Tebak question, then a skipped one', () => {
        const host = () =>
            tebakHost({
                type: 'tebak_kata',
                actionUrl: '/act',
                people: [],
                labels: {},
                questionIds: ['q1', 'q2', 'q3'],
            });

        expect(withGame(host, { type: 'tebak_kata', question: null, statuses: ['w', 'Q', 'q'] }).nextToOpen).toEqual({
            number: 3,
            id: 'q3',
        });
        expect(withGame(host, { type: 'tebak_kata', question: null, statuses: ['w', 'Q', 'x'] }).nextToOpen).toEqual({
            number: 2,
            id: 'q2',
        });
        expect(withGame(host, { type: 'tebak_kata', question: null, statuses: ['w', 'w', 'x'] }).nextToOpen).toBe(null);
    });
});

describe('Next question goes on in order; Pick a question leaves the choice to the host', () => {
    const revealed = () => {
        const store = realtimeStore({ now: () => 0 });
        store.accept({
            ...live,
            game: {
                ...live.game,
                question: { id: 'q1', number: 1, status: 'revealed', attempt: 1 },
                statuses: ['revealed', 'ready', 'ready'],
            },
        });
        store.refresh = vi.fn(async () => {});
        const host = pentahootHost({
            actionUrl: '/act/__QUESTION__/__ACTION__',
            labels: { network_error: 'Network' },
            questionIds: ['q1', 'q2', 'q3'],
        });
        host.$store = { realtime: store };

        return host;
    };

    it('goes on in one server action, so no screen shows the moment in between', async () => {
        const post = vi.fn(async () => answer(204, {}));
        vi.stubGlobal('fetch', post);

        await revealed().act('advance');

        expect(post.mock.calls.map(([url]) => url)).toEqual(['/act/q1/advance']);
    });
});

describe('Open question N only starts a game; after Pick a question the list decides', () => {
    const withGame = (make, game) => {
        const store = realtimeStore({ now: () => 0 });
        store.accept({ ...live, game: { ...live.game, ...game } });
        const host = make();
        host.$store = { realtime: store };

        return host;
    };

    it('offers the first Pentahoot question only before any question was played', () => {
        const host = () => pentahootHost({ actionUrl: '/act', labels: {}, questionIds: ['q1', 'q2'] });

        expect(withGame(host, { question: null, statuses: ['ready', 'ready'] }).firstToOpen).toEqual({
            number: 1,
            id: 'q1',
        });
        expect(withGame(host, { question: null, statuses: ['done', 'ready'] }).firstToOpen).toBe(null);
    });

    it('offers the first Tebak question only before any question was shown', () => {
        const host = () =>
            tebakHost({ type: 'tebak_kata', actionUrl: '/act', people: [], labels: {}, questionIds: ['q1', 'q2'] });

        expect(withGame(host, { type: 'tebak_kata', question: null, statuses: ['q', 'q'] }).firstToOpen).toEqual({
            number: 1,
            id: 'q1',
        });
        expect(withGame(host, { type: 'tebak_kata', question: null, statuses: ['w', 'q'] }).firstToOpen).toBe(null);
    });
});

describe('One main action in the middle of Live control (D-10)', () => {
    const host = (game) => {
        const store = realtimeStore({ now: () => 0 });
        store.accept({ ...live, game: { ...live.game, ...game } });
        const made = pentahootHost({ actionUrl: '/act', labels: {}, questionIds: ['q1', 'q2'] });
        made.$store = { realtime: store };

        return made;
    };
    const revealed = {
        question: { id: 'q1', number: 1, status: 'revealed', attempt: 1 },
        statuses: ['revealed', 'ready'],
    };

    it('lets the list open another question once this one is revealed, not while it runs (D-5)', () => {
        expect(host(revealed).canOpen(2)).toBe(true);
        expect(
            host({
                question: { id: 'q1', number: 1, status: 'live', attempt: 1, ends_at: 99_000 },
                statuses: ['live', 'ready'],
            }).canOpen(2),
        ).toBe(false);
    });

    it('names the one main action for the step at hand', () => {
        expect(host({ question: null, statuses: ['ready', 'ready'] }).mainAction).toEqual({
            action: 'start',
            id: 'q1',
            label: 'open_next',
            number: 1,
        });
        expect(host(revealed).mainAction).toEqual({ action: 'advance', id: 'q1', label: 'next' });
        expect(
            host({
                ...revealed,
                statuses: ['done', 'revealed'],
                question: { ...revealed.question, id: 'q2', number: 2 },
            }).mainAction,
        ).toEqual({ action: 'finish' });
        expect(host({ question: null, statuses: ['done', 'ready'] }).mainAction).toBe(null);
        expect(host({ question: null, statuses: ['done', 'done'] }).mainAction).toEqual({ action: 'finish' });
    });
});

describe('U30 the countdown on the Public View', () => {
    it('draws the ring from the time left and grows tense in the last seconds', () => {
        let clock = 0;
        const store = realtimeStore({ now: () => clock });
        store.accept({ ...live, game: { ...live.game, question: { ...live.game.question, duration: 20 } } });
        const screen = pentahootScreen({ labels: {} });
        screen.$store = { realtime: store };

        clock = 10_000;
        expect(screen.ring).toBe(50);
        expect(screen.urgency).toBe('calm');

        clock = 16_500;
        expect(screen.seconds).toBe(4);
        expect(screen.urgency).toBe('soon');

        clock = 18_000;
        expect(screen.urgency).toBe('last');
    });
});

describe('U31 the moments worth a celebration', () => {
    beforeEach(() => vi.useFakeTimers());
    afterEach(() => vi.useRealTimers());

    const revealed = (results) => ({
        ...live,
        version: 4,
        game: {
            ...live.game,
            question: { ...live.game.question, status: 'revealed', results, total_votes: results.length },
        },
    });
    const winner = [{ rank: 1, name: 'Ana Putri', votes: 3 }];

    function watching(component, store) {
        const watchers = [];
        const told = [];
        component.$store = { realtime: store };
        component.$watch = (read, fn) => {
            let last = read();
            watchers.push(() => {
                const next = read();

                if (next !== last) {
                    last = next;
                    fn(next);
                }
            });
        };
        component.$dispatch = (name, detail) => told.push([name, detail]);
        component.init();

        return { told, flush: () => watchers.forEach((run) => run()) };
    }

    it('celebrates rank 1 on the Public View once the E2 pause is over', () => {
        const store = realtimeStore({ now: () => 0 });
        store.accept(live);
        const screen = pentahootScreen({ labels: {} });
        const { told, flush } = watching(screen, store);

        store.accept(revealed(winner));
        flush();
        expect(told).toEqual([]);

        vi.advanceTimersByTime(30_000);
        expect(told).toEqual([['celebrate', { key: 'reveal:q1:1' }]]);
    });

    it('stays quiet when the Public View opens on a reveal it did not see, or with no votes', () => {
        const store = realtimeStore({ now: () => 0 });
        store.accept(revealed(winner));
        const late = pentahootScreen({ labels: {} });
        const first = watching(late, store);
        vi.advanceTimersByTime(30_000);
        expect(first.told).toEqual([]);

        const empty = realtimeStore({ now: () => 0 });
        empty.accept(live);
        const screen = pentahootScreen({ labels: {} });
        const second = watching(screen, empty);
        empty.accept(revealed([]));
        second.flush();
        vi.advanceTimersByTime(30_000);
        expect(second.told).toEqual([]);
    });

    it('celebrates on the phone when the results come in', () => {
        const store = realtimeStore({ now: () => 0 });
        store.accept(live);
        const phone = pentahootPhone({ peopleUrl: '/people', voteUrl: '/vote/__QUESTION__', labels: {} });
        phone.loadPeople = vi.fn();
        const { told, flush } = watching(phone, store);

        store.accept(revealed(winner));
        flush();

        expect(told).toEqual([['celebrate', { key: 'reveal:q1:1' }]]);
    });

    it('celebrates a Tebak winner and the final podium, not a surrender', () => {
        const tebak = (game) => ({
            ...live,
            game: { id: 'g1', type: 'tebak_kata', status: 'running', leaderboard: null, ...game },
        });
        const store = realtimeStore({ now: () => 0 });
        store.accept(tebak({ question: { id: 'k1', status: 'shown' } }));
        const screen = tebakScreen({ types: ['tebak_kata'], labels: {} });
        const { told, flush } = watching(screen, store);

        store.accept({ ...tebak({ question: { id: 'k1', status: 'surrendered' } }), version: 4 });
        flush();
        store.accept({ ...tebak({ question: { id: 'k2', status: 'won' } }), version: 5 });
        flush();
        store.accept({ ...tebak({ status: 'finished', question: null, final: [] }), version: 6 });
        flush();

        expect(told).toEqual([
            ['celebrate', { key: 'won:k2' }],
            ['celebrate', { key: 'final:g1' }],
        ]);
    });
});

describe('U32 Live control stamps a Tebak winner', () => {
    function hostWith({ reduced = false } = {}) {
        const pieces = [];
        vi.stubGlobal('document', {
            querySelector: () => null,
            createElement: () => ({ className: '', style: {}, remove: vi.fn(), animate: vi.fn(() => ({})) }),
        });
        const store = realtimeStore({ now: () => 0 });
        store.accept({
            ...live,
            game: { id: 'g1', type: 'tebak_kata', status: 'running', question: { id: 'k1', status: 'shown' } },
        });
        const host = tebakHost({ type: 'tebak_kata', actionUrl: '/a', people: [], labels: {} });
        let watcher = null;
        host.$store = { realtime: store, motion: { reduced } };
        host.$refs = { winnerConfetti: { clientHeight: 200, append: (el) => pieces.push(el) } };
        host.$watch = (read, fn) => {
            watcher = { read, fn };
        };
        host.init();

        return {
            pieces,
            win() {
                store.accept({
                    ...live,
                    version: 4,
                    game: { id: 'g1', type: 'tebak_kata', status: 'running', question: { id: 'k1', status: 'won' } },
                });
                watcher.fn(watcher.read());
            },
        };
    }

    it('throws a little confetti in the card when the winner is set', () => {
        const { pieces, win } = hostWith();

        win();

        expect(pieces.length).toBeGreaterThan(0);
    });

    it('throws none in the reduced mode', () => {
        const { pieces, win } = hostWith({ reduced: true });

        win();

        expect(pieces).toHaveLength(0);
    });
});

describe('audit PERF-6: the 250 ms clock runs only while a countdown is on screen', () => {
    beforeEach(() => {
        vi.useFakeTimers();
        vi.stubGlobal(
            'fetch',
            vi.fn(async () => answer(200, { people: [] })),
        );
    });
    afterEach(() => vi.useRealTimers());

    function started(component, store) {
        const watchers = [];
        component.$store = { realtime: store };
        component.$dispatch = () => {};
        component.$watch = (read, fn) => {
            let last = read();
            watchers.push(() => {
                const next = read();

                if (next !== last) {
                    last = next;
                    fn(next);
                }
            });
        };
        component.init();

        return () => watchers.forEach((run) => run());
    }

    const waiting = { version: 2, server_now: 0, event: { status: 'open' }, game: null };

    it.each([
        ['phone', () => pentahootPhone({ peopleUrl: '/p', voteUrl: '/v/__QUESTION__', labels: {} })],
        ['Public View', () => pentahootScreen({ labels: {} })],
        ['Live control', () => pentahootHost({ actionUrl: '/a', labels: {} })],
    ])('the %s stays still in the lobby, ticks while live and stops once the question closes', (_, make) => {
        let now = 0;
        const store = realtimeStore({ now: () => now });
        store.accept(waiting);
        const flush = started(make(), store);
        expect(vi.getTimerCount()).toBe(0);

        store.accept(live);
        flush();
        expect(vi.getTimerCount()).toBe(1);

        now = 40_000;
        vi.advanceTimersByTime(250);
        flush();
        expect(vi.getTimerCount()).toBe(0);
    });
});
