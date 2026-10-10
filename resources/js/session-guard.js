// C-8: keeps a host session alive while a host page is open, and warns before the absolute limit
// (8 h since sign in) ends it. Live control can sit for hours without a request of its own while
// the socket brings every change, so without the ping the idle session would end unseen.

/** How often an open host page tells the server it is still in use. */
export const SESSION_PING_MS = 10 * 60 * 1_000;

/** The warning shows this long before the session ends. */
export const SESSION_WARN_MS = 10 * 60 * 1_000;

/**
 * 'active', 'ending' (show the warning) or 'ended'.
 *
 * @param {number} now milliseconds
 * @param {number} endsAt milliseconds
 */
export function sessionPhase(now, endsAt) {
    if (now >= endsAt) {
        return 'ended';
    }

    return endsAt - now <= SESSION_WARN_MS ? 'ending' : 'active';
}

/**
 * The Alpine component of the warning in the host layout.
 *
 * @param {{ endsAt: number, url: string }} options
 */
export function sessionGuard({ endsAt, url }) {
    return {
        endsAt,
        now: Date.now(),
        pingTimer: null,
        clockTimer: null,

        init() {
            this.pingTimer = setInterval(() => this.ping(), SESSION_PING_MS);
            this.clockTimer = setInterval(() => {
                this.now = Date.now();
            }, 30_000);
        },

        destroy() {
            clearInterval(this.pingTimer);
            clearInterval(this.clockTimer);
        },

        get phase() {
            return sessionPhase(this.now, this.endsAt);
        },

        /** The local time the session ends, such as 21:30. */
        get endsAtLabel() {
            return new Intl.DateTimeFormat(undefined, { hour: '2-digit', minute: '2-digit' }).format(this.endsAt);
        },

        async ping() {
            try {
                const response = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });

                // The session is gone (limit reached, signed out elsewhere): show the sign in page.
                if (response.status === 401 || response.status === 419) {
                    window.location.reload();

                    return;
                }

                if (response.ok) {
                    const body = await response.json();
                    this.endsAt = Number.isFinite(body.ends_at) ? body.ends_at : this.endsAt;
                }
            } catch {
                // Offline for a moment; the next ping tries again.
            }

            this.now = Date.now();
        },
    };
}
