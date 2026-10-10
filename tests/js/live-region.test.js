import { afterEach, describe, expect, it, vi } from 'vitest';
import { CLAIMS_REFRESH_MS, LIVE_REFRESH_MS, liveRefreshPlan, liveRegion } from '../../resources/js/live-region.js';

describe('U12 live refresh of host pages', () => {
    it('does nothing for the version the page was rendered with or an older one', () => {
        expect(liveRefreshPlan({ rendered: 7, version: 7, typing: false })).toEqual({ fetch: false, wait: false });
        expect(liveRefreshPlan({ rendered: 7, version: 5, typing: false })).toEqual({ fetch: false, wait: false });
    });

    it('fetches the page again once a newer version arrives (F15)', () => {
        expect(liveRefreshPlan({ rendered: 7, version: 8, typing: false })).toEqual({ fetch: true, wait: false });
    });

    it('fetches after a claim even though claims keep the version (F25)', () => {
        expect(liveRefreshPlan({ rendered: 7, version: 7, typing: false, claimed: true })).toEqual({
            fetch: true,
            wait: false,
        });
    });

    it('waits while the host is typing in the region, so nothing they type is lost', () => {
        expect(liveRefreshPlan({ rendered: 7, version: 9, typing: true })).toEqual({ fetch: false, wait: true });
    });

    it('waits while the tab is hidden and catches up once it is shown', () => {
        expect(liveRefreshPlan({ rendered: 7, version: 9, typing: false, hidden: true })).toEqual({
            fetch: false,
            wait: true,
        });
    });

    it('waits about a second between fetches, so a burst of changes makes one request', () => {
        expect(LIVE_REFRESH_MS).toBeGreaterThanOrEqual(500);
        expect(LIVE_REFRESH_MS).toBeLessThanOrEqual(2_000);
    });

    it('catches up with claims at most every ten seconds, so a room scanning the QR code does not reload the list each time (F25)', () => {
        expect(CLAIMS_REFRESH_MS).toBeGreaterThanOrEqual(10_000);
    });
});

describe('audit FE-2 a region that leaves the page', () => {
    afterEach(() => {
        vi.unstubAllGlobals();
        vi.useRealTimers();
    });

    it('stops following the tab and never fetches again once destroyed', async () => {
        vi.useFakeTimers();
        const listeners = new Set();
        vi.stubGlobal('document', {
            visibilityState: 'visible',
            activeElement: null,
            addEventListener: (type, listener) => type === 'visibilitychange' && listeners.add(listener),
            removeEventListener: (type, listener) => type === 'visibilitychange' && listeners.delete(listener),
        });
        const fetch = vi.fn();
        vi.stubGlobal('fetch', fetch);

        const region = liveRegion();
        Object.assign(region, {
            $store: { realtime: { version: 3 } },
            $watch: () => {},
            $root: { addEventListener: () => {}, contains: () => false },
        });
        region.init();
        expect(listeners.size).toBe(1);

        region.destroy();
        expect(listeners.size).toBe(0);

        // A version change that arrives late still finds a stopped region.
        region.$store.realtime.version = 4;
        region.schedule();
        await vi.advanceTimersByTimeAsync(LIVE_REFRESH_MS * 2);

        expect(fetch).not.toHaveBeenCalled();
    });
});
