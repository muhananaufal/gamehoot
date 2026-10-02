import { describe, expect, it } from 'vitest';
import { menuItems, rowMenuStore } from '../../resources/js/row-actions.js';

const spec = [
    { key: 'rename', label: 'Rename', type: 'link' },
    { key: 'release', label: 'Release name', type: 'confirm' },
    { key: 'newLink', label: 'New link', type: 'confirm' },
    { key: 'delete', label: 'Delete', type: 'confirm', danger: true },
];

describe('U5 one row menu per page', () => {
    it('offers only the actions the row carries an address for', () => {
        const items = menuItems(spec, { name: 'Budi', newLink: '/l', delete: '/d' });

        expect(items).toEqual([
            { key: 'newLink', label: 'New link', type: 'confirm', danger: false, url: '/l' },
            { key: 'delete', label: 'Delete', type: 'confirm', danger: true, url: '/d' },
        ]);
    });

    it('builds the address from a template once per page and the row id (A3: light pages)', () => {
        const templated = [
            { key: 'delete', label: 'Delete', type: 'confirm', danger: true, url: '/host/e/people/__P__' },
        ];

        expect(menuItems(templated, { id: 'p-7', delete: '1' })).toEqual([
            { key: 'delete', label: 'Delete', type: 'confirm', danger: true, url: '/host/e/people/p-7' },
        ]);
        expect(menuItems(templated, { id: 'p-7' })).toEqual([]);
    });

    it('opens next to the row button and closes again', () => {
        const menu = rowMenuStore();
        menu.spec = spec;
        const button = {
            dataset: { name: 'Budi', rename: '/r' },
            getBoundingClientRect: () => ({ bottom: 120, right: 600 }),
        };

        menu.openFrom(button, { innerWidth: 1000 });

        expect(menu.open).toBe(true);
        expect(menu.name).toBe('Budi');
        expect(menu.items.map((i) => i.key)).toEqual(['rename']);
        expect(menu.position).toEqual({ top: 124, right: 400 });

        menu.close();
        expect(menu.open).toBe(false);
    });

    it('toggles closed when the same row button is pressed again', () => {
        const menu = rowMenuStore();
        menu.spec = spec;
        const button = {
            dataset: { name: 'Budi', delete: '/d' },
            getBoundingClientRect: () => ({ bottom: 0, right: 0 }),
        };

        menu.openFrom(button, { innerWidth: 100 });
        menu.openFrom(button, { innerWidth: 100 });

        expect(menu.open).toBe(false);
    });
});
