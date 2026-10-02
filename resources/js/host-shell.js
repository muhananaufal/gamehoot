// F20, U1: the host layout. On phones the sidebar is a drawer (menuOpen); on wide screens it can
// be narrowed to icons, remembered per browser. Storage may be refused (private windows), so
// every read and write is guarded; the sidebar then simply starts open.

const KEY = 'pentahoot-sidebar';

function safeStorage() {
    try {
        return window.localStorage;
    } catch {
        return null;
    }
}

function storedCollapsed(storage) {
    try {
        return storage?.getItem(KEY) === 'collapsed';
    } catch {
        return false;
    }
}

export function hostShell({ storage = safeStorage() } = {}) {
    return {
        menuOpen: false,
        collapsed: storedCollapsed(storage),

        toggleCollapsed() {
            this.collapsed = !this.collapsed;

            try {
                storage?.setItem(KEY, this.collapsed ? 'collapsed' : 'open');
            } catch {
                // The choice lasts until the page is left.
            }
        },
    };
}
