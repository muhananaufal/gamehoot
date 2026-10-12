import { describe, expect, it } from 'vitest';
import { eventLink, linkFromName } from '../../resources/js/event-link.js';

const reserved = ['host', 'admin'];

describe('U13 the link of a new event follows its name', () => {
    it('makes the link the way the server does: lower case, plain letters, hyphens', () => {
        expect(linkFromName('Gathering 2026', reserved)).toBe('gathering-2026');
        expect(linkFromName('  Café   Ölmez & Friends! ', reserved)).toBe('cafe-olmez-friends');
        expect(linkFromName('Dina@HR', reserved)).toBe('dina-at-hr');
    });

    it('keeps the server rules for short and reserved names, and leaves an empty name empty', () => {
        expect(linkFromName('AB', reserved)).toBe('ab-event');
        expect(linkFromName('Host', reserved)).toBe('host-event');
        expect(linkFromName('!!!', reserved)).toBe('');
    });

    it('stops at 60 characters without a trailing hyphen', () => {
        const link = linkFromName('word '.repeat(20), reserved);

        expect(link.length).toBeLessThanOrEqual(60);
        expect(link.endsWith('-')).toBe(false);
    });

    it('follows the name until the host types a link of their own', () => {
        const fill = eventLink({ reserved });

        fill.nameTyped('Year End Party');
        expect(fill.link).toBe('year-end-party');

        fill.linkTyped('party');
        fill.nameTyped('Year End Party 2026');
        expect(fill.link).toBe('party');
    });

    it('follows the name again once the host clears the link', () => {
        const fill = eventLink({ reserved });
        fill.linkTyped('');
        fill.nameTyped('Demo');

        expect(fill.link).toBe('demo');
    });
});

describe('U13 a refused form keeps the link that was sent', () => {
    it('starts from the link the server sent back and treats it as typed', () => {
        const fill = eventLink({ reserved, link: 'my-party' });
        fill.nameTyped('Something Else');

        expect(fill.link).toBe('my-party');
    });
});
