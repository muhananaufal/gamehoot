import { describe, expect, it } from 'vitest';
import { SESSION_PING_MS, SESSION_WARN_MS, sessionPhase } from '../../resources/js/session-guard.js';

describe('C-8 host session guard', () => {
    const endsAt = Date.UTC(2026, 9, 15, 9, 0, 0);

    it('stays quiet until ten minutes before the session ends', () => {
        expect(sessionPhase(endsAt - SESSION_WARN_MS - 1, endsAt)).toBe('active');
    });

    it('warns during the last ten minutes', () => {
        expect(sessionPhase(endsAt - SESSION_WARN_MS, endsAt)).toBe('ending');
        expect(sessionPhase(endsAt - 1, endsAt)).toBe('ending');
    });

    it('knows when the session has ended', () => {
        expect(sessionPhase(endsAt, endsAt)).toBe('ended');
    });

    it('pings well inside the 120 minute idle lifetime of the server', () => {
        expect(SESSION_PING_MS).toBeLessThanOrEqual(30 * 60 * 1_000);
    });
});
