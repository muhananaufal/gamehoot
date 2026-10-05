import { describe, expect, it } from 'vitest';
import { confirmStore } from '../../resources/js/confirm-store.js';

describe('U5 one confirmation dialog per page', () => {
    it('opens with what the row asked for and closes again', () => {
        const confirm = confirmStore();

        confirm.ask({
            title: 'Delete Budi?',
            body: 'The name and its personal link are deleted.',
            action: '/host/e/people/p',
            method: 'DELETE',
            confirmLabel: 'Delete',
            tone: 'destructive',
        });

        expect(confirm.open).toBe(true);
        expect(confirm.title).toBe('Delete Budi?');
        expect(confirm.method).toBe('DELETE');
        expect(confirm.tone).toBe('destructive');

        confirm.close();
        expect(confirm.open).toBe(false);
    });

    it('defaults to a POST with the normal button', () => {
        const confirm = confirmStore();
        confirm.ask({ title: 'New link?', body: '', action: '/x', confirmLabel: 'New link' });

        expect(confirm.method).toBe('POST');
        expect(confirm.tone).toBe('primary');
    });
});

describe('U5 confirmation texts sent once per page', () => {
    it('fills the row name into the texts the page gave', () => {
        const confirm = confirmStore();
        confirm.texts = {
            delete: {
                title: 'Delete __N__?',
                body: 'Gone for good.',
                label: 'Delete',
                method: 'DELETE',
                tone: 'destructive',
            },
        };

        confirm.askFor('delete', 'Budi Santoso', '/host/e/people/p');

        expect(confirm.title).toBe('Delete Budi Santoso?');
        expect(confirm.action).toBe('/host/e/people/p');
        expect(confirm.method).toBe('DELETE');
        expect(confirm.open).toBe(true);
    });
});

describe('U5 row buttons that ask first', () => {
    const button = (data) => ({ dataset: data });

    it('builds the address from the template of its kind and the row id', () => {
        const confirm = confirmStore();
        confirm.texts = {
            newLink: {
                title: 'New link for __N__?',
                label: 'New link',
                method: 'POST',
                url: '/host/e/people/__P__/link',
            },
        };

        confirm.askRow(button({ ask: 'newLink', id: 'p1', name: 'Budi' }));

        expect(confirm.title).toBe('New link for Budi?');
        expect(confirm.action).toBe('/host/e/people/p1/link');
        expect(confirm.field).toBeNull();
    });

    it('carries one field to fill in, starting from the row value (rename)', () => {
        const confirm = confirmStore();
        confirm.texts = {
            rename: {
                title: 'Rename __N__',
                label: 'Save',
                method: 'PUT',
                url: '/p/__P__',
                field: { name: 'name', label: 'Name', maxlength: 100 },
            },
        };

        confirm.askRow(button({ ask: 'rename', id: 'p1', name: 'Budi' }));

        expect(confirm.method).toBe('PUT');
        expect(confirm.field).toEqual({ name: 'name', label: 'Name', maxlength: 100, value: 'Budi', error: '' });
    });

    it('opens again with the value and the error after the server refused it', () => {
        const confirm = confirmStore();
        confirm.texts = {
            rename: {
                title: 'Rename __N__',
                label: 'Save',
                method: 'PUT',
                url: '/p/__P__',
                field: { name: 'name', label: 'Name' },
            },
        };

        confirm.askRow(button({ ask: 'rename', id: 'p1', name: 'Budi' }), { value: 'Rita', error: 'Taken.' });

        expect(confirm.field.value).toBe('Rita');
        expect(confirm.field.error).toBe('Taken.');
    });

    it('ignores kinds the page did not describe', () => {
        const confirm = confirmStore();
        confirm.askRow(button({ ask: 'unknown', id: 'p1', name: 'Budi' }));

        expect(confirm.open).toBe(false);
    });
});

describe('U5 row buttons that take id and name from their row', () => {
    it('reads data-id and data-name from the row when the button has none', () => {
        const confirm = confirmStore();
        confirm.texts = { delete: { title: 'Delete __N__?', label: 'Delete', method: 'DELETE', url: '/p/__P__' } };

        confirm.askRow({ dataset: { ask: 'delete' }, closest: () => ({ dataset: { id: 'p9', name: 'Budi' } }) });

        expect(confirm.title).toBe('Delete Budi?');
        expect(confirm.action).toBe('/p/p9');
    });
});
