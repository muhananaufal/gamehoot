// U13: every native <dialog> (confirmations, the date picker, reset password) closes on a click
// outside its box, like the Unpoly modals. The box is filled by its content, so a click whose
// target is the <dialog> itself landed on the backdrop. The press must start there too: a text
// selection that ends outside the box is not a click outside.

export function closeDialogsOnBackdrop(doc = document) {
    let pressedOn = null;

    doc.addEventListener('pointerdown', (event) => {
        pressedOn = event.target;
    });

    doc.addEventListener('click', (event) => {
        const target = event.target;

        if (target?.tagName === 'DIALOG' && target.open && pressedOn === target) {
            target.close();
        }
    });
}
