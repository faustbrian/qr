<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Decoder;

use Cline\Qr\Decoder\Common\BitMatrix;

/**
 * Cached binary view over a luminance source.
 *
 * Reader implementations accept this object rather than working directly with
 * raw image data. The bitmap lazily asks its binarizer for row or matrix data
 * and caches the expensive 2D matrix representation after the first request so
 * multiple readers can reuse the same conversion work.
 *
 * Cropping and rotation are delegated to the underlying luminance source and
 * re-wrapped in a fresh binarizer so the transformed bitmap preserves the same
 * decoding pipeline as the original.
 *
 * @author dswitkin@google.com (Daniel Switkin)
 */
final class BinaryBitmap
{
    private ?BitMatrix $matrix = null;

    private readonly AbstractBinarizer $binarizer;

    public function __construct(AbstractBinarizer $binarizer)
    {
        if ($binarizer === null) {
            throw InvalidArgumentException::withMessage('Binarizer must be non-null.');
        }
        $this->binarizer = $binarizer;
    }

    /**
     * Return the bitmap width as reported by the underlying luminance source.
     *
     * @return int The width of the bitmap.
     */
    public function getWidth()
    {
        return $this->binarizer->getWidth();
    }

    /**
     * Return the bitmap height as reported by the underlying luminance source.
     *
     * @return int The height of the bitmap.
     */
    public function getHeight()
    {
        return $this->binarizer->getHeight();
    }

    /**
     * Convert a single row of luminance data to 1-bit form.
     *
     * The method delegates to the wrapped binarizer and therefore inherits its
     * performance characteristics and row-reuse semantics.
     *
     * @param int                  $y   Row index to fetch.
     * @param null|Common\BitArray $row Optional reusable buffer.
     *
     * @throws NotFoundException When the row cannot be binarized.
     * @return Common\BitArray   The row bits, where `true` means black.
     */
    public function getBlackRow($y, $row): Common\BitArray
    {
        return $this->binarizer->getBlackRow($y, $row);
    }

    /**
     * Determine whether the underlying source supports cropping.
     *
     * @return bool Whether this bitmap can be cropped.
     */
    public function isCropSupported()
    {
        return $this->binarizer->getLuminanceSource()->isCropSupported();
    }

    /**
     * Return a cropped bitmap view.
     *
     * The crop is applied to the luminance source first, then wrapped in a fresh
     * binarizer so the returned bitmap remains decoupled from the original
     * object's cached matrix state.
     *
     * @param int $left   Left coordinate of the crop rectangle.
     * @param int $top    Top coordinate of the crop rectangle.
     * @param int $width  Width of the crop rectangle.
     * @param int $height Height of the crop rectangle.
     *
     * @return self A cropped version of this bitmap.
     */
    public function crop($left, $top, $width, $height): self
    {
        $newSource = $this->binarizer->getLuminanceSource()->crop($left, $top, $width, $height);

        return new self($this->binarizer->createBinarizer($newSource));
    }

    /**
     * Determine whether the underlying source supports counter-clockwise rotation.
     *
     * @return bool Whether bitmap supports counter-clockwise rotation.
     */
    public function isRotateSupported()
    {
        return $this->binarizer->getLuminanceSource()->isRotateSupported();
    }

    /**
     * Return a bitmap rotated 90 degrees counter-clockwise.
     *
     * The rotation is delegated to the luminance source and the result is wrapped
     * in a new binarizer instance so cached matrix state from the original bitmap
     * is not reused incorrectly.
     *
     * @return self A rotated version of this bitmap.
     */
    public function rotateCounterClockwise(): self
    {
        $newSource = $this->binarizer->getLuminanceSource()->rotateCounterClockwise();

        return new self($this->binarizer->createBinarizer($newSource));
    }

    /**
     * Return a bitmap rotated 45 degrees counter-clockwise.
     *
     * This is primarily used by detector code that can recover symbols from
     * tilted images without forcing callers to pre-process the input themselves.
     *
     * @return self A rotated version of this bitmap.
     */
    public function rotateCounterClockwise45(): self
    {
        $newSource = $this->binarizer->getLuminanceSource()->rotateCounterClockwise45();

        return new self($this->binarizer->createBinarizer($newSource));
    }

    /**
     * Render the cached binary matrix as text, suppressing decode failures.
     *
     * When the image cannot be binarized, the method returns an empty string
     * rather than bubbling the exception. That keeps it safe for debugging and
     * logging paths that want a best-effort representation.
     */
    public function toString(): string
    {
        try {
            return $this->getBlackMatrix()->toString();
        } catch (NotFoundException) {
        }

        return '';
    }

    /**
     * Convert the full image into a binary matrix and cache the result.
     *
     * The first call performs the expensive binarization work. Later calls return
     * the cached matrix directly, which is important when multiple readers inspect
     * the same bitmap during a decode attempt.
     *
     * @throws NotFoundException When the image cannot be binarized.
     * @return BitMatrix         The 2D matrix of bits for the image, where `true`
     *                           means black.
     */
    public function getBlackMatrix()
    {
        // The matrix is created on demand the first time it is requested, then cached. There are two
        // reasons for this:
        // 1. This work will never be done if the caller only installs 1D Reader objects, or if a
        //    1D Reader finds a barcode before the 2D Readers run.
        // 2. This work will only be done once even if the caller installs multiple 2D Readers.
        if ($this->matrix === null) {
            $this->matrix = $this->binarizer->getBlackMatrix();
        }

        return $this->matrix;
    }
}
