import { describe, expect, it } from 'vitest';
import { compareRows, matchesRow, readState, writeState } from '../../resources/js/table-view.js';

const row = (values) => ({ search: values.search ?? '', filter: values.filter ?? '', sort: values.sort ?? {} });

describe('U4 table view state in the URL', () => {
    it('reads search, filter and sort from the query string', () => {
        expect(readState('?q=budi&filter=joined&sort=joined&dir=desc', { sort: 'name', dir: 'asc' })).toEqual({
            q: 'budi',
            filter: 'joined',
            sort: 'joined',
            dir: 'desc',
        });
    });

    it('falls back to the defaults and ignores unknown directions', () => {
        expect(readState('?dir=sideways', { sort: 'name', dir: 'asc' })).toEqual({
            q: '',
            filter: 'all',
            sort: 'name',
            dir: 'asc',
        });
    });

    it('writes only what differs from the defaults, keeping other parameters', () => {
        const defaults = { sort: 'name', dir: 'asc' };

        expect(
            writeState('/host/e/people?edit=7', { q: 'ana', filter: 'all', sort: 'name', dir: 'asc' }, defaults),
        ).toBe('/host/e/people?edit=7&q=ana');
        expect(writeState('/host/e/people?q=old', { q: '', filter: 'all', sort: 'name', dir: 'asc' }, defaults)).toBe(
            '/host/e/people',
        );
        expect(writeState('/host', { q: '', filter: 'open', sort: 'changed', dir: 'desc' }, defaults)).toBe(
            '/host?filter=open&sort=changed&dir=desc',
        );
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
