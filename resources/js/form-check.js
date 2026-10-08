// U19: before any form is sent (sign in excepted, data-no-check), the rules that need no database
// are checked in the browser: required fields, minimum and maximum length, a whole number inside
// its range, the shape of an email, a pattern (usernames, links, passwords) and a confirmation
// that must match its field. A form with a problem is not
// sent (no request, no spinner); each message shows under its field in the style of the
// server's errors, and the first field with a problem gets the focus. The messages are the
// application's (A10), sent once by the layout; rules that need the database stay with the
// server (up-validate), which checks everything again.

import { nudge } from './scene.js';

const EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

/**
 * The message for one field, or null when it passes. `label` names the field; `valueOf` reads
 * another field of the form by name (a confirmation must match its field).
 */
export function fieldProblem(field, label, messages, valueOf = () => '') {
    // A part of the form can mark itself invalid with its own message (Tebak Kata: every box open).
    if (field.dataset?.invalid === 'true') {
        return field.dataset.invalidMessage ?? fieldProblem({ ...field, dataset: {} }, label, messages, valueOf);
    }

    if (field.type === 'hidden' || field.disabled || field.readOnly) {
        return null;
    }

    const value = String(field.value ?? '');
    const attribute = label.toLowerCase();
    const fill = (text, extra = {}) =>
        Object.entries({ attribute, ...extra }).reduce((out, [key, val]) => out.replace(`:${key}`, String(val)), text);

    if (field.required && value.trim() === '') {
        return fill(messages.required);
    }

    if (value === '') {
        return null;
    }

    if (field.maxLength > 0 && value.length > field.maxLength) {
        return fill(messages.max, { max: field.maxLength });
    }

    if (field.minLength > 0 && value.length < field.minLength) {
        return fill(messages.min, { min: field.minLength });
    }

    // A whole number inside its range (time to vote, points).
    if (field.type === 'number') {
        const number = Number(value);
        const min = field.min === '' || field.min === undefined ? -Infinity : Number(field.min);
        const max = field.max === '' || field.max === undefined ? Infinity : Number(field.max);

        if (!Number.isInteger(number) || number < min || number > max) {
            return field.dataset?.rangeMessage ?? fill(messages.between, { min: field.min, max: field.max });
        }
    }

    if (field.dataset?.same && value !== valueOf(field.dataset.same)) {
        return fill(messages.same, { other: field.dataset.same.replace(/_/g, ' ') });
    }

    if (field.type === 'email' && !EMAIL.test(value.trim())) {
        return fill(messages.email);
    }

    // Unicode mode: letters of every script match \p{L}, as on the server (Tebak Kata answers).
    if (field.pattern && !new RegExp(`^(?:${field.pattern})$`, 'u').test(value)) {
        return field.dataset?.patternMessage ?? fill(messages.required);
    }

    return null;
}

const ERROR_CLASS = 'text-sm font-semibold text-on-danger';

function showProblem(field, message) {
    const group = field.closest('[up-form-group]') ?? field.parentElement;
    const box = field.closest('.border-line-strong, .border-on-danger');
    let error = group.querySelector('[data-check-error]') ?? group.querySelector(`#${CSS.escape(field.id)}-error`);

    if (!error) {
        error = document.createElement('p');
        error.id = `${field.id}-error`;
        error.className = ERROR_CLASS;
        group.appendChild(error);
    }

    error.dataset.checkError = '';
    error.textContent = message;
    field.setAttribute('aria-invalid', 'true');
    field.setAttribute('aria-describedby', error.id);
    box?.classList.replace('border-line-strong', 'border-on-danger');
    // U32: one short shake points at the field (none in the reduced mode, U27).
    nudge(box ?? field, 'shake', { reduced: document.documentElement.dataset.motion === 'reduced' });
}

function clearProblem(field) {
    const group = field.closest('[up-form-group]') ?? field.parentElement;
    group.querySelector('[data-check-error]')?.remove();
    field.removeAttribute('aria-invalid');
    field.closest('.border-on-danger')?.classList.replace('border-on-danger', 'border-line-strong');
}

function labelOf(field) {
    // data-label names a field without a label of its own (the hidden image inputs).
    return (
        field.dataset.label ||
        (field.id && document.querySelector(`label[for="${CSS.escape(field.id)}"]`)?.textContent?.trim()) ||
        field.name
    );
}

/**
 * U19, U26: checks some fields only (a step of New event), with the messages of a sent form.
 * Returns the first field with a problem, or null.
 */
export function checkFields(fields, messages, valueOf = () => '') {
    let first = null;

    for (const field of fields) {
        const message = fieldProblem(field, labelOf(field), messages, valueOf);

        if (message) {
            showProblem(field, message);
            first ??= field;
        } else if (
            field.hasAttribute('aria-invalid') &&
            field.closest('[up-form-group]')?.querySelector('[data-check-error]')
        ) {
            clearProblem(field);
        }
    }

    return first;
}

/**
 * Watches every form (the browser's own checks are off, see app.js). The listener runs in
 * the capture phase, before Unpoly sends the form.
 */
export function checkFormsBeforeSubmit(doc = document, messages = {}) {
    doc.addEventListener(
        'submit',
        (event) => {
            const form = event.target;

            // Every form, except the ones that leave all checks to the server (sign in).
            if (typeof form?.querySelectorAll !== 'function' || form.hasAttribute('data-no-check')) {
                return;
            }

            const valueOf = (name) => form.elements.namedItem(name)?.value ?? '';
            const first = checkFields(form.querySelectorAll('input, textarea'), messages, valueOf);

            if (first) {
                event.preventDefault();
                event.stopImmediatePropagation();
                first.focus();
            }
        },
        true,
    );

    // A field the host corrects loses its message at once.
    doc.addEventListener('input', (event) => {
        const field = event.target;

        if (field instanceof HTMLElement && field.closest('[up-form-group]')?.querySelector('[data-check-error]')) {
            const valueOf = (name) => field.form?.elements.namedItem(name)?.value ?? '';

            if (!fieldProblem(field, labelOf(field), messages, valueOf)) {
                clearProblem(field);
            }
        }
    });
}
