// U33: the tooltips of the host panel, in place of the browser's title (which cannot be styled,
// comes late and never shows for the keyboard). The text is the element's aria-label (or its
// title when it has no label), so it is written once. WCAG 1.4.13: shown on hover and on keyboard
// focus, closed with Escape without moving the focus, kept while the pointer moves onto it, and
// kept until its trigger is left. Placed by this small script (CSS anchor positioning is not
// everywhere yet); it appears with --motion-instant, at once in the reduced mode (app.css).
export const HOVER_DELAY_MS = 300;
const HIDE_GRACE_MS = 100;
const MARGIN = 8;
const GAP = 8;

// Icon buttons with a label, elements with a title (drag handles, links) and a sidebar link.
const TRIGGERS = 'button[aria-label], button[title], a[title], span[title], [data-tip-title], [data-tip-collapsed]';

/** The text of the tooltip of an element, or null when it needs none. */
export function tipText(el) {
    if (!el || el.tagName === 'IFRAME') {
        return null;
    }

    const text = (
        el.getAttribute('aria-label') ??
        el.getAttribute('data-tip-title') ??
        el.getAttribute('title') ??
        ''
    ).trim();

    if (text === '') {
        return null;
    }

    // A sidebar link names itself unless the sidebar is narrowed to icons.
    if (el.hasAttribute('data-tip-collapsed')) {
        return el.closest('[data-collapsed="true"]') ? text : null;
    }

    // Text already on screen needs no tooltip.
    const shown = (el.textContent ?? '').replace(/\s+/g, ' ').trim().toLowerCase();

    return shown !== '' && shown.includes(text.toLowerCase()) ? null : text;
}

/** Where the tooltip goes: beside its anchor, on the other side when the window would cut it. */
export function placeTip({ anchor, tip, viewport, prefer = 'top' }) {
    const centreX = (anchor.left + anchor.right) / 2;
    const centreY = (anchor.top + anchor.bottom) / 2;
    const clampX = (left) => Math.min(Math.max(left, MARGIN), viewport.width - tip.width - MARGIN);
    const clampY = (top) => Math.min(Math.max(top, MARGIN), viewport.height - tip.height - MARGIN);

    if (prefer === 'right' || prefer === 'left') {
        const right = anchor.right + GAP;
        const left = anchor.left - GAP - tip.width;
        const fitsRight = right + tip.width <= viewport.width - MARGIN;
        const side = prefer === 'right' ? (fitsRight ? 'right' : 'left') : left >= MARGIN ? 'left' : 'right';

        return { top: clampY(Math.round(centreY - tip.height / 2)), left: side === 'right' ? right : left, side };
    }

    const above = anchor.top - GAP - tip.height;
    const side = above >= MARGIN ? 'top' : 'bottom';

    return {
        top: side === 'top' ? above : anchor.bottom + GAP,
        left: clampX(Math.round(centreX - tip.width / 2)),
        side,
    };
}

export function tooltips({ doc = globalThis.document, win = globalThis.window } = {}) {
    let tip = null;
    let current = null;
    let showTimer = null;
    let hideTimer = null;

    const trigger = (target) => {
        const el = target?.closest?.(TRIGGERS);

        return el && el.closest('[data-host-shell]') ? el : null;
    };

    // The browser's own title would show beside ours; the text stays in data-tip-title.
    const quietTitle = (el) => {
        if (el.hasAttribute('title')) {
            el.setAttribute('data-tip-title', el.getAttribute('title'));
            el.removeAttribute('title');
        }
    };

    const hide = () => {
        clearTimeout(showTimer);
        clearTimeout(hideTimer);

        if (tip) {
            tip.hidden = true;
        }

        current = null;
    };

    const later = () => {
        clearTimeout(hideTimer);
        hideTimer = setTimeout(hide, HIDE_GRACE_MS);
    };

    const ensure = () => {
        if (!tip) {
            tip = doc.createElement('div');
            tip.id = 'host-tip';
            tip.className = 'host-tip';
            tip.setAttribute('role', 'tooltip');
            tip.hidden = true;
            // Hoverable: the pointer may move onto the tooltip without it closing.
            tip.addEventListener('pointerenter', () => clearTimeout(hideTimer));
            tip.addEventListener('pointerleave', (event) => {
                if (!current?.contains(event.relatedTarget)) {
                    later();
                }
            });
            doc.body.append(tip);
        }

        return tip;
    };

    const show = (el) => {
        const text = tipText(el);

        if (!text || !el.isConnected) {
            return;
        }

        const box = ensure();
        clearTimeout(hideTimer);
        box.textContent = text;
        box.hidden = false;
        const place = placeTip({
            anchor: el.getBoundingClientRect(),
            tip: { width: box.offsetWidth, height: box.offsetHeight },
            viewport: { width: win.innerWidth, height: win.innerHeight },
            prefer: el.getAttribute('data-tip-side') ?? 'top',
        });
        box.style.top = `${place.top}px`;
        box.style.left = `${place.left}px`;
        box.dataset.side = place.side;
        current = el;
    };

    doc.addEventListener('pointerover', (event) => {
        const el = trigger(event.target);

        if (!el) {
            return;
        }

        quietTitle(el);

        if (el === current) {
            clearTimeout(hideTimer);

            return;
        }

        clearTimeout(showTimer);
        showTimer = setTimeout(() => show(el), HOVER_DELAY_MS);
    });

    doc.addEventListener('pointerout', (event) => {
        if (!trigger(event.target)) {
            return;
        }

        clearTimeout(showTimer);

        if (tip?.contains(event.relatedTarget) || current?.contains(event.relatedTarget)) {
            return;
        }

        later();
    });

    // Keyboard focus shows at once; a mouse click that focuses a button does not.
    doc.addEventListener('focusin', (event) => {
        const el = trigger(event.target);

        if (el && event.target.matches?.(':focus-visible')) {
            quietTitle(el);
            show(el);
        }
    });

    doc.addEventListener('focusout', () => {
        if (current) {
            hide();
        }
    });

    // Escape closes the tooltip and leaves the focus where it is.
    doc.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && tip && !tip.hidden) {
            hide();
        }
    });

    // A scroll or a new page moves the button away from its tooltip.
    win.addEventListener('scroll', hide, true);
    doc.addEventListener('up:location:changed', hide);
}
