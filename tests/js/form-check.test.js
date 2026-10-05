import { describe, expect, it } from 'vitest';
import { fieldProblem } from '../../resources/js/form-check.js';

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
