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
