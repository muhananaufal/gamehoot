// While a page form is being sent, its submit buttons are locked and the clicked one is marked
// busy, so a slow save is not sent twice and the host sees that it is on its way.

/**
 * @returns {boolean} whether the form was marked (dialog buttons only close their dialog)
 */
export function markBusy(form, submitter) {
    // A dialog form only closes its dialog; nothing comes back to unlock it, so it is never marked.
    if (submitter?.getAttribute('formmethod') === 'dialog' || form.getAttribute?.('method') === 'dialog') {
        return false;
    }

    form.setAttribute('aria-busy', 'true');
    submitter?.setAttribute('aria-busy', 'true');

    for (const button of form.querySelectorAll('button[type="submit"], button:not([type])')) {
        button.disabled = true;
    }

    return true;
}

export function markIdle(form) {
    form.removeAttribute('aria-busy');

    for (const button of form.querySelectorAll('button[type="submit"], button:not([type])')) {
        button.disabled = false;
        button.removeAttribute('aria-busy');
    }
}

/**
 * U16: the same lock for a form Unpoly sends (every form of the host panel). Unpoly halts the
 * submit event before watchFormSubmits sees it, so the lock runs as an Unpoly preview, which
 * Unpoly undoes once the request ends, whether it succeeded or failed.
 */
export function busyPreview(form, submitter) {
    return (preview) => {
        if (markBusy(form, submitter)) {
            preview.undo(() => markIdle(form));
        }
    };
}

export function watchFormSubmits(doc = document, win = window) {
    doc.addEventListener('submit', (event) => {
        if (event.defaultPrevented) {
            return;
        }

        // After the browser has read the form: a button disabled now would drop its own value.
        win.setTimeout(() => markBusy(event.target, event.submitter), 0);
    });

    // Back and forward keep the page in memory with its buttons still locked.
    win.addEventListener('pageshow', (event) => {
        if (event.persisted) {
            doc.querySelectorAll('form[aria-busy]').forEach(markIdle);
        }
    });
}
