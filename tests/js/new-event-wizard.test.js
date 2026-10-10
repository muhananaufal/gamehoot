import { describe, expect, it, vi } from 'vitest';
import { newEventWizard } from '../../resources/js/new-event-wizard.js';

const NL = String.fromCharCode(10);
const texts = {
    imported: { one: '__N__ name added from __F__', other: '__N__ names added from __F__' },
    count: { one: '__N__ name', other: '__N__ names' },
};
const wizard = (options = {}) => newEventWizard({ texts, parseUrl: '/parse', ...options });

describe('U32 the steps slide the way the host moves', () => {
    it('slides forward on Next or a later step, and back on Back or an earlier step', () => {
        const steps = wizard({ start: 2, detailsValid: true });

        steps.next();
        expect(steps.direction).toBe('forward');
        steps.back();
        expect(steps.direction).toBe('back');
        steps.go(4);
        expect(steps.direction).toBe('forward');
        steps.go(1);
        expect(steps.direction).toBe('back');
    });
});

describe('U26 the steps of New event', () => {
    it('starts at Name and link, and opens the other steps only once it is valid', () => {
        const steps = wizard();

        expect(steps.step).toBe(1);
        expect(steps.canGo(3)).toBe(false);

        steps.go(3);
        expect(steps.step).toBe(1);

        steps.detailsValid = true;
        steps.go(4);
        expect(steps.step).toBe(4);
        steps.go(2);
        expect(steps.step).toBe(2);
    });

    it('moves on from Names and Games with Next, or Skip while they are empty', () => {
        const steps = wizard({ start: 2, detailsValid: true });

        expect(steps.skippable).toBe(true);
        steps.text = 'Rita Wulandari';
        expect(steps.skippable).toBe(false);

        steps.next();
        expect(steps.step).toBe(3);
        expect(steps.skippable).toBe(true);
        steps.pick('p1', 'Office Awards');
        expect(steps.skippable).toBe(false);
        steps.back();
        expect(steps.step).toBe(2);
    });

    it('keeps Next off while the names have a problem, and says so on the review', () => {
        const steps = wizard({ start: 2, detailsValid: true, names: ['Ana Putri', 'ana putri'].join(NL) });

        expect(steps.namesOk).toBe(false);
        expect(steps.canCreate).toBe(false);

        steps.text = 'Ana Putri';
        expect(steps.namesOk).toBe(true);
        expect(steps.canCreate).toBe(true);
    });

    it('opens again at the step the server refused, with what was sent', () => {
        const steps = wizard({
            start: 3,
            detailsValid: true,
            names: 'Rita',
            picked: ['p2'],
            titles: { p2: 'Team Trivia' },
        });

        expect(steps.step).toBe(3);
        expect(steps.chips()).toEqual([{ id: 'p2', number: 1, title: 'Team Trivia' }]);
    });
});

describe('U26 importing a CSV file into the names step', () => {
    const answer = (status, body) => vi.fn(async () => ({ ok: status === 200, status, json: async () => body }));

    it('adds the names of the file under the list, and Undo takes them out again', async () => {
        const steps = wizard({
            fetch: answer(200, { names: ['Budi Santoso', 'Ana Putri'], file: 'staff.csv' }),
            csrf: 't',
        });
        steps.text = 'Rita Wulandari';

        await steps.importFile({ name: 'staff.csv' });

        expect(steps.text).toBe(['Rita Wulandari', 'Budi Santoso', 'Ana Putri'].join(NL));
        expect(steps.importNote).toBe('2 names added from staff.csv');

        steps.undoImport();
        expect(steps.text).toBe('Rita Wulandari');
        expect(steps.importNote).toBe('');
    });

    it('shows why a file was refused and leaves the list as it was', async () => {
        const steps = wizard({ fetch: answer(422, { errors: { file: ['This is not a CSV file.'] } }), csrf: 't' });
        steps.text = 'Rita Wulandari';

        await steps.importFile({ name: 'photo.csv' });

        expect(steps.text).toBe('Rita Wulandari');
        expect(steps.importError).toBe('This is not a CSV file.');
    });
});

describe('U24 leaving New event after typing asks first', () => {
    it('knows when something was entered', () => {
        const steps = wizard();
        expect(steps.dirty({ name: '', slug: '' })).toBe(false);
        expect(steps.dirty({ name: 'Year-End', slug: '' })).toBe(true);

        steps.pick('p1', 'Office Awards');
        expect(steps.dirty({ name: '', slug: '' })).toBe(true);
    });
});

describe('U26 a change on step 1 keeps the other steps open while it stays valid', () => {
    it('keeps them open for a valid change, closes them for an invalid one, and opens them again', () => {
        const steps = wizard({ start: 2, detailsValid: true });

        steps.refreshDetails(true);
        expect(steps.canGo(3)).toBe(true);

        steps.refreshDetails(false);
        expect(steps.canGo(3)).toBe(false);

        steps.refreshDetails(true);
        expect(steps.canGo(3)).toBe(true);
    });

    it('keeps them closed before step 1 was ever passed with Next', () => {
        const steps = wizard();

        steps.refreshDetails(true);

        expect(steps.canGo(2)).toBe(false);
    });
});

describe('audit FE-11: an import that never got an answer', () => {
    it('says so instead of failing silently, and keeps the list as it was', async () => {
        const steps = newEventWizard({
            texts: { ...texts, import_failed: 'The file could not be sent.' },
            parseUrl: '/parse',
            fetch: vi.fn(async () => {
                throw new TypeError('Failed to fetch');
            }),
            csrf: 't',
        });
        steps.text = 'Rita Wulandari';

        await steps.importFile({ name: 'staff.csv' });

        expect(steps.text).toBe('Rita Wulandari');
        expect(steps.importError).toBe('The file could not be sent.');
        expect(steps.importing).toBe(false);
    });
});
