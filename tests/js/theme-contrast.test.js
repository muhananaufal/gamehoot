import { readFileSync } from 'node:fs';
import { describe, expect, it } from 'vitest';

// U11, F21: the theme tokens are measured, not judged by eye. Light values come from @theme,
// dark values override them under [data-theme='dark'].
const css = readFileSync(new URL('../../resources/css/app.css', import.meta.url), 'utf8');

function tokensIn(block) {
    return Object.fromEntries([...block.matchAll(/--color-([a-z0-9-]+):\s*(#[0-9a-f]{6})/gi)].map((m) => [m[1], m[2]]));
}

const light = tokensIn(css.slice(css.indexOf('@theme'), css.indexOf('@layer base')));
const dark = { ...light, ...tokensIn(css.slice(css.indexOf("[data-theme='dark']"), css.indexOf('html {'))) };

function luminance(hex) {
    const [r, g, b] = [1, 3, 5].map((i) => parseInt(hex.slice(i, i + 2), 16) / 255);
    const lin = (c) => (c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4);

    return 0.2126 * lin(r) + 0.7152 * lin(g) + 0.0722 * lin(b);
}

export function contrast(a, b) {
    const [hi, lo] = [luminance(a), luminance(b)].sort((x, y) => y - x);

    return (hi + 0.05) / (lo + 0.05);
}

// Text pairs need 4.5:1 (WCAG AA for normal text).
const textPairs = [
    ['ink', 'canvas'],
    ['ink', 'surface'],
    ['muted', 'surface'],
    ['muted', 'canvas'],
    ['link', 'surface'],
    ['link', 'canvas'],
    ['on-danger', 'surface'],
    ['on-danger', 'danger'],
    ['on-success', 'success'],
    ['on-warning', 'warning'],
    ['on-accent', 'accent'],
    ['on-subtle', 'subtle'],
    ['nav-ink', 'nav'],
    ['nav-muted', 'nav'],
    ['nav-label', 'nav'],
    ['nav-ink', 'nav-active'],
];

describe.each([
    ['light', light],
    ['dark', dark],
])('%s theme', (_, tokens) => {
    it.each(textPairs)('%s on %s reaches 4.5:1', (fg, bg) => {
        expect(tokens[fg], `--color-${fg}`).toBeDefined();
        expect(contrast(tokens[fg], tokens[bg])).toBeGreaterThanOrEqual(4.5);
    });

    it('draws sidebar icons at 3:1 at least (non-text contrast)', () => {
        expect(contrast(tokens['nav-icon'], tokens.nav)).toBeGreaterThanOrEqual(3);
    });
});

it('gives the light theme a light sidebar and the dark theme a dark one (U1, U11)', () => {
    expect(luminance(light.nav)).toBeGreaterThan(0.6);
    expect(luminance(dark.nav)).toBeLessThan(0.05);
});
