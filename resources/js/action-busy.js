// U16: actions sent with fetch (Live control) show the same spinner as form buttons. The click
// is recorded in the capture phase, before Alpine runs the handler that starts the action, and
// that button carries aria-busy until the action ends (resources/css/app.css draws the spinner).

let lastClicked = null;

export function trackClicks(doc = document) {
    doc.addEventListener(
        'click',
        (event) => {
            lastClicked = event.target?.closest?.('button') ?? null;
        },
        true,
    );
}

/** Runs an action, marking the button that started it busy until it ends, even when it fails. */
export async function withBusyButton(run) {
    const button = lastClicked;
    lastClicked = null;
    button?.setAttribute('aria-busy', 'true');

    try {
        return await run();
    } finally {
        button?.removeAttribute('aria-busy');
    }
}
