<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Decoder;

use Cline\Qr\Decoder\Common\BitArray;
use Cline\Qr\Decoder\Common\BitMatrix;

/**
 * Base luminance-to-binary conversion strategy for decoder pipelines.
 *
 * The decoder works in two stages: a luminance source provides raw image data
 * and a binarizer turns that data into 1-bit rows or matrices. Concrete
 * implementations can trade speed for accuracy, cache expensive work, or vary
 * their thresholding strategy depending on the source image characteristics.
 *
 * Consumers should treat every conversion call as potentially expensive. The
 * abstraction exists so row-based and matrix-based decoding can share the same
 * luminance source while still choosing different binarization algorithms.
 *
 * @author dswitkin@google.com (Daniel Switkin)
 */
abstract class AbstractBinarizer
{
    protected function __construct(
        private readonly AbstractLuminanceSource $source,
    ) {}

    /**
     * Return the luminance source this binarizer reads from.
     *
     * The source is stable for the lifetime of the binarizer instance; concrete
     * implementations should create a fresh binarizer when they need to operate
     * on a different source.
     *
     * @return AbstractLuminanceSource
     */
    final public function getLuminanceSource()
    {
        return $this->source;
    }

    final public function getWidth()
    {
        return $this->source->getWidth();
    }

    final public function getHeight()
    {
        return $this->source->getHeight();
    }

    /**
     * Convert a single row of luminance data into a 1-bit row.
     *
     * Implementations may compute the row from scratch or reuse cached state.
     * Callers should assume the work is expensive and reuse the optional row
     * buffer where possible. The returned `BitArray` is always the authoritative
     * result, even when a preallocated buffer was supplied.
     *
     * @param int           $y   Row index to fetch, which must be within the source height.
     * @param null|BitArray $row Optional reusable buffer. When present, the
     *                           implementation may clear and repopulate it.
     *
     * @throws NotFoundException When the row cannot be binarized.
     * @return BitArray          The row bits, where `true` means black.
     */
    abstract public function getBlackRow(int $y, ?BitArray $row = null): BitArray;

    /**
     * Convert the entire image into a binary matrix.
     *
     * This is the 2D counterpart to `getBlackRow()` and may use different
     * thresholding behavior, so callers must not assume equivalence between a
     * matrix row and a separately fetched row result.
     *
     * @throws NotFoundException When the image cannot be binarized.
     * @return BitMatrix         The 2D matrix of bits for the image, where `true`
     *                           means black.
     */
    abstract public function getBlackMatrix();

    /**
     * Create a fresh binarizer instance for a different luminance source.
     *
     * Binarizers may cache derived data, so cloning the current object would not
     * reliably reset internal state. Implementations should therefore construct a
     * new instance of the same concrete type against the supplied source.
     *
     * @param mixed $source Luminance source for the new binarizer instance.
     *
     * @return self A new concrete binarizer with pristine state.
     */
    abstract public function createBinarizer($source);
}
