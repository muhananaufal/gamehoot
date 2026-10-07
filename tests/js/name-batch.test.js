import { describe, expect, it } from 'vitest';
import { checkNames, countText, nameBatch, normalizeName, problemText } from '../../resources/js/name-batch.js';

// Line breaks and the combining accent are built from their codes, so no tool rewrites them.
const NL = String.fromCharCode(10);
const CRLF = String.fromCharCode(13, 10);
const COMBINING_ACUTE = String.fromCharCode(0x301);
const lines = (...rows) => rows.join(NL);

describe('B-4 names compared like the server (PersonName)', () => {
    it('ignores case and extra spaces, and composes accents', () => {
        expect(normalizeName('  BUDI   santoso ')).toBe('budi santoso');
        // "é" typed as e + combining accent equals the composed "é".
        expect(normalizeName(`Rene${COMBINING_ACUTE}e`)).toBe(normalizeName('Renée'));
    });
});

describe('B-4 Add names checked before sending', () => {
    const existing = ['Rita Wulandari', 'Budi Santoso'];

    it('takes one name per line, skipping empty lines and tidying spaces', () => {
        const result = checkNames(`Dina Rahma${NL}${NL}   Eko   Prasetyo  ${CRLF} ${NL}`, existing);

        expect(result.names).toEqual(['Dina Rahma', 'Eko Prasetyo']);
        expect(result.problems).toEqual([]);
        expect(result.ok).toBe(true);
    });

    it('marks a name already in the event and a name typed twice, with their line', () => {
        const result = checkNames(lines('Dina Rahma', 'rita  WULANDARI', 'dina rahma'), existing);

        expect(result.problems).toEqual([
            { line: 2, name: 'rita WULANDARI', kind: 'existing', other: 'Rita Wulandari' },
            { line: 3, name: 'dina rahma', kind: 'repeat', other: 1 },
        ]);
        expect(result.ok).toBe(false);
    });

    it('marks a name longer than 100 characters', () => {
        const result = checkNames(lines('A'.repeat(101), 'B'.repeat(100)), existing);

        expect(result.problems).toEqual([{ line: 1, name: 'A'.repeat(101), kind: 'too_long', other: null }]);
    });

    it('refuses more names than the event can still take (500 in all)', () => {
        const full = Array.from({ length: 499 }, (_, i) => `Guest ${i}`);

        expect(checkNames(lines('One', 'Two'), full).tooMany).toBe(true);
        expect(checkNames(lines('One', 'Two'), full).ok).toBe(false);
        expect(checkNames('One', full).tooMany).toBe(false);
    });

    it('has nothing to send for empty text', () => {
        expect(checkNames(`  ${NL} `, existing)).toMatchObject({ names: [], ok: false });
    });
});

describe('B-4 Add names says the problem like the server does', () => {
    const texts = {
        line: 'Line __L__: __P__',
        existing: 'Same as “__N__” already in this event.',
        repeat: 'Same as line __R__, e.g. “__N__ (IT)”.',
        too_long: 'Longer than 100 characters.',
        count: { one: '__N__ name', other: '__N__ names' },
        add: { one: 'Add __N__ name', other: 'Add __N__ names' },
    };

    it('writes each problem with its line', () => {
        expect(problemText({ line: 2, name: 'rita', kind: 'existing', other: 'Rita Wulandari' }, texts)).toBe(
            'Line 2: Same as “Rita Wulandari” already in this event.',
        );
        expect(problemText({ line: 3, name: 'dina rahma', kind: 'repeat', other: 1 }, texts)).toBe(
            'Line 3: Same as line 1, e.g. “dina rahma (IT)”.',
        );
        expect(problemText({ line: 1, name: 'A', kind: 'too_long', other: null }, texts)).toBe(
            'Line 1: Longer than 100 characters.',
        );
    });

    it('counts the names on the button, one or many', () => {
        expect(countText(texts.add, 1)).toBe('Add 1 name');
        expect(countText(texts.add, 3)).toBe('Add 3 names');
    });
});

describe('B-4 Add names points at the lines of the text area', () => {
    it('counts empty lines too, so the line in a message is the line the host sees', () => {
        const result = checkNames(lines('Dina Rahma', '', 'Eko Prasetyo', 'dina RAHMA'), []);

        expect(result.problems).toEqual([{ line: 4, name: 'dina RAHMA', kind: 'repeat', other: 1 }]);
    });

    it('names the button without a count while nothing is typed', () => {
        const dialog = nameBatch({
            texts: { add: { one: 'Add __N__ name', other: 'Add __N__ names' }, empty: 'Add names' },
        });

        expect(dialog.addLabel).toBe('Add names');
        dialog.text = 'Dina Rahma';
        expect(dialog.addLabel).toBe('Add 1 name');
    });
});
