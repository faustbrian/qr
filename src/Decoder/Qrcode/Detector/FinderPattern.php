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
 * Candidate or confirmed finder pattern center.
 *
 * Finder patterns are the large corner markers that establish the QR Code's
 * orientation and approximate scale. The detector keeps a hit count so repeat
 * sightings of the same center can be merged rather than tracked separately.
 *
 * @author Sean Owen
 */
final class FinderPattern extends AbstractResultPoint
{
    /**
     * @param float|int $posX                Center X coordinate.
     * @param float|int $posY                Center Y coordinate.
     * @param float|int $estimatedModuleSize Estimated module size in pixels.
     * @param int       $count               Number of merged observations.
     */
    public function __construct(
        $posX,
        $posY,
        private readonly int|float $estimatedModuleSize,
        private readonly int $count = 1,
    ) {
        parent::__construct($posX, $posY);
    }

    /**
     * Return the estimated module size associated with this candidate.
     */
    public function getEstimatedModuleSize()
    {
        return $this->estimatedModuleSize;
    }

    /**
     * Return how many times this center has been re-observed.
     */
    public function getCount()
    {
        return $this->count;
    }

    /*
    void incrementCount() {
      this.count++;
    }
     */

    /**
     * Compare this candidate with a nearby observation.
     *
     * The detector uses this to collapse repeated hits that are close enough in
     * both center position and module size to plausibly refer to the same finder
     * pattern.
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
     * Merge a new observation into the weighted center estimate.
     *
     * More sightings increase confidence and shift the stored center toward the
     * averaged position instead of replacing it outright.
     * @param mixed $i
     * @param mixed $j
     * @param mixed $newModuleSize
     */
    public function combineEstimate($i, $j, $newModuleSize): self
    {
        $combinedCount = $this->count + 1;
        $combinedX = ($this->count * $this->getX() + $j) / $combinedCount;
        $combinedY = ($this->count * $this->getY() + $i) / $combinedCount;
        $combinedModuleSize = ($this->count * $this->estimatedModuleSize + $newModuleSize) / $combinedCount;

        return new self($combinedX, $combinedY, $combinedModuleSize, $combinedCount);
    }
}
