import { readFileSync } from 'node:fs';
import { describe, expect, it } from 'vitest';

// U16: a button that is sending keeps its width and shows a spinner in its own ink colour.
const css = readFileSync(new URL('../../resources/css/app.css', import.meta.url), 'utf8');

function ruleOf(selector) {
    const start = css.indexOf(`${selector} {`);

    return start === -1 ? '' : css.slice(start, css.indexOf('}', start));
}

describe('U16 busy buttons', () => {
    it('hides the label without changing the width', () => {
        expect(ruleOf("button[aria-busy='true']")).toMatch(/color:\s*transparent/);
    });

    it('draws a spinning ring in the ink colour of the button variant', () => {
        const spinner = ruleOf("button[aria-busy='true']::after");

        expect(spinner).toMatch(/animation:\s*busy-spin/);
        expect(spinner).toContain('var(--busy-ink');
    });
});
