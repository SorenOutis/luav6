/**
 * Client-side downscaling for user-selected images.
 *
 * A photo straight from a phone camera is routinely 3–8 MB at 4000px+ on the
 * long edge. Uploading that raw is the single worst part of the profile flow
 * on a low-end device:
 *
 *  - the upload itself takes tens of seconds on mobile data, and anything
 *    over the 10MB server limit fails only *after* the whole body is sent;
 *  - the browser then has to decode that full-resolution bitmap to paint a
 *    96px preview, which is hundreds of megabytes of RGBA in memory and is a
 *    common cause of the tab being killed on 2–3 GB devices.
 *
 * Downscaling first turns both problems into a fraction of the work, and the
 * result is still far larger than any place the image is displayed.
 */

export type CompressOptions = {
    /** Longest edge of the output, in pixels. */
    maxSize?: number;
    /** JPEG/WebP quality, 0–1. */
    quality?: number;
    /** Skip work entirely for files already below this size, in bytes. */
    skipBelowBytes?: number;
};

const DEFAULTS: Required<CompressOptions> = {
    maxSize: 1024,
    quality: 0.85,
    skipBelowBytes: 512 * 1024,
};

/** GIFs may be animated; re-encoding would freeze them on the first frame. */
const isCompressible = (file: File): boolean =>
    /^image\/(jpeg|png|webp)$/.test(file.type);

const loadBitmap = async (
    file: File,
): Promise<ImageBitmap | HTMLImageElement> => {
    // createImageBitmap decodes off the main thread where available, which
    // keeps the UI responsive on weak CPUs.
    if (typeof createImageBitmap === 'function') {
        try {
            return await createImageBitmap(file);
        } catch {
            // Fall through to the <img> path below.
        }
    }

    const url = URL.createObjectURL(file);

    try {
        const image = new Image();
        image.src = url;
        await image.decode();

        return image;
    } finally {
        URL.revokeObjectURL(url);
    }
};

const dimensionsOf = (source: ImageBitmap | HTMLImageElement) => ({
    width:
        source instanceof HTMLImageElement ? source.naturalWidth : source.width,
    height:
        source instanceof HTMLImageElement
            ? source.naturalHeight
            : source.height,
});

/**
 * Returns a downscaled copy of `file`, or the original file when shrinking it
 * is unnecessary or not possible.
 *
 * This never rejects: compression is an optimisation, so any failure falls
 * back to uploading the file the user picked.
 */
export async function compressImage(
    file: File,
    options: CompressOptions = {},
): Promise<File> {
    const { maxSize, quality, skipBelowBytes } = { ...DEFAULTS, ...options };

    if (typeof document === 'undefined') return file;
    if (!isCompressible(file)) return file;
    if (file.size <= skipBelowBytes) return file;

    let source: ImageBitmap | HTMLImageElement | null = null;

    try {
        source = await loadBitmap(file);

        const { width, height } = dimensionsOf(source);
        if (!width || !height) return file;

        const scale = Math.min(1, maxSize / Math.max(width, height));

        // Already small enough — re-encoding would only lose quality.
        if (scale >= 1) return file;

        const canvas = document.createElement('canvas');
        canvas.width = Math.round(width * scale);
        canvas.height = Math.round(height * scale);

        const context = canvas.getContext('2d');
        if (!context) return file;

        context.imageSmoothingQuality = 'high';
        context.drawImage(source, 0, 0, canvas.width, canvas.height);

        const blob = await new Promise<Blob | null>((resolve) => {
            canvas.toBlob((result) => resolve(result), 'image/jpeg', quality);
        });

        // Free the backing bitmap immediately rather than waiting for GC.
        canvas.width = 0;
        canvas.height = 0;

        if (!blob || blob.size >= file.size) return file;

        const baseName = file.name.replace(/\.[^./\\]+$/, '');

        return new File([blob], `${baseName}.jpg`, {
            type: 'image/jpeg',
            lastModified: Date.now(),
        });
    } catch {
        return file;
    } finally {
        if (source && 'close' in source) source.close();
    }
}

/**
 * Replace the selection in a file input with a single file.
 *
 * Needed because the profile form is a real multipart `<form>`: the value the
 * browser submits is whatever sits in `input.files`, so a compressed file has
 * to be written back there to actually be the thing that gets uploaded.
 */
export function setInputFile(input: HTMLInputElement, file: File): void {
    if (typeof DataTransfer === 'undefined') return;

    const transfer = new DataTransfer();
    transfer.items.add(file);
    input.files = transfer.files;
}
