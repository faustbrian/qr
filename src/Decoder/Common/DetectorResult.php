<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Decoder\Common;

/**
 * Result of the detector stage before bitstream decoding begins.
 *
 * The object carries the sampled black/white matrix and any corner or finder
 * points that were discovered while locating the barcode in the source image.
 * Downstream components use it as the handoff between geometric detection and
 * payload extraction.
 *
 * @author Sean Owen
 */
final class DetectorResult
{
    /**
     * Capture the detected matrix and its supporting points.
     */
    public function __construct(
        private readonly mixed $bits,
        private readonly mixed $points,
    ) {}

    /**
     * Return the detected bit matrix.
     */
    public function getBits()
    {
        return $this->bits;
    }

    /**
     * Return the points identified during detection.
     */
    public function getPoints()
    {
        return $this->points;
    }
}
