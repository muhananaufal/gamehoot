import { describe, expect, it } from 'vitest';
import { LIVE_REFRESH_MS, liveRefreshPlan } from '../../resources/js/live-region.js';

describe('U12 live refresh of host pages', () => {
    it('does nothing for the version the page was rendered with or an older one', () => {
        expect(liveRefreshPlan({ rendered: 7, version: 7, typing: false })).toEqual({ fetch: false, wait: false });
        expect(liveRefreshPlan({ rendered: 7, version: 5, typing: false })).toEqual({ fetch: false, wait: false });
    });

    it('fetches the page again once a newer version arrives (F15)', () => {
        expect(liveRefreshPlan({ rendered: 7, version: 8, typing: false })).toEqual({ fetch: true, wait: false });
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

    it('waits about a second between fetches, so a burst of joins makes one request', () => {
        expect(LIVE_REFRESH_MS).toBeGreaterThanOrEqual(500);
        expect(LIVE_REFRESH_MS).toBeLessThanOrEqual(2_000);
    });
});
