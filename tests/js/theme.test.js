import { describe, expect, it } from 'vitest';
import { nextTheme, resolveTheme, themeToggle } from '../../resources/js/theme.js';

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

describe('U17 theme switch icon', () => {
    const fakeRoot = (theme) => ({ dataset: { theme } });
    const memory = () => {
        const saved = {};

        return { saved, setItem: (key, value) => (saved[key] = value) };
    };

    it('knows the current theme, so the icon can show where the switch goes', () => {
        expect(themeToggle({ root: fakeRoot('dark'), storage: memory() }).dark).toBe(true);
        expect(themeToggle({ root: fakeRoot('light'), storage: memory() }).dark).toBe(false);
    });

    it('flips the page theme, its own state and the stored choice together', () => {
        const root = fakeRoot('light');
        const storage = memory();
        const toggle = themeToggle({ root, storage });

        toggle.toggle();

        expect(root.dataset.theme).toBe('dark');
        expect(toggle.dark).toBe(true);
        expect(storage.saved['pentahoot-theme']).toBe('dark');
    });

    it('still switches when the browser blocks storage', () => {
        const root = fakeRoot('dark');
        const toggle = themeToggle({
            root,
            storage: {
                setItem() {
                    throw new Error('blocked');
                },
            },
        });

        toggle.toggle();

        expect(root.dataset.theme).toBe('light');
    });
});
