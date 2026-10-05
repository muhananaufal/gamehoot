// U19: before a host form is sent, simple rules are checked in the browser: required fields,
// maximum length, the shape of an email and a pattern (usernames). A form with a problem is not
// sent (no request, no spinner); each message shows under its field in the style of the
// server's errors, and the first field with a problem gets the focus. The messages are the
// application's (A10), sent once by the layout; rules that need the database stay with the
// server (up-validate), which checks everything again.

const EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

/** The message for one field, or null when it passes. `label` names the field. */
export function fieldProblem(field, label, messages) {
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

    if (field.type === 'email' && !EMAIL.test(value.trim())) {
        return fill(messages.email);
    }

    if (field.pattern && !new RegExp(`^(?:${field.pattern})$`).test(value)) {
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
}

function clearProblem(field) {
    const group = field.closest('[up-form-group]') ?? field.parentElement;
    group.querySelector('[data-check-error]')?.remove();
    field.removeAttribute('aria-invalid');
    field.closest('.border-on-danger')?.classList.replace('border-on-danger', 'border-line-strong');
}

function labelOf(field) {
    return (
        (field.id && document.querySelector(`label[for="${CSS.escape(field.id)}"]`)?.textContent?.trim()) || field.name
    );
}

/**
 * Watches every host form that leaves validation to the app (novalidate). The listener runs in
 * the capture phase, before Unpoly sends the form.
 */
export function checkFormsBeforeSubmit(doc = document, messages = {}) {
    doc.addEventListener(
        'submit',
        (event) => {
            const form = event.target;

            if (!(form instanceof HTMLFormElement) || !form.noValidate) {
                return;
            }

            let first = null;

            for (const field of form.querySelectorAll('input, textarea')) {
                const message = fieldProblem(field, labelOf(field), messages);

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
            if (!fieldProblem(field, labelOf(field), messages)) {
                clearProblem(field);
            }
        }
    });
}
