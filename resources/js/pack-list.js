// U14: the pack list beside the pack editor stays light with many packs (measured: 1,000 packs).
// Items carry no Alpine bindings: one function hides the items that do not match the search or
// the game type filter, orders them, and folds groups. Folds, filter and order are remembered
// per browser (U4); while searching, a group that holds results opens by itself.

const KEY = 'pentahoot-packs';
const SORTS = ['name', 'changed'];
const DEFAULTS = { collapsed: [], type: 'all', sort: 'name' };
const collator = new Intl.Collator(undefined, { numeric: true, sensitivity: 'base' });

export function matchesPack(pack, { q, type }) {
    const words = q.trim().toLowerCase().split(/\s+/).filter(Boolean);
    const title = pack.title.toLowerCase();

    return (type === 'all' || pack.type === type) && words.every((word) => title.includes(word));
}

/** By name (natural order), or the most recently changed first. */
export function orderPacks(packs, sort) {
    return [...packs].sort((a, b) => (sort === 'changed' ? b.changed - a.changed : collator.compare(a.title, b.title)));
}

export function groupOpen(type, { collapsed, q, hasMatch }) {
    return q.trim() !== '' ? hasMatch : !collapsed.includes(type);
}

export function loadPackState(storage) {
    try {
        const stored = JSON.parse(storage.getItem(KEY) ?? '{}') ?? {};

        return {
            collapsed: Array.isArray(stored.collapsed) ? stored.collapsed.filter((t) => typeof t === 'string') : [],
            type: typeof stored.type === 'string' && stored.type !== '' ? stored.type : DEFAULTS.type,
            sort: SORTS.includes(stored.sort) ? stored.sort : DEFAULTS.sort,
        };
    } catch {
        return { ...DEFAULTS, collapsed: [] };
    }
}

export function savePackState(storage, { collapsed, type, sort }) {
    try {
        storage.setItem(KEY, JSON.stringify({ collapsed, type, sort }));
    } catch {
        // Blocked storage: the list works, it just is not remembered.
    }
}

/**
 * The Alpine component on <nav id="pack-list">. Groups are [data-group="<type>"] holding a
 * [data-items] list of [data-pack data-type data-changed] items: links that open a pack, or
 * buttons that add it as a game (U23).
 */
export function packList({ storage = globalThis.localStorage } = {}) {
    return {
        q: '',
        ...loadPackState(storage),

        init() {
            this.apply();
            this.$watch(
                () => [this.q, this.type, this.sort, this.collapsed.join(',')].join('|'),
                () => this.apply(),
            );
        },

        isOpen(type) {
            return !this.collapsed.includes(type);
        },

        toggle(type) {
            this.collapsed = this.isOpen(type) ? [...this.collapsed, type] : this.collapsed.filter((t) => t !== type);
        },

        apply() {
            const state = { q: this.q, type: this.type };

            for (const group of this.$root.querySelectorAll('[data-group]')) {
                const list = group.querySelector('[data-items]');
                const items = [...list.querySelectorAll('[data-pack]')].map((el) => ({
                    el,
                    // The first span holds the title; the second the question count.
                    title: (el.querySelector('span')?.textContent ?? '').trim(),
                    type: el.dataset.type,
                    changed: Number(el.dataset.changed ?? 0),
                }));
                let shown = 0;

                for (const item of orderPacks(items, this.sort)) {
                    item.el.hidden = !matchesPack(item, state);
                    shown += item.el.hidden ? 0 : 1;
                    list.appendChild(item.el);
                }

                group.hidden = shown === 0;
                list.hidden = !groupOpen(group.dataset.group, {
                    collapsed: this.collapsed,
                    q: this.q,
                    hasMatch: shown > 0,
                });
            }

            savePackState(storage, this);
        },
    };
}
