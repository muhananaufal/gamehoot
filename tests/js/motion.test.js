import { readFileSync } from 'node:fs';
import { describe, expect, it } from 'vitest';

// U8: motion only at key moments, 100-300 ms, and none with prefers-reduced-motion (MDN).
const css = readFileSync(new URL('../../resources/css/app.css', import.meta.url), 'utf8');

const reduced = css.slice(css.indexOf('@media (prefers-reduced-motion: reduce)'));
const reducedBlock = reduced.slice(0, reduced.indexOf('\n}') + 2);

function ruleOf(selector) {
    const start = css.indexOf(`${selector} {`);

    return start === -1 ? '' : css.slice(start, css.indexOf('}', start));
}

const KEY_MOMENTS = ['.pop-in', '.row-enter', '.podium-step', '.podium-name', 'dialog[open]'];

describe('U8 key-moment motion', () => {
    it.each(KEY_MOMENTS)('%s animates in 100-300 ms', (selector) => {
        const match = ruleOf(selector).match(/animation:\s*[\w-]+\s+(\d+)ms/);

        expect(match, `${selector} has an animation with a duration in ms`).not.toBeNull();
        expect(Number(match[1])).toBeGreaterThanOrEqual(100);
        expect(Number(match[1])).toBeLessThanOrEqual(300);
    });

    it.each([...KEY_MOMENTS, '.mascot-idle'])('%s stops with prefers-reduced-motion', (selector) => {
        expect(reducedBlock).toContain(selector);
        expect(reducedBlock).toMatch(/animation:\s*none/);
    });

    it('raises the podium from the third step to the first', () => {
        const delay = (step) => Number(ruleOf(`.podium-step-${step}`).match(/animation-delay:\s*(\d+)ms/)?.[1] ?? -1);

        expect(delay(3)).toBeGreaterThanOrEqual(0);
        expect(delay(2)).toBeGreaterThan(delay(3));
        expect(delay(1)).toBeGreaterThan(delay(2));
    });
});
