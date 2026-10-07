// D-9: Add games picks several packs at once. Each picked pack shows the number of its turn in
// the pick, the Selected row lists them in that order (each can be taken out again), and the
// games are added in that order.

/** A copy of the pick with `id` added at the end, or taken out when it was in. */
export function togglePick(picked, id) {
    return picked.includes(id) ? picked.filter((item) => item !== id) : [...picked, id];
}

/** The Alpine component of the Add games dialog. `labels` names the button: { one, other } with __N__. */
export function packPicker({ labels }) {
    return {
        picked: [],
        titles: {},

        // Not "toggle": the pack list inside has its own toggle(type) for folding its groups.
        pick(id, title) {
            this.titles[id] = title;
            this.picked = togglePick(this.picked, id);
        },

        /** 1, 2, 3 in the order picked; 0 when not picked. */
        orderOf(id) {
            return this.picked.indexOf(id) + 1;
        },

        chips() {
            return this.picked.map((id, index) => ({ id, number: index + 1, title: this.titles[id] ?? '' }));
        },

        addLabel() {
            const text = this.picked.length === 1 ? labels.one : labels.other;

            return text.replace('__N__', String(this.picked.length));
        },

        clear() {
            this.picked = [];
        },
    };
}
