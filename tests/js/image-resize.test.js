import { describe, expect, it } from 'vitest';
import { fileNameFor, fitWithin, imageProblem, outputTypes } from '../../resources/js/image-resize.js';

describe('E8 image checks before upload', () => {
    it('refuses HEIC by type or by name, and anything that is not JPG or PNG', () => {
        expect(imageProblem({ name: 'IMG_0001.HEIC', type: 'image/heic' })).toBe('heic');
        expect(imageProblem({ name: 'IMG_0001.heif', type: '' })).toBe('heic');
        expect(imageProblem({ name: 'scan.gif', type: 'image/gif' })).toBe('type');
        expect(imageProblem({ name: 'notes.txt', type: 'text/plain' })).toBe('type');
        expect(imageProblem({ name: 'photo.JPG', type: 'image/jpeg' })).toBeNull();
        expect(imageProblem({ name: 'map.png', type: 'image/png' })).toBeNull();
    });

    it('makes images at most the given width and keeps their shape (E8, E14)', () => {
        expect(fitWithin(4032, 3024, 1920)).toEqual({ width: 1920, height: 1440 });
        expect(fitWithin(4032, 3024, 720)).toEqual({ width: 720, height: 540 });
        expect(fitWithin(3000, 1, 720)).toEqual({ width: 720, height: 1 });
    });

    it('also fits a tall photo within the height the server takes (K7, audit SEC-4)', () => {
        expect(fitWithin(3024, 4032, 1920)).toEqual({ width: 1440, height: 1920 });
        expect(fitWithin(1080, 20000, 720)).toEqual({ width: 39, height: 720 });
    });

    it('never makes a small image bigger', () => {
        expect(fitWithin(640, 480, 1920)).toEqual({ width: 640, height: 480 });
    });

    it('keeps PNG as PNG and falls back to JPEG only when it stays over 2 MB', () => {
        expect(outputTypes('image/png')).toEqual(['image/png', 'image/jpeg']);
        expect(outputTypes('image/jpeg')).toEqual(['image/jpeg']);
    });

    it('keeps the original name with the extension of the new type (E9)', () => {
        expect(fileNameFor('Eiffel Tower.PNG', 'image/jpeg')).toBe('Eiffel Tower.jpg');
        expect(fileNameFor('map.png', 'image/png')).toBe('map.png');
        expect(fileNameFor('no-extension', 'image/jpeg')).toBe('no-extension.jpg');
    });
});
