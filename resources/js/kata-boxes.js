import { answerBoxes } from './answer-boxes.js';

// Question form for Tebak Kata: live preview of the answer boxes (E5) and the choice of
// boxes shown from the start. The server validates the same rules again.
export function kataBoxes({ answer = '', open = [] } = {}) {
    return {
        answer,
        open: [...open],

        get boxes() {
            return answerBoxes(this.answer);
        },

        isOpen(box) {
            return this.open.includes(box);
        },

        toggle(box) {
            this.open = this.isOpen(box)
                ? this.open.filter((b) => b !== box)
                : [...this.open, box].sort((a, b) => a - b);
        },

        // Boxes that no longer exist after the answer changes are dropped.
        pruneOpen() {
            const count = this.boxes?.count ?? 0;
            this.open = this.open.filter((b) => b < count);
        },
    };
}
