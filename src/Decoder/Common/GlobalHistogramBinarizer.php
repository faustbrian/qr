<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Decoder\Common;

/**
 * Global-threshold binarizer used as the simpler fallback strategy.
 *
 * This implementation estimates a single black point from image luminance
 * histograms. It is cheaper than local thresholding and still useful on
 * uniformly lit images, but it can struggle with shadows and gradients where
 * {@see HybridBinarizer} performs better.
 *
 * @author dswitkin@google.com (Daniel Switkin)
 * @author Sean Owen
 */
final class GlobalHistogramBinarizer extends AbstractGlobalHistogramBinarizer
{
    /**
     * Create another global-histogram binarizer for a different source.
     * @param mixed $source
     */
    public function createBinarizer($source): self
    {
        return new self($source);
    }
}
