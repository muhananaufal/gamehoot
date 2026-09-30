// E8, E14: images are made smaller in the host's browser before upload: 1920 px wide for the
// projector and 720 px for phones. Only then is the 2 MB limit checked, and the server checks
// every rule again. Drawing on a canvas also drops the photo's metadata, such as GPS.

export const LARGE_WIDTH = 1920;
export const SMALL_WIDTH = 720;
export const MAX_BYTES = 2 * 1024 * 1024;

const HEIC_TYPES = ['image/heic', 'image/heif'];
const HEIC_NAME = /\.(heic|heif)$/i;
const EXTENSIONS = { 'image/jpeg': 'jpg', 'image/png': 'png' };
const JPEG_QUALITY = 0.85;

/** Returns null when the file can be used, 'heic' for iPhone photos, 'type' for anything else. */
export function imageProblem(file) {
    const type = (file.type ?? '').toLowerCase();

    if (HEIC_TYPES.includes(type) || HEIC_NAME.test(file.name ?? '')) {
        return 'heic';
    }

    return type in EXTENSIONS ? null : 'type';
}

/** The size of an image at most `max` pixels wide, in the same shape. Never enlarges. */
export function fitWidth(width, height, max) {
    if (width <= max) {
        return { width, height };
    }

    return { width: max, height: Math.max(1, Math.round((height * max) / width)) };
}

/** PNG stays PNG unless it is still over 2 MB, then JPEG (photos rarely need transparency). */
export function outputTypes(type) {
    return type === 'image/png' ? ['image/png', 'image/jpeg'] : ['image/jpeg'];
}

/** E9: answer images keep their original name on the server, so only the extension changes. */
export function fileNameFor(name, type) {
    const base = name.includes('.') ? name.slice(0, name.lastIndexOf('.')) : name;

    return `${base}.${EXTENSIONS[type]}`;
}

/**
 * Draws the image at most `maxWidth` wide and returns it as a File, or null when it stays over
 * 2 MB. Throws when the browser cannot read the image.
 */
export async function resizeImage(file, maxWidth) {
    const bitmap = await createImageBitmap(file);
    const size = fitWidth(bitmap.width, bitmap.height, maxWidth);
    const canvas = document.createElement('canvas');
    canvas.width = size.width;
    canvas.height = size.height;
    canvas.getContext('2d').drawImage(bitmap, 0, 0, size.width, size.height);
    bitmap.close();

    for (const type of outputTypes(file.type)) {
        const blob = await new Promise((resolve) => canvas.toBlob(resolve, type, JPEG_QUALITY));

        if (blob !== null && blob.size <= MAX_BYTES) {
            return new File([blob], fileNameFor(file.name, type), { type });
        }
    }

    return null;
}
