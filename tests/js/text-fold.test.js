import { describe, expect, it } from 'vitest';
import { normalizeName } from '../../resources/js/name-review.js';
import { matchesRow } from '../../resources/js/table-view.js';
import { foldText } from '../../resources/js/text-fold.js';

// B-8 (audit DATA-1): mirrors App\People\PersonName::normalize(), see tests/Unit/People/PersonNameTest.php.
describe('B-8 names compared like the database', () => {
    it.each([
        ['José', 'Jose'],
        ['Ägnes Öztürk', 'agnes ozturk'],
        ['ＡＮＡ', 'ana'],
        ['Strauß', 'Strauss'],
        ['ÉLODIE', 'elodie'],
    ])('treats %s and %s as one name', (a, b) => {
        expect(normalizeName(a)).toBe(normalizeName(b));
    });

    it('still tells different names apart', () => {
        expect(normalizeName('Ana')).not.toBe(normalizeName('Anna'));
        expect(normalizeName('Budi (IT)')).not.toBe(normalizeName('Budi'));
    });

    it('lets "jose" find José in a host list', () => {
        const row = { search: 'José Santoso', filter: 'all' };

        expect(matchesRow(row, { q: 'jose', filter: 'all' })).toBe(true);
        expect(foldText('Ｊｏｓé')).toBe('jose');
    });
});
