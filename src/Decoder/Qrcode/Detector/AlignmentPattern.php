<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Decoder\Qrcode\Detector;

use Cline\Qr\Decoder\AbstractResultPoint;

use function abs;

/**
 * Candidate or confirmed alignment pattern center.
 *
 * Alignment patterns only appear on QR Code versions greater than 1. They are
 * used to correct skew and local distortion after the three finder patterns
 * establish the symbol's rough geometry.
 *
 * @author Sean Owen
 */
final class AlignmentPattern extends AbstractResultPoint
{
    /**
     * @param float|int $posX                Center X coordinate.
     * @param float|int $posY                Center Y coordinate.
     * @param float|int $estimatedModuleSize Estimated module width in pixels.
     */
    public function __construct(
        $posX,
        $posY,
        private readonly int|float $estimatedModuleSize,
    ) {
        parent::__construct($posX, $posY);
    }

    /**
     * Compare this candidate against a nearby observation.
     *
     * The detector uses this to merge repeated sightings of the same physical
     * pattern while tolerating small differences in center location and module
     * size.
     * @param mixed $moduleSize
     * @param mixed $i
     * @param mixed $j
     */
    public function aboutEquals($moduleSize, $i, $j): bool
    {
        if (abs($i - $this->getY()) <= $moduleSize && abs($j - $this->getX()) <= $moduleSize) {
            $moduleSizeDiff = abs($moduleSize - $this->estimatedModuleSize);

            return $moduleSizeDiff <= 1.0 || $moduleSizeDiff <= $this->estimatedModuleSize;
        }

        return false;
    }

    /**
     * Merge this estimate with a newer observation and return the averaged result.
     *
     * The object is immutable, so repeated sightings always yield a new instance
     * rather than mutating the existing candidate in place.
     * @param mixed $i
     * @param mixed $j
     * @param mixed $newModuleSize
     */
    public function combineEstimate($i, $j, $newModuleSize): self
    {
        $combinedX = ($this->getX() + $j) / 2.0;
        $combinedY = ($this->getY() + $i) / 2.0;
        $combinedModuleSize = ($this->estimatedModuleSize + $newModuleSize) / 2.0;

        return new self($combinedX, $combinedY, $combinedModuleSize);
    }
}
