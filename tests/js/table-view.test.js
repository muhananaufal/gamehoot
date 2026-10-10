import { describe, expect, it, vi } from 'vitest';
import {
    compareRows,
    filtersActive,
    labelButtons,
    loadState,
    matchesRow,
    goToPage,
    pageItems,
    rangeText,
    pageOf,
    saveState,
    tableView,
} from '../../resources/js/table-view.js';

const row = (values) => ({
    search: values.search ?? '',
    filter: values.filter ?? '',
    date: values.date ?? '',
    sort: values.sort ?? {},
});

const memory = (initial = {}) => {
    const saved = { ...initial };

    return {
        saved,
        getItem: (key) => saved[key] ?? null,
        setItem: (key, value) => (saved[key] = value),
        removeItem: (key) => delete saved[key],
    };
};

const blocked = {
    getItem() {
        throw new Error('blocked');
    },
    setItem() {
        throw new Error('blocked');
    },
    removeItem() {
        throw new Error('blocked');
    },
};

const DEFAULTS = { sort: 'name', dir: 'asc' };

describe('U4 table view state in localStorage', () => {
    it('starts from the defaults when nothing was stored', () => {
        expect(loadState(memory(), 'names', DEFAULTS)).toEqual({
            q: '',
            filter: 'all',
            who: 'all',
            kind: 'all',
            sort: 'name',
            dir: 'asc',
            from: '',
            to: '',
            page: 1,
            size: 25,
        });
    });

    it('stores only what differs from the defaults, under its own key, and clears it when back to defaults', () => {
        const storage = memory();

        saveState(storage, 'names:e1', { ...loadState(storage, 'x', DEFAULTS), q: 'budi', size: 50 }, DEFAULTS);
        expect(JSON.parse(storage.saved['pentahoot-table:names:e1'])).toEqual({ q: 'budi', size: 50 });

        saveState(storage, 'names:e1', loadState(memory(), 'x', DEFAULTS), DEFAULTS);
        expect(storage.saved['pentahoot-table:names:e1']).toBeUndefined();
    });

    it('reads back a stored state and drops values it does not know', () => {
        const storage = memory({
            'pentahoot-table:events': JSON.stringify({
                dir: 'sideways',
                size: 7,
                page: 3,
                from: '2026-10-01',
                sort: 'created',
            }),
        });

        expect(loadState(storage, 'events', DEFAULTS)).toMatchObject({
            dir: 'asc',
            size: 25,
            page: 3,
            from: '2026-10-01',
            sort: 'created',
        });
    });

    it('works without remembering when the browser blocks storage', () => {
        expect(loadState(blocked, 'names', DEFAULTS).filter).toBe('all');
        expect(() =>
            saveState(blocked, 'names', { ...loadState(blocked, 'names', DEFAULTS), q: 'a' }, DEFAULTS),
        ).not.toThrow();
    });

    it('knows when a filter is active, so Reset filters can show', () => {
        const base = loadState(memory(), 'x', DEFAULTS);

        expect(filtersActive(base)).toBe(false);
        expect(filtersActive({ ...base, sort: 'joined', size: 100 })).toBe(false);
        expect(filtersActive({ ...base, q: ' a ' })).toBe(true);
        expect(filtersActive({ ...base, filter: 'joined' })).toBe(true);
        expect(filtersActive({ ...base, to: '2026-10-02' })).toBe(true);
    });
});

describe('U4 pages', () => {
    it('cuts the visible rows into pages and says which rows are shown', () => {
        expect(pageOf(480, 1, 25)).toEqual({ page: 1, pages: 20, start: 0, end: 25 });
        expect(pageOf(480, 20, 25)).toEqual({ page: 20, pages: 20, start: 475, end: 480 });
        expect(pageOf(30, 2, 10)).toEqual({ page: 2, pages: 3, start: 10, end: 20 });
    });

    it('keeps the page inside the list after rows disappear', () => {
        expect(pageOf(12, 5, 10)).toEqual({ page: 2, pages: 2, start: 10, end: 12 });
        expect(pageOf(0, 3, 25)).toEqual({ page: 1, pages: 1, start: 0, end: 0 });
    });
});

