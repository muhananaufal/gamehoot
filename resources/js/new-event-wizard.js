// U26: New event in four steps: ① Name and link, ② Names, ③ Games, ④ Review. Nothing is saved
// before Create event; the steps only hold what the host enters. Once step 1 is valid every
// step can be opened from the stepper. The names step uses the Add names rules (name-batch.js)
// and adds the names of a CSV file read by the server; the games step is the Add games picker.

import { checkFields, fieldProblem } from './form-check.js';
import { checkNames, countText, problemText } from './name-batch.js';
import { packPicker } from './pack-picker.js';

export const STEPS = 4;

const NEWLINE = String.fromCharCode(10);

/**
 * @param {{ texts: object, parseUrl: string, csrf?: string, start?: number, detailsValid?: boolean,
 *   names?: string, picked?: string[], titles?: Record<string, string>, fetch?: Function }} options
 */
export function newEventWizard({
    texts,
    parseUrl,
    csrf = '',
    start = 1,
    detailsValid = false,
    names = '',
    picked = [],
    titles = {},
    fetch = (...args) => window.fetch(...args),
}) {
    const picker = packPicker({ labels: { one: '', other: '' } });

    return {
        step: start,
        detailsValid,
        // Step 1 was passed with Next once (or the server sent the form back past it).
        detailsChecked: detailsValid,
        text: names,
        picked: [...picked],
        titles: { ...titles },
        importing: false,
        importNote: '',
        importError: '',
        beforeImport: null,

        // The Add games picker (pack-picker.js), on this component so the pack list can call it.
        pick: picker.pick,
        orderOf: picker.orderOf,
        chips: picker.chips,

        get nameCheck() {
            return checkNames(this.text, []);
        },

        get namesOk() {
            return this.nameCheck.names.length === 0 || this.nameCheck.ok;
        },

        get nameCount() {
            return countText(texts.count, this.nameCheck.names.length);
        },

        /** Skip while the step is empty, Next once something was entered. */
        get skippable() {
            return (
                (this.step === 2 && this.nameCheck.names.length === 0) || (this.step === 3 && this.picked.length === 0)
            );
        },

        get canCreate() {
            return this.detailsValid && this.namesOk;
        },

        canGo(step) {
            return step === 1 || this.detailsValid;
        },

        go(step) {
            if (this.canGo(step)) {
                this.step = step;
            }
        },

        next() {
            if (this.step === 2 && !this.namesOk) {
                return;
            }

            this.step = Math.min(STEPS, this.step + 1);
        },

        back() {
            this.step = Math.max(1, this.step - 1);
        },

        /**
         * Next on step 1: the checks of a sent form (U19) for its fields, and a link the server
         * already refused (taken) still holds it back.
         */
        nextFromDetails(container) {
            const form = container.closest('form');
            const valueOf = (name) => form?.elements.namedItem(name)?.value ?? '';
            const messages = JSON.parse(document.querySelector('meta[name="form-messages"]')?.content ?? '{}');
            const problem =
                checkFields(container.querySelectorAll('input, textarea'), messages, valueOf) ??
                container.querySelector('[aria-invalid="true"]');

            if (problem) {
                problem.focus();

                return;
            }

            this.detailsValid = true;
            this.detailsChecked = true;
            this.step = 2;
        },

        /** Reads a CSV file on the server, without saving it, and adds its names under the list. */
        async importFile(file) {
            if (!file) {
                return;
            }

            const body = new FormData();
            body.append('file', file);
            this.importing = true;
            this.importError = '';

            try {
                const response = await fetch(parseUrl, {
                    method: 'POST',
                    body,
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
                });
                const answer = await response.json();

                if (!response.ok) {
                    this.importError = answer?.errors?.file?.[0] ?? answer?.message ?? '';

                    return;
                }

                this.beforeImport = this.text;
                const kept = this.text.replace(/\s+$/u, '');
                this.text = [kept, ...answer.names].filter((line) => line !== '').join(NEWLINE);
                this.importNote = countText(texts.imported, answer.names.length).replace('__F__', answer.file);
            } finally {
                this.importing = false;
            }
        },

        /** Takes the names of the last file out again. */
        undoImport() {
            if (this.beforeImport === null) {
                return;
            }

            this.text = this.beforeImport;
            this.beforeImport = null;
            this.importNote = '';
        },

        /**
         * A change on step 1 keeps the other steps open while step 1 stays valid, closes them while
         * it is not, and opens them again once it is (never before step 1 was passed with Next).
         */
        refreshDetails(valid) {
            this.detailsValid = this.detailsChecked && valid;
        },

        /** The checks of step 1 without showing anything; the server checks the link again. */
        quietlyValid(container) {
            const form = container.closest('form');
            const valueOf = (name) => form?.elements.namedItem(name)?.value ?? '';
            const messages = JSON.parse(document.querySelector('meta[name="form-messages"]')?.content ?? '{}');

            return [...container.querySelectorAll('input, textarea')].every(
                (field) => fieldProblem(field, '', messages, valueOf) === null,
            );
        },

        /** A problem of the names step, in the words of the server (name-batch.js). */
        lineProblem(problem) {
            return problemText(problem, texts);
        },

        /** What step 1 holds, read from its fields, for the review and the leave check. */
        details() {
            const form = this.$refs.wizardForm;
            const value = (name) => form?.elements.namedItem(name)?.value ?? '';
            const theme = form?.querySelector('input[name="screen_theme"]:checked')?.value ?? 'dark';
            const devices = form?.querySelector('input[name="show_on_devices"][type="checkbox"]')?.checked ?? false;

            return {
                name: value('name').trim(),
                slug: value('slug').trim(),
                summary: [texts.themes?.[theme] ?? theme, devices ? texts.devices?.on : texts.devices?.off]
                    .filter(Boolean)
                    .join(' · '),
            };
        },

        leaving: false,

        /** Cancel: at once when nothing was entered, else after the question. */
        requestClose() {
            if (this.dirty(this.details())) {
                this.askDiscard();

                return;
            }

            this.$el.closest('dialog')?.close();
        },

        askDiscard() {
            this.$refs.discard?.showModal();
        },

        /** Drops what was entered and closes New event. */
        discard() {
            this.$refs.wizardForm?.reset();
            Object.assign(this, {
                step: 1,
                detailsValid: false,
                text: '',
                picked: [],
                importNote: '',
                importError: '',
                beforeImport: null,
            });
            this.leaving = true;
            this.$refs.wizardForm?.closest('dialog')?.close();
            this.leaving = false;
        },

        /** U24: whether leaving would drop something the host entered. */
        dirty({ name, slug }) {
            return name.trim() !== '' || slug.trim() !== '' || this.text.trim() !== '' || this.picked.length > 0;
        },
    };
}
