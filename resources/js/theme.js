// F21: host panel and phones follow the system theme until the viewer picks one.
// The inline script in components/theme-script.blade.php applies the same rule before first paint.
export const THEME_STORAGE_KEY = 'pentahoot-theme';

export function resolveTheme(stored, prefersDark) {
    if (stored === 'light' || stored === 'dark') {
        return stored;
    }

    return prefersDark ? 'dark' : 'light';
}

export function nextTheme(current) {
    return current === 'dark' ? 'light' : 'dark';
}

/**
 * U17: the switch keeps the current theme, so its icon shows where a press goes
 * (sun while dark, moon while light).
 */
export function themeToggle({ root = document.documentElement, storage = globalThis.localStorage } = {}) {
    return {
        dark: root.dataset.theme === 'dark',

        toggle() {
            const theme = nextTheme(root.dataset.theme);
            root.dataset.theme = theme;
            this.dark = theme === 'dark';

            try {
                storage.setItem(THEME_STORAGE_KEY, theme);
            } catch {
                // Storage can be blocked (private mode); the choice then lasts for this page only.
            }
        },
    };
}
