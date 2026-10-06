import { describe, expect, it } from 'vitest';
import { checkFormsBeforeSubmit, fieldProblem } from '../../resources/js/form-check.js';

const messages = {
    required: 'The :attribute field is required.',
    max: 'The :attribute field must not be greater than :max characters.',
    email: 'The :attribute field must be a valid email address.',
};
const field = (props) => ({
    value: '',
    required: false,
    maxLength: -1,
    type: 'text',
    pattern: '',
    dataset: {},
    ...props,
});

describe('U19 checks before a form is sent', () => {
    it('asks for a required field left empty or only spaces, naming the field like the server does', () => {
        expect(fieldProblem(field({ required: true, value: '  ' }), 'Event name', messages)).toBe(
            'The event name field is required.',
        );
        expect(fieldProblem(field({ required: true, value: 'Gathering' }), 'Event name', messages)).toBeNull();
    });

    it('stops text longer than allowed', () => {
        expect(fieldProblem(field({ value: 'abcd', maxLength: 3 }), 'Name', messages)).toBe(
            'The name field must not be greater than 3 characters.',
        );
    });

    it('checks the shape of an email, leaving an empty optional one alone', () => {
        expect(fieldProblem(field({ type: 'email', value: 'dina@' }), 'Email', messages)).toBe(
            'The email field must be a valid email address.',
        );
        expect(fieldProblem(field({ type: 'email', value: 'dina@company.test' }), 'Email', messages)).toBeNull();
        expect(fieldProblem(field({ type: 'email', value: '' }), 'Email', messages)).toBeNull();
    });

    it('checks a pattern with the message the field carries (usernames)', () => {
        const username = field({
            pattern: '[a-z0-9._-]{3,30}',
            dataset: { patternMessage: 'Lower case letters only.' },
        });

        expect(fieldProblem({ ...username, value: 'Dina HR' }, 'Username', messages)).toBe('Lower case letters only.');
        expect(fieldProblem({ ...username, value: 'dina.hr' }, 'Username', messages)).toBeNull();
    });

    it('leaves hidden, disabled and read-only fields to the server', () => {
        expect(fieldProblem(field({ required: true, type: 'hidden' }), 'X', messages)).toBeNull();
        expect(fieldProblem(field({ required: true, disabled: true }), 'X', messages)).toBeNull();
        expect(fieldProblem(field({ required: true, readOnly: true }), 'X', messages)).toBeNull();
    });
});

describe('U19 more rules checked before sending', () => {
    const more = {
        ...messages,
        min: 'The :attribute field must be at least :min characters.',
        between: 'The :attribute field must be between :min and :max.',
        same: 'The :attribute field must match :other.',
    };

    it('keeps a number inside its range, with the message the field carries (time to vote)', () => {
        const seconds = field({
            type: 'number',
            min: '5',
            max: '300',
            dataset: { rangeMessage: 'Any value from 5 to 300 seconds.' },
        });

        expect(fieldProblem({ ...seconds, value: '3' }, 'Seconds', more)).toBe('Any value from 5 to 300 seconds.');
        expect(fieldProblem({ ...seconds, value: '301' }, 'Seconds', more)).toBe('Any value from 5 to 300 seconds.');
        expect(fieldProblem({ ...seconds, value: '4.5' }, 'Seconds', more)).toBe('Any value from 5 to 300 seconds.');
        expect(fieldProblem({ ...seconds, value: '45' }, 'Seconds', more)).toBeNull();
    });

    it('falls back to the server message for a range without its own', () => {
        expect(fieldProblem(field({ type: 'number', min: '0', max: '2', value: '9' }), 'Points', more)).toBe(
            'The points field must be between 0 and 2.',
        );
    });

    it('asks for a minimum length (passwords)', () => {
        expect(fieldProblem(field({ minLength: 12, value: 'short' }), 'Password', more)).toBe(
            'The password field must be at least 12 characters.',
        );
    });

    it('asks a confirmation to match its field', () => {
        const confirmation = field({ value: 'abc', dataset: { same: 'password' } });

        expect(fieldProblem(confirmation, 'Repeat the password', more, () => 'different')).toBe(
            'The repeat the password field must match password.',
        );
        expect(fieldProblem(confirmation, 'Repeat the password', more, () => 'abc')).toBeNull();
    });
});

describe('U19 every host form is checked, sign in excepted', () => {
    const listen = () => {
        const handlers = {};
        const doc = { addEventListener: (type, handler) => (handlers[type] = handler) };
        checkFormsBeforeSubmit(doc, messages);

        return handlers;
    };
    const formWith = (attrs = {}) => {
        const form = Object.create(globalThis.HTMLFormElement?.prototype ?? {});

        return Object.assign(form, {
            noValidate: false,
            hasAttribute: (name) => name in attrs,
            querySelectorAll: () => [],
            elements: { namedItem: () => null },
        });
    };

    it('looks at forms without novalidate too, and leaves a form marked data-no-check alone', () => {
        const handlers = listen();
        const looked = [];
        const event = (form) => ({ target: form, preventDefault() {}, stopImmediatePropagation() {} });
        const plain = formWith();
        plain.querySelectorAll = () => (looked.push('plain'), []);
        const signIn = formWith({ 'data-no-check': '' });
        signIn.querySelectorAll = () => (looked.push('sign in'), []);

        handlers.submit(event(plain));
        handlers.submit(event(signIn));

        expect(looked).toEqual(['plain']);
    });
});
