import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { pentahootHost, pentahootPhone } from '../../resources/js/pentahoot-screens.js';
import { realtimeStore } from '../../resources/js/realtime.js';
import { tebakHost } from '../../resources/js/tebak-screens.js';

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
