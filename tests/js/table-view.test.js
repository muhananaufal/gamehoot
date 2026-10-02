import { describe, expect, it } from 'vitest';
import { compareRows, filtersActive, loadState, matchesRow, pageOf, saveState } from '../../resources/js/table-view.js';

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
