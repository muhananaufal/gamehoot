import { imageProblem, LARGE_WIDTH, resizeImage, SMALL_WIDTH } from './image-resize.js';

// E8, E14: one image field of a question form. The picked file never leaves the browser as is:
// it is drawn at 1920 and 720 px and the two results are put into the named file inputs, so the
// form posts as usual and server errors come back the usual way.
export function imagePicker({ current = null, labels = {} } = {}) {
    return {
        preview: current,
        // The visible name of the picked file: the browser field is cleared after resizing.
        fileName: '',
        error: '',
        busy: false,

        async pick(event) {
            const input = event.target;
            const file = input.files?.[0];
            this.error = '';

            if (!file) {
                return;
            }

            const problem = imageProblem(file);

            if (problem !== null) {
                this.fail(labels[problem]);
                input.value = '';

                return;
            }

            this.busy = true;

            try {
                const [large, small] = await Promise.all([
                    resizeImage(file, LARGE_WIDTH),
                    resizeImage(file, SMALL_WIDTH),
                ]);

                if (large === null || small === null) {
                    this.fail(labels.too_big);
                } else {
                    this.put(this.$refs.large, large);
                    this.put(this.$refs.small, small);
                    this.preview = URL.createObjectURL(small);
                    this.fileName = file.name;
                }
            } catch {
                this.fail(labels.unreadable);
            } finally {
                this.busy = false;
                input.value = '';
            }
        },

        put(input, file) {
            const files = new DataTransfer();
            files.items.add(file);
            input.files = files.files;
        },

        /** A failed pick goes back to the stored image, so nothing looks attached that is not. */
        fail(message) {
            this.error = message;
            this.preview = current;
            this.fileName = '';
            this.$refs.large.value = '';
            this.$refs.small.value = '';
        },
    };
}