describe('U4 matching and sorting rows', () => {
    it('matches the search anywhere in the row text, ignoring case and extra spaces', () => {
        const budi = row({ search: 'budi santoso (it) playing' });

        expect(matchesRow(budi, { q: '  SANTOSO ', filter: 'all' })).toBe(true);
        expect(matchesRow(budi, { q: 'rita', filter: 'all' })).toBe(false);
    });

    it('applies the filter when one is chosen', () => {
        const joined = row({ search: 'ana', filter: 'joined' });

        expect(matchesRow(joined, { q: '', filter: 'joined' })).toBe(true);
        expect(matchesRow(joined, { q: '', filter: 'waiting' })).toBe(false);
        expect(matchesRow(joined, { q: '', filter: 'all' })).toBe(true);
    });

    it('keeps rows whose date falls inside the chosen range, both ends included (U15)', () => {
        const early = row({ date: '2026-09-29' });
        const late = row({ date: '2026-10-02' });
        const range = { q: '', filter: 'all', from: '2026-09-30', to: '2026-10-02' };

        expect(matchesRow(early, range)).toBe(false);
        expect(matchesRow(late, range)).toBe(true);
        expect(matchesRow(early, { ...range, from: '', to: '2026-09-29' })).toBe(true);
        expect(matchesRow(row({ date: '' }), range)).toBe(false);
    });

    it('sorts text naturally and numbers by value, empty values last either way', () => {
        const a = row({ sort: { name: 'Budi 10', joined: '' } });
        const b = row({ sort: { name: 'Budi 9', joined: '1790906731' } });
        const c = row({ sort: { name: 'ana', joined: '1790900000' } });

        expect([a, b, c].sort((x, y) => compareRows(x, y, 'name', 'asc')).map((r) => r.sort.name)).toEqual([
            'ana',
            'Budi 9',
            'Budi 10',
        ]);
        expect([a, b, c].sort((x, y) => compareRows(x, y, 'joined', 'desc')).map((r) => r.sort.joined)).toEqual([
            '1790906731',
            '1790900000',
            '',
        ]);
        expect([a, b, c].sort((x, y) => compareRows(x, y, 'joined', 'asc')).map((r) => r.sort.joined)).toEqual([
            '1790900000',
            '1790906731',
            '',
        ]);
    });
});

describe('U4 numbered pagination', () => {
    it('lists every page while there are few', () => {
        expect(pageItems(1, 1)).toEqual([1]);
        expect(pageItems(3, 5)).toEqual([1, 2, 3, 4, 5]);
    });

    it('keeps the first, the last and the neighbours of the current page, with gaps between', () => {
        expect(pageItems(1, 9)).toEqual([1, 2, 3, '…', 9]);
        expect(pageItems(5, 9)).toEqual([1, '…', 4, 5, 6, '…', 9]);
        expect(pageItems(9, 9)).toEqual([1, '…', 7, 8, 9]);
    });

    it('shows a single hidden page as its number instead of a gap', () => {
        expect(pageItems(4, 9)).toEqual([1, 2, 3, 4, 5, '…', 9]);
    });
});

describe('U4 who did it (Activity log)', () => {
    it('keeps rows of the chosen person only', () => {
        const rows = [row({ who: 'u1' }), row({ who: 'u2' })].map((r, i) => ({ ...r, who: ['u1', 'u2'][i] }));

        expect(rows.filter((r) => matchesRow(r, { q: '', filter: 'all', who: 'u2' }))).toHaveLength(1);
        expect(rows.filter((r) => matchesRow(r, { q: '', filter: 'all', who: 'all' }))).toHaveLength(2);
    });

    it('remembers the choice and counts it as a filter', () => {
        const storage = memory();
        saveState(storage, 'logs', { ...loadState(storage, 'logs', DEFAULTS), who: 'u2' }, DEFAULTS);

        expect(loadState(storage, 'logs', DEFAULTS).who).toBe('u2');
        expect(filtersActive({ ...loadState(memory(), 'x', DEFAULTS), who: 'u2' })).toBe(true);
    });
});

