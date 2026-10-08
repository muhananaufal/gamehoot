// U13: every native <dialog> (confirmations, the date picker, reset password) closes on a click
// outside its box, like the Unpoly modals. The box is filled by its content, so a click whose
// target is the <dialog> itself landed on the backdrop. The press must start there too: a text
// selection that ends outside the box is not a click outside.
//
// D-10: when a dialog closes, the browser gives the focus back to the button that opened it, and
// shows its focus ring even after a mouse click; next to its sibling the button then looks bigger.
// After a mouse close the focus is dropped; keyboard users keep it, to see where they are.

export function closeDialogsOnBackdrop(doc = document, { defer = (run) => setTimeout(run, 0) } = {}) {
    let pressedOn = null;
    let lastInput = 'pointer';

    doc.addEventListener('pointerdown', (event) => {
        pressedOn = event.target;
        lastInput = 'pointer';
    });

    doc.addEventListener('keydown', () => {
        lastInput = 'keyboard';
    });

    doc.addEventListener('click', (event) => {
        const target = event.target;

        // U24: the cancel event goes first, as with Escape, so a dialog holding typed work can
        // ask before it closes (New event); a prevented event keeps it open.
        if (target?.tagName === 'DIALOG' && target.open && pressedOn === target) {
            if (target.dispatchEvent?.(new Event('cancel', { cancelable: true })) !== false) {
                target.close();
            }
        }
    });

    // "close" does not bubble: listen in the capture phase. The focus returns after the event.
    doc.addEventListener(
        'close',
        () => {
            if (lastInput !== 'pointer') {
                return;
            }

            defer(() => {
                if (doc.activeElement?.tagName === 'BUTTON') {
                    doc.activeElement.blur();
                }
            });
        },
        true,
    );
}
