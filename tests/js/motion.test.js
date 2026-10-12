import { readFileSync } from 'node:fs';
import { describe, expect, it } from 'vitest';
import { EASE, MOTION } from '../../resources/js/motion.js';

// U8, U27: motion through tokens, 100-400 ms, and none in the reduced mode (MDN, WCAG 2.2.2).
const css = readFileSync(new URL('../../resources/css/app.css', import.meta.url), 'utf8');

function blockAfter(marker) {
    const start = css.indexOf(marker);

    if (start === -1) {
        return '';
    }

    const rest = css.slice(start);

    return rest.slice(0, rest.indexOf('\n}') + 2);
}

const tokens = blockAfter(':root {');
const reducedBlock = blockAfter(":root[data-motion='reduced'] {");

function ruleOf(selector) {
    const start = css.indexOf(`${selector} {`);

    return start === -1 ? '' : css.slice(start, css.indexOf('}', start));
}

/** The milliseconds of a token, read from the CSS where it is defined. */
function tokenMs(name) {
    const match = tokens.match(new RegExp(`${name}:\\s*(\\d+)ms`));

    return match ? Number(match[1]) : NaN;
}

const KEY_MOMENTS = ['.pop-in', '.row-enter', '.podium-step', '.podium-name', 'dialog[open]'];

describe('U27 motion tokens in CSS', () => {
    it.each([
        ['--motion-instant', 'instant'],
        ['--motion-quick', 'quick'],
        ['--motion-base', 'base'],
        ['--motion-slow', 'slow'],
    ])('defines %s with the same value as motion.js', (name, key) => {
        expect(tokenMs(name)).toBe(MOTION[key]);
    });

    it('defines the easings of the app', () => {
        expect(tokens).toMatch(/--ease-out:\s*cubic-bezier\(0\.2, 0, 0, 1\)/);
        expect(tokens).toMatch(/--ease-in:\s*cubic-bezier\(0\.4, 0, 1, 1\)/);
        expect(tokens).toMatch(/--ease-overshoot:\s*cubic-bezier\(0\.34, 1\.4, 0\.64, 1\)/);
        expect(tokens).toMatch(/--ease-spring:\s*linear\(/);
    });

    it.each(['out', 'in', 'overshoot', 'spring', 'bounce'])('gives JS the same --ease-%s as CSS', (name) => {
        expect(tokens).toContain(`--ease-${name}: ${EASE[name]};`);
    });

    it('writes no durations or delays by hand outside the tokens', () => {
        const rest = css.replace(tokens, '');
        const manual = rest.match(/(?:transition|animation)[^;{]*?\b[1-9]\d*(?:\.\d+)?m?s\b/g) ?? [];

        expect(manual).toEqual([]);
    });
});

describe('U8 key-moment motion', () => {
    it.each(KEY_MOMENTS)('%s animates in 100-400 ms through a token', (selector) => {
        const match = ruleOf(selector).match(/animation:\s*[\w-]+\s+var\((--motion-[a-z]+)\)/);

        expect(match, `${selector} has an animation with a motion token`).not.toBeNull();
        expect(tokenMs(match[1])).toBeGreaterThanOrEqual(100);
        expect(tokenMs(match[1])).toBeLessThanOrEqual(400);
    });

    it.each(KEY_MOMENTS)('%s stops in the reduced mode', (selector) => {
        expect(reducedBlock).toContain(selector);
        expect(reducedBlock).toMatch(/animation:\s*none/);
    });

    it('no longer floats the mascot forever: Penta moves in bursts of at most five seconds (U29, WCAG 2.2.2)', () => {
        expect(css).not.toContain('mascot-idle');
        expect(css).not.toContain('mascot-float');
        expect(css).not.toContain('--motion-float');
    });

    it('raises the podium from the third step to the first', () => {
        const delay = (step) =>
            ruleOf(`.podium-step-${step}`)
                .match(/animation-delay:\s*([^;]+);/)?.[1]
                ?.trim();

        expect(delay(3)).toBe('0s');
        expect(delay(2)).toBe('var(--motion-podium-step)');
        expect(delay(1)).toBe('calc(var(--motion-podium-step) * 2)');
    });
});

describe('U8 leaving and pressing', () => {
    it('lets dialogs leave faster than they come, also when closed with Escape', () => {
        const leave = ruleOf('dialog');

        expect(leave).toContain('allow-discrete');
        expect(leave).toMatch(/opacity\s+var\(--motion-instant\)/);
        expect(ruleOf('dialog:not([open])')).toMatch(/opacity:\s*0/);
        expect(ruleOf('dialog[open]')).toMatch(/var\(--motion-base\)/);
    });

    it('gives buttons a small press', () => {
        expect(ruleOf(":is(button, a[class*='rounded-control']):active:not(:disabled)")).toMatch(/scale:\s*0\.97/);
    });

    it('stops leaving and pressing in the reduced mode', () => {
        expect(reducedBlock).toMatch(/transition:\s*none/);
        expect(reducedBlock).toContain('scale: none');
    });

    it('no longer reads the device setting in CSS: the inline script decides the mode (U27)', () => {
        expect(css).not.toContain('@media (prefers-reduced-motion');
    });
});

describe('U13 modals sit in the middle of the screen', () => {
    it('centres Unpoly modals vertically like the native dialogs, still scrolling when taller than the screen', () => {
        expect(ruleOf('up-modal up-modal-box')).toMatch(/margin-block:\s*auto/);
    });
});

describe('U13 one backdrop for every dialog and modal', () => {
    it('draws the native dialogs and the Unpoly modals behind the same backdrop', () => {
        // up-modal > up-modal-backdrop outweighs unpoly.css, which loads later.
        expect(ruleOf('dialog::backdrop,\nup-modal > up-modal-backdrop')).toMatch(
            /background:\s*color-mix\(in oklab, var\(--color-nav\) 70%, transparent\)/,
        );
    });
});
