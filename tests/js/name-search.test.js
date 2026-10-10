import { describe, expect, it } from 'vitest';
import { nameSearch } from '../../resources/js/name-search.js';

describe('F9, B-8 the name search on the phone', () => {
    it('finds a name whatever accents, case or width the player types (audit DATA-1)', () => {
        const search = nameSearch({ names: ['José Ramírez', 'Ana Putri', 'Élodie Martin'] });

        search.query = 'jose';
        expect([0, 1, 2].map((index) => search.shows(index))).toEqual([true, false, false]);

        search.query = 'ELODIE';
        expect(search.shows(2)).toBe(true);

        search.query = 'ｐｕｔｒｉ';
        expect(search.shows(1)).toBe(true);
    });

    it('shows every name for an empty search and says when nothing matches', () => {
        const search = nameSearch({ names: ['José Ramírez'] });

        search.query = '  ';
        expect(search.shows(0)).toBe(true);
        expect(search.noMatch).toBe(false);

        search.query = 'budi';
        expect(search.noMatch).toBe(true);
    });
});
