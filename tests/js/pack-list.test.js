import { describe, expect, it } from 'vitest';
import { groupOpen, loadPackState, matchesPack, orderPacks, savePackState } from '../../resources/js/pack-list.js';

const memory = () => {
    const saved = {};

    return {
        saved,
        getItem: (k) => saved[k] ?? null,
        setItem: (k, v) => (saved[k] = v),
        removeItem: (k) => delete saved[k],
    };
};

describe('U14 a pack list that stays light with many packs', () => {
    it('matches packs by title words and by game type', () => {
        const pack = { title: 'Office Awards 2026', type: 'pentahoot' };

        expect(matchesPack(pack, { q: 'awards office', type: 'all' })).toBe(true);
        expect(matchesPack(pack, { q: '', type: 'tebak_kata' })).toBe(false);
        expect(matchesPack(pack, { q: 'quiz', type: 'all' })).toBe(false);
    });

    it('orders packs by name or by the most recently changed first', () => {
        const packs = [
            { title: 'Beta', changed: 100 },
            { title: 'alpha', changed: 300 },
            { title: 'Gamma', changed: 200 },
        ];

        expect(orderPacks(packs, 'name').map((p) => p.title)).toEqual(['alpha', 'Beta', 'Gamma']);
        expect(orderPacks(packs, 'changed').map((p) => p.title)).toEqual(['alpha', 'Gamma', 'Beta']);
    });

    it('keeps folded groups folded, but opens a group that holds search results', () => {
        expect(groupOpen('pentahoot', { collapsed: ['pentahoot'], q: '', hasMatch: true })).toBe(false);
        expect(groupOpen('pentahoot', { collapsed: ['pentahoot'], q: 'awards', hasMatch: true })).toBe(true);
        expect(groupOpen('pentahoot', { collapsed: [], q: '', hasMatch: true })).toBe(true);
    });

    it('remembers folds, filter and order per browser, and drops values it does not know', () => {
        const storage = memory();
        savePackState(storage, { collapsed: ['tebak_kata'], type: 'pentahoot', sort: 'changed' });

        expect(loadPackState(storage)).toEqual({ collapsed: ['tebak_kata'], type: 'pentahoot', sort: 'changed' });

        storage.setItem('pentahoot-packs', '{"collapsed":"x","sort":"weird"}');
        expect(loadPackState(storage)).toEqual({ collapsed: [], type: 'all', sort: 'name' });
    });

    it('works without remembering when storage is blocked', () => {
        const blocked = {
            getItem() {
                throw new Error('blocked');
            },
            setItem() {
                throw new Error('blocked');
            },
        };

        expect(loadPackState(blocked)).toEqual({ collapsed: [], type: 'all', sort: 'name' });
        expect(() => savePackState(blocked, { collapsed: [], type: 'all', sort: 'name' })).not.toThrow();
    });
});
