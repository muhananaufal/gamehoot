// E5: splits a Tebak Kata answer into boxes. Mirrors App\Games\TebakKata\AnswerBoxes; the server
// validates, this copy only drives the live preview in the question form.
const PUNCTUATION = new Set(['-', "'", '.', '/', '&']);
const PUNCTUATION_ALIASES = { '’': "'", '‘': "'", '–': '-' };
const BOX = /^[\p{L}\p{N}]$/u;

/**
 * @param {string} answer
 * @returns {{ words: Array<Array<{ char: string, box: number|null }>>, count: number } | null}
 *   null when the answer has an unsupported character or no box at all.
 */
export function answerBoxes(answer) {
    const text = Array.from(answer.normalize('NFC'))
        .map((char) => PUNCTUATION_ALIASES[char] ?? char)
        .join('')
        .toUpperCase();

    const words = [];
    let count = 0;

    for (const word of text.replace(/^ +| +$/g, '').split(/ +/)) {
        if (word === '') {
            continue;
        }

        const cells = [];

        for (const char of Array.from(word)) {
            if (BOX.test(char)) {
                cells.push({ char, box: count++ });
            } else if (PUNCTUATION.has(char)) {
                cells.push({ char, box: null });
            } else {
                return null;
            }
        }

        words.push(cells);
    }

    return count === 0 ? null : { words, count };
}
