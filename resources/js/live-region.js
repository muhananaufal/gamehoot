// U12: host pages whose data other people change (Names, Games, Activity log) refresh one
// region when the event's realtime version moves on (F15). The page fetches itself and swaps
// only the region with the same id: no Livewire, no extra endpoint, and server rendering stays
// the one source of the markup.

/** At most one fetch per this many milliseconds, so a burst of joins makes one request. */
export const LIVE_REFRESH_MS = 2_000;

/**
 * Whether to fetch now, wait for the host (typing, hidden tab), or do nothing.
 *
 * @param {{ rendered: number, version: number, typing: boolean, hidden?: boolean }} state
 */
export function liveRefreshPlan({ rendered, version, typing, hidden = false }) {
    if (version <= rendered) {
        return { fetch: false, wait: false };
    }

    if (typing || hidden) {
        return { fetch: false, wait: true };
    }

    return { fetch: true, wait: false };
}

const FIELD_TAGS = ['INPUT', 'TEXTAREA', 'SELECT'];

/** The Alpine component on the region; the region needs an id that the fetched page shares. */
export function liveRegion() {
    return {
        rendered: -1,
        timer: null,

        init() {
            this.rendered = this.$store.realtime?.version ?? -1;
            this.$watch(
                () => this.$store.realtime?.version,
                () => this.schedule(),
            );
            // Catch up once the host stops typing or comes back to the tab.
            this.$root.addEventListener('focusout', () => this.schedule());
            document.addEventListener('visibilitychange', () => this.schedule());
        },

        typing() {
            const active = document.activeElement;

            return active !== null && this.$root.contains(active) && FIELD_TAGS.includes(active.tagName);
        },

        schedule() {
            if (this.timer !== null) {
                return;
            }

            this.timer = setTimeout(() => {
                this.timer = null;
                this.refresh();
            }, LIVE_REFRESH_MS);
        },

        async refresh() {
            const version = this.$store.realtime?.version ?? -1;
            const plan = liveRefreshPlan({
                rendered: this.rendered,
                version,
                typing: this.typing(),
                hidden: document.visibilityState === 'hidden',
            });

            if (!plan.fetch) {
                return;
            }

            try {
                const response = await fetch(window.location.href, {
                    credentials: 'same-origin',
                    headers: { Accept: 'text/html' },
                });

                if (!response.ok) {
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
            } catch {
                // The next version change tries again.
            }
        },
    };
}
