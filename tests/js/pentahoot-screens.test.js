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