describe('U5 row button labels sent once per page', () => {
    const fakeButton = (data, rowName) => {
        const attrs = {};

        return {
            dataset: data,
            attrs,
            title: '',
            hasAttribute: (name) => name in attrs,
            setAttribute: (name, value) => (attrs[name] = value),
            closest: (selector) => (selector === '[data-row]' ? { dataset: { name: rowName } } : null),
        };
    };

    it('labels each icon button with the name of its row and a tooltip', () => {
        const copy = fakeButton({ copy: 'tok' }, 'Budi');
        const rename = fakeButton({ ask: 'rename' }, 'Rita');
        const root = { querySelectorAll: () => [copy, rename] };

        labelButtons(root, {
            copy: { label: 'Copy the personal link of __N__', title: 'Copy link' },
            rename: { label: 'Rename __N__', title: 'Rename' },
        });

        expect(copy.attrs['aria-label']).toBe('Copy the personal link of Budi');
        expect(copy.title).toBe('Copy link');
        expect(rename.attrs['aria-label']).toBe('Rename Rita');
    });

    it('leaves buttons alone that have no label template or a label already', () => {
        const other = fakeButton({ ask: 'unknown' }, 'Budi');
        const labelled = fakeButton({ ask: 'rename' }, 'Budi');
        labelled.attrs['aria-label'] = 'Kept';

        labelButtons(
            { querySelectorAll: () => [other, labelled] },
            { rename: { label: 'Rename __N__', title: 'Rename' } },
        );

        expect(other.attrs['aria-label']).toBeUndefined();
        expect(labelled.attrs['aria-label']).toBe('Kept');
    });
});

describe('U5 row menu buttons get their label once per page', () => {
    it('labels the menu button of a row with its name', () => {
        const attrs = {};
        const button = {
            dataset: { menu: '' },
            title: '',
            hasAttribute: (name) => name in attrs,
            setAttribute: (name, value) => (attrs[name] = value),
            closest: () => ({ dataset: { name: 'Budi' } }),
        };

        labelButtons({ querySelectorAll: () => [button] }, { menu: { label: 'Actions for __N__', title: 'Actions' } });

        expect(attrs['aria-label']).toBe('Actions for Budi');
    });
});

describe('U4 a second filter (role on Host accounts)', () => {
    it('keeps rows of the chosen kind, remembers it and counts it as a filter', () => {
        const rows = [row({}), row({})].map((r, i) => ({ ...r, kind: ['admin', 'host'][i] }));

        expect(rows.filter((r) => matchesRow(r, { q: '', filter: 'all', kind: 'host' }))).toHaveLength(1);

        const storage = memory();
        saveState(storage, 'accounts', { ...loadState(storage, 'accounts', DEFAULTS), kind: 'host' }, DEFAULTS);
        expect(loadState(storage, 'accounts', DEFAULTS).kind).toBe('host');
        expect(filtersActive({ ...loadState(memory(), 'x', DEFAULTS), kind: 'host' })).toBe(true);
    });
});

describe('U4 long lists: going to a page and the range summary', () => {
    it('goes to a typed page, kept inside the list, and ignores what is not a number', () => {
        expect(goToPage('7', 3, 42)).toBe(7);
        expect(goToPage('99', 3, 42)).toBe(42);
        expect(goToPage('0', 3, 42)).toBe(1);
        expect(goToPage('', 3, 42)).toBe(3);
        expect(goToPage('abc', 3, 42)).toBe(3);
    });

    it('writes the range with thousands separators', () => {
        expect(rangeText('Showing __S__–__E__ of __T__', 50, 75, 1243)).toBe('Showing 51–75 of 1,243');
    });
});

describe('audit FE-11: copying a personal link when the browser refuses', () => {
    it('says that the copy did not work instead of failing silently', async () => {
        vi.stubGlobal('navigator', {
            clipboard: { writeText: vi.fn(async () => Promise.reject(new Error('denied'))) },
        });
        const toasts = [];
        const view = tableView({
            key: 'names:copy',
            sort: 'name',
            copied: '__N__ copied',
            copyFailed: 'Copy did not work. Select the link and copy it.',
            copyBase: 'https://p.test/',
            storage: null,
        });
        view.$store = { toasts: { push: (text) => toasts.push(text) } };
        const button = {
            dataset: { copy: 'tok' },
            closest: (selector) => (selector === '[data-copy]' ? button : null),
        };

        view.onRowClick({ target: button });
        await new Promise((resolve) => setTimeout(resolve, 0));

        expect(toasts).toEqual(['Copy did not work. Select the link and copy it.']);
        vi.unstubAllGlobals();
    });
});
