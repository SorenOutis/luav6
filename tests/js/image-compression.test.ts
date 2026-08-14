import { describe, expect, it } from 'vitest';
import { compressImage } from '@/lib/image-compression';

/**
 * jsdom has no real canvas encoder, so `toBlob` yields null and compression
 * cannot succeed. That is exactly the fallback path worth pinning down: the
 * helper must never lose the user's file when optimisation is unavailable.
 */

const fileOf = (name: string, type: string, bytes: number): File =>
    new File([new Uint8Array(bytes)], name, { type });

describe('compressImage', () => {
    it('returns small files untouched instead of re-encoding them', async () => {
        const file = fileOf('small.jpg', 'image/jpeg', 1024);

        await expect(compressImage(file)).resolves.toBe(file);
    });

    it('never re-encodes a GIF, which would freeze an animation', async () => {
        const gif = fileOf('loop.gif', 'image/gif', 4 * 1024 * 1024);

        await expect(compressImage(gif)).resolves.toBe(gif);
    });

    it('leaves non-images alone', async () => {
        const pdf = fileOf('doc.pdf', 'application/pdf', 4 * 1024 * 1024);

        await expect(compressImage(pdf)).resolves.toBe(pdf);
    });

    it('falls back to the original file when encoding is unavailable', async () => {
        const big = fileOf('photo.jpg', 'image/jpeg', 4 * 1024 * 1024);

        // Must resolve, not reject: compression is an optimisation only.
        await expect(compressImage(big)).resolves.toBeInstanceOf(File);
    });

    it('honours a custom skip threshold', async () => {
        const file = fileOf('mid.jpg', 'image/jpeg', 100 * 1024);

        await expect(
            compressImage(file, { skipBelowBytes: 200 * 1024 }),
        ).resolves.toBe(file);
    });
});
