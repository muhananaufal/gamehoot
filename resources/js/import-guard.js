// B-4, U24: the import review lives in an Unpoly modal. Closing it (✕, Escape, Cancel) after
// a change asks first; without a change it closes at once. Unpoly emits up:layer:dismiss before
// it closes a modal, and a prevented event keeps it open. Save accepts the modal instead, so it
// never asks.

/**
 * @param {{ layer: { on: Function, dismiss: Function }, dirty: () => boolean, ask: (go: () => void) => void }} options
 *   ask shows the question and calls go once the host agrees
 */
export function guardLeaving({ layer, dirty, ask }) {
    let allowed = false;

    const off = layer.on('up:layer:dismiss', (event) => {
        if (allowed || !dirty()) {
            return;
        }

        event.preventDefault();
        ask(() => {
            allowed = true;
            layer.dismiss();
        });
    });

    return {
        /** A page that is being sent (Save, Back) no longer asks. */
        allow() {
            allowed = true;
        },
        off,
    };
}
