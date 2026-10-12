// U12: host pages whose data other people change (Names, Games, Activity log) refresh one
// region when the event's realtime version moves on (F15). The page fetches itself and swaps
// only the region with the same id: no Livewire, no extra endpoint, and server rendering stays
// the one source of the markup.

/** At most one fetch per this many milliseconds, so a burst of changes makes one request. */
export const LIVE_REFRESH_MS = 2_000;

/**
 * F25: claims move the joined counter, not the version. A region that shows claims (Names)
 * catches up at most this often, so a room scanning the QR code does not reload it each time.
 */
export const CLAIMS_REFRESH_MS = 10_000;

/**
 * Whether to fetch now, wait for the host (typing, hidden tab), or do nothing.
 *
 * @param {{ rendered: number, version: number, typing: boolean, hidden?: boolean, claimed?: boolean }} state
 *   claimed: a claim arrived since the region was drawn (F25)
 */
export function liveRefreshPlan({ rendered, version, typing, hidden = false, claimed = false }) {
    if (version <= rendered && !claimed) {
        return { fetch: false, wait: false };
    }

    if (typing || hidden) {
        return { fetch: false, wait: true };
    }

    return { fetch: true, wait: false };
}

const FIELD_TAGS = ['INPUT', 'TEXTAREA', 'SELECT'];

/**
 * The Alpine component on the region; the region needs an id that the fetched page shares.
 *
 * @param {{ claims?: boolean }} options claims: the region shows who has joined (F25)
 */
export function liveRegion({ claims = false } = {}) {
    return {
        rendered: -1,
        claimed: false,
        timer: null,
        // Audit FE-2: removed in destroy(); a region left behind by an Unpoly page change
        // must not keep fetching the page that replaced it.
        onVisible: null,
        stopped: false,

        init() {
            this.rendered = this.$store.realtime?.version ?? -1;
            this.$watch(
                () => this.$store.realtime?.version,
                () => this.schedule(),
            );

            if (claims) {
                this.$watch(
                    () => this.$store.realtime?.snapshot?.lobby?.joined,
                    () => {
                        this.claimed = true;
                        this.schedule(CLAIMS_REFRESH_MS);
                    },
                );
            }

            // Catch up once the host stops typing or comes back to the tab.
            this.$root.addEventListener('focusout', () => this.schedule());
            this.onVisible = () => this.schedule();
            document.addEventListener('visibilitychange', this.onVisible);
        },

        destroy() {
            this.stopped = true;
            clearTimeout(this.timer);
            this.timer = null;
            document.removeEventListener('visibilitychange', this.onVisible);
        },

        typing() {
            const active = document.activeElement;

            return active !== null && this.$root.contains(active) && FIELD_TAGS.includes(active.tagName);
        },

        schedule(delay = LIVE_REFRESH_MS) {
            if (this.timer !== null || this.stopped) {
                return;
            }

            this.timer = setTimeout(() => {
                this.timer = null;
                this.refresh();
            }, delay);
        },

        async refresh() {
            if (this.stopped) {
                return;
            }

            const version = this.$store.realtime?.version ?? -1;
            const plan = liveRefreshPlan({
                rendered: this.rendered,
                version,
                typing: this.typing(),
                hidden: document.visibilityState === 'hidden',
                claimed: this.claimed,
            });

            if (!plan.fetch) {
                return;
            }

            try {
                const response = await fetch(window.location.href, {
                    credentials: 'same-origin',
                    headers: { Accept: 'text/html' },
                });

                if (!response.ok || this.stopped) {
                    return;
                }

                const page = new DOMParser().parseFromString(await response.text(), 'text/html');
                const next = page.getElementById(this.$root.id);

                // The host may have started typing while the request was out.
                if (next === null || this.typing()) {
                    this.schedule();

                    return;
                }

                // Alpine starts the components inside the new markup (a table view reads its
                // search, filter and sort back from the URL).
                this.$root.innerHTML = next.innerHTML;
                this.rendered = version;
                this.claimed = false;
            } catch {
                // The next version change tries again.
            }
        },
    };
}
