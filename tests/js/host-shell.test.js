import { describe, expect, it } from 'vitest';
import { hostShell } from '../../resources/js/host-shell.js';

function memoryStorage(initial = {}) {
    const values = { ...initial };

    return {
        getItem: (key) => values[key] ?? null,
        setItem: (key, value) => {
            values[key] = String(value);
        },
        values,
    };
}

describe('U1 host sidebar', () => {
    it('starts open and remembers a collapsed sidebar for the next page', () => {
        const storage = memoryStorage();
        const shell = hostShell({ storage });

        expect(shell.collapsed).toBe(false);

        shell.toggleCollapsed();
        expect(shell.collapsed).toBe(true);
        expect(hostShell({ storage }).collapsed).toBe(true);

        shell.toggleCollapsed();
        expect(hostShell({ storage }).collapsed).toBe(false);
    });

    it('still works when the browser refuses storage (private window)', () => {
        const broken = {
            getItem: () => {
                throw new Error('denied');
            },
            setItem: () => {
                throw new Error('denied');
            },
        };
        const shell = hostShell({ storage: broken });

        expect(shell.collapsed).toBe(false);
        shell.toggleCollapsed();
        expect(shell.collapsed).toBe(true);
    });
});
