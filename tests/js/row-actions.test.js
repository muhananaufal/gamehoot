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

describe('C-7 row menu items that cannot run now', () => {
    it('shows an action the row marks "off" as disabled, with the reason the page gave', () => {
        const items = menuItems(
            [{ key: 'delete', label: 'Delete', type: 'confirm', danger: true, reason: 'Owns events', url: '/u/__P__' }],
            {
                id: 'u1',
                delete: 'off',
            },
        );

        expect(items).toEqual([
            {
                key: 'delete',
                label: 'Delete',
                type: 'confirm',
                danger: true,
                reason: 'Owns events',
                url: '/u/u1',
                disabled: true,
            },
        ]);
    });

    it('keeps the modal settings of a link, so it opens over the list', () => {
        const items = menuItems(
            [{ key: 'edit', label: 'Edit', type: 'link', modal: true, accept: '/admin/users', url: '/u/__P__/edit' }],
            {
                id: 'u1',
                edit: '1',
            },
        );

        expect(items[0]).toMatchObject({ modal: true, accept: '/admin/users', url: '/u/u1/edit' });
    });
});

describe('U5 row menus on light rows', () => {
    it('reads the id, name and actions from the row as well as the button', () => {
        const store = rowMenuStore();
        store.spec = [{ key: 'delete', label: 'Delete', type: 'confirm', url: '/p/__P__' }];
        const button = {
            dataset: { delete: '1' },
            closest: () => ({ dataset: { id: 'p1', name: 'Budi' } }),
            getBoundingClientRect: () => ({ bottom: 10, right: 100 }),
        };

        store.openFrom(button, { innerWidth: 200 });

        expect(store.name).toBe('Budi');
        expect(store.items[0].url).toBe('/p/p1');
    });
});
