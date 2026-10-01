import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const resize = vi.fn();

vi.mock('../../resources/js/image-resize.js', () => ({
    LARGE_WIDTH: 1920,
    SMALL_WIDTH: 720,
    imageProblem: () => null,
    resizeImage: (...args) => resize(...args),
}));

const { imagePicker } = await import('../../resources/js/image-picker.js');

class FakeDataTransfer {
    constructor() {
        const list = [];
        this.items = { add: (file) => list.push(file) };
        this.files = list;
    }
}

function picker(current = null) {
    const component = imagePicker({ current, labels: { too_big: 'Too big', unreadable: 'Unreadable' } });
    component.$refs = { large: { files: null, value: '' }, small: { files: null, value: '' } };

    return component;
}

const pickEvent = (name) => ({ target: { files: [{ name, type: 'image/jpeg', size: 1000 }], value: name } });

beforeEach(() => {
    vi.stubGlobal('DataTransfer', FakeDataTransfer);
    vi.stubGlobal('URL', { createObjectURL: () => 'blob:preview' });
});

afterEach(() => {
    vi.unstubAllGlobals();
    resize.mockReset();
});

describe('E8 image picker', () => {
    it('names the picked file, since the browser field is cleared after resizing', async () => {
        resize.mockResolvedValue(new Blob(['x']));
        const component = picker();

        await component.pick(pickEvent('Eiffel Tower.jpg'));

        expect(component.fileName).toBe('Eiffel Tower.jpg');
        expect(component.preview).toBe('blob:preview');
    });

    it('goes back to the stored image when a pick fails, so nothing looks attached that is not', async () => {
        resize.mockResolvedValueOnce(new Blob(['x'])).mockResolvedValueOnce(new Blob(['x']));
        const component = picker('/media/stored-w720.jpg');
        await component.pick(pickEvent('good.jpg'));

        resize.mockResolvedValue(null);
        await component.pick(pickEvent('huge.jpg'));

        expect(component.error).toBe('Too big');
        expect(component.preview).toBe('/media/stored-w720.jpg');
        expect(component.fileName).toBe('');
    });
});
