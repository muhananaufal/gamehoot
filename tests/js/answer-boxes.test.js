import { describe, expect, it } from 'vitest';
import { answerBoxes } from '../../resources/js/answer-boxes.js';

// Same cases as tests/Unit/Games/TebakKata/AnswerBoxesTest.php: both sides must agree (E5).
const render = (result) =>
    result.words.map((word) => word.map((c) => (c.box === null ? `(${c.char})` : `${c.char}${c.box}`)));

describe('E5 answer boxes', () => {
    it('gives every letter one box, in capitals', () => {
        const result = answerBoxes('paris');
        expect(result.count).toBe(5);
        expect(render(result)).toEqual([['P0', 'A1', 'R2', 'I3', 'S4']]);
    });

    it('splits words on spaces and numbers boxes across words', () => {
        const result = answerBoxes('new  york ');
        expect(result.count).toBe(7);
        expect(render(result)).toEqual([
            ['N0', 'E1', 'W2'],
            ['Y3', 'O4', 'R5', 'K6'],
        ]);
    });

    it('gives digits a box', () => {
        expect(answerBoxes('3 Idiots').count).toBe(7);
    });

    it('shows punctuation without a box', () => {
        expect(render(answerBoxes("R&B-Jl./O'k"))).toEqual([
            ['R0', '(&)', 'B1', '(-)', 'J2', 'L3', '(.)', '(/)', 'O4', "(')", 'K5'],
        ]);
    });

    it('treats a typographic apostrophe as a plain one', () => {
        expect(render(answerBoxes('O’k'))).toEqual([['O0', "(')", 'K1']]);
    });

    it('keeps accented letters as one box, composed or decomposed', () => {
        expect(answerBoxes('café').count).toBe(4);
        expect(render(answerBoxes('café'))).toEqual(render(answerBoxes('café')));
    });

    it.each(['hello!', 'a+b', 'emoji 😀', 'tab\tchar', '', '   ', '- / .'])('rejects %j', (answer) => {
        expect(answerBoxes(answer)).toBeNull();
    });
});
