import { describe, expect, it } from 'vitest';
import {
    addDays,
    addMonths,
    dayCell,
    monthGrid,
    moveFocus,
    pickDay,
    presetRange,
} from '../../resources/js/date-range.js';

describe('U15 calendar grid', () => {
    it('lays out a month in weeks that start on Monday, padded with the neighbouring days', () => {
        const weeks = monthGrid(2026, 10);

        expect(weeks[0].map((day) => day.date)).toEqual([
            '2026-09-28',
            '2026-09-29',
            '2026-09-30',
            '2026-10-01',
            '2026-10-02',
            '2026-10-03',
            '2026-10-04',
        ]);
        expect(weeks[0][0].inMonth).toBe(false);
        expect(weeks[0][3].inMonth).toBe(true);
        expect(weeks.at(-1).at(-1).date).toBe('2026-11-01');
        expect(weeks.every((week) => week.length === 7)).toBe(true);
    });

    it('counts days and months across month and year ends', () => {
        expect(addDays('2026-12-31', 1)).toBe('2027-01-01');
        expect(addDays('2026-03-01', -1)).toBe('2026-02-28');
        expect(addMonths('2026-01-31', 1)).toBe('2026-02-28');
        expect(addMonths('2026-01-15', -1)).toBe('2025-12-15');
    });
});

describe('U15 picking a range', () => {
    it('starts a range on the first click and closes it on the second', () => {
        const first = pickDay({ from: '', to: '' }, '2026-10-05');
        expect(first).toEqual({ from: '2026-10-05', to: '' });

        expect(pickDay(first, '2026-10-09')).toEqual({ from: '2026-10-05', to: '2026-10-09' });
    });

    it('swaps the ends when the second day comes before the first', () => {
        expect(pickDay({ from: '2026-10-09', to: '' }, '2026-10-02')).toEqual({ from: '2026-10-02', to: '2026-10-09' });
    });

    it('starts again when a full range is clicked', () => {
        expect(pickDay({ from: '2026-10-02', to: '2026-10-09' }, '2026-10-20')).toEqual({ from: '2026-10-20', to: '' });
    });

    it('offers quick ranges counted back from today in WIB', () => {
        expect(presetRange('today', '2026-10-02')).toEqual({ from: '2026-10-02', to: '2026-10-02' });
        expect(presetRange('last7', '2026-10-02')).toEqual({ from: '2026-09-26', to: '2026-10-02' });
        expect(presetRange('last30', '2026-10-02')).toEqual({ from: '2026-09-03', to: '2026-10-02' });
        expect(presetRange('month', '2026-10-02')).toEqual({ from: '2026-10-01', to: '2026-10-31' });
    });
});

describe('U15 keyboard', () => {
    it('moves the focused day like a date grid (W3C APG)', () => {
        expect(moveFocus('2026-10-02', 'ArrowLeft')).toBe('2026-10-01');
        expect(moveFocus('2026-10-02', 'ArrowRight')).toBe('2026-10-03');
        expect(moveFocus('2026-10-02', 'ArrowUp')).toBe('2026-09-25');
        expect(moveFocus('2026-10-02', 'ArrowDown')).toBe('2026-10-09');
        expect(moveFocus('2026-10-02', 'PageUp')).toBe('2026-09-02');
        expect(moveFocus('2026-10-02', 'PageDown')).toBe('2026-11-02');
        // 2 Oct 2026 is a Friday: Home goes to Monday, End to Sunday.
        expect(moveFocus('2026-10-02', 'Home')).toBe('2026-09-28');
        expect(moveFocus('2026-10-02', 'End')).toBe('2026-10-04');
        expect(moveFocus('2026-10-02', 'Tab')).toBeNull();
    });
});

describe('U15 day cell look', () => {
    it('marks the ends, the days between and today with one cheap call per cell', () => {
        const range = { from: '2026-10-05', to: '2026-10-09' };

        expect(dayCell(range, '2026-10-05', '2026-10-02')).toEqual({ edge: true, inRange: true, today: false });
        expect(dayCell(range, '2026-10-07', '2026-10-02')).toEqual({ edge: false, inRange: true, today: false });
        expect(dayCell(range, '2026-10-02', '2026-10-02')).toEqual({ edge: false, inRange: false, today: true });
        expect(dayCell({ from: '2026-10-05', to: '' }, '2026-10-05', '2026-10-02').edge).toBe(true);
    });
});
