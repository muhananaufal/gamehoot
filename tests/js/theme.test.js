import { describe, expect, it } from 'vitest';
import { nextTheme, resolveTheme } from '../../resources/js/theme.js';

describe('F21 theme choice', () => {
    it('uses the theme the viewer picked', () => {
        expect(resolveTheme('light', true)).toBe('light');
        expect(resolveTheme('dark', false)).toBe('dark');
    });

    it('follows the system setting when nothing valid was picked', () => {
        expect(resolveTheme(null, true)).toBe('dark');
        expect(resolveTheme(null, false)).toBe('light');
        expect(resolveTheme('purple', true)).toBe('dark');
    });

    it('toggles between light and dark', () => {
        expect(nextTheme('dark')).toBe('light');
        expect(nextTheme('light')).toBe('dark');
    });
});
