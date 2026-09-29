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

export function themeToggle() {
    return {
        toggle() {
            const theme = nextTheme(document.documentElement.dataset.theme);
            document.documentElement.dataset.theme = theme;

            try {
                localStorage.setItem(THEME_STORAGE_KEY, theme);
            } catch {
                // Storage can be blocked (private mode); the choice then lasts for this page only.
            }
        },
    };
}
