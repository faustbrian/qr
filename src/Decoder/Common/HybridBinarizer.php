<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Decoder\Common;

use function fill_array;

/**
 * Locally thresholds luminance data into a binary matrix.
 *
 * The hybrid strategy is the decoder's preferred path for QR images because it
 * handles shadows and gradients better than a single global threshold. The
 * implementation keeps the global-histogram fallback for small inputs and lazily
 * computes the block matrix on first use so callers only pay for the expensive
 * path when they actually need it.
 *
 * @author dswitkin@google.com (Daniel Switkin)
 */
final class HybridBinarizer extends AbstractGlobalHistogramBinarizer
{
    // The block thresholds use 5x5 neighborhoods over 8x8 luminance blocks.
    private static int $BLOCK_SIZE_POWER = 3;

    private static int $BLOCK_SIZE = 8;

    private static int $BLOCK_SIZE_MASK = 7;

    private static int $MINIMUM_DIMENSION = 40;

    private static int $MIN_DYNAMIC_RANGE = 24;

    /**
     * Cached binarized matrix computed from the source image.
     *
     * The matrix is memoized because the hybrid thresholding pass is the expensive
     * part of decoding and the result is stable for the lifetime of the source.
     */
    private ?BitMatrix $matrix = null;

    /**
     * Resets the block-size constants and preserves the parent luminance source.
     *
     * The values are derived from the decoder implementation and are intentionally
     * fixed so the thresholding behavior remains predictable across runs.
     * @param mixed $source
     */
    public function __construct($source)
    {
        parent::__construct($source);
        self::$BLOCK_SIZE_POWER = 3;
        self::$BLOCK_SIZE = 1 << self::$BLOCK_SIZE_POWER; // ...0100...00
        self::$BLOCK_SIZE_MASK = self::$BLOCK_SIZE - 1;   // ...0011...11
        self::$MINIMUM_DIMENSION = self::$BLOCK_SIZE * 5;
        self::$MIN_DYNAMIC_RANGE = 24;
    }

    /**
     * Returns the thresholded matrix, computing it once on demand.
     *
     * Small images fall back to the parent histogram-based implementation because
     * the local block heuristic does not improve them and only adds overhead.
     */
    public function getBlackMatrix()
    {
        if ($this->matrix !== null) {
            return $this->matrix;
        }
        $source = $this->getLuminanceSource();
        $width = $source->getWidth();
        $height = $source->getHeight();

        if ($width >= self::$MINIMUM_DIMENSION && $height >= self::$MINIMUM_DIMENSION) {
            $luminances = $source->getMatrix();
            $subWidth = $width >> self::$BLOCK_SIZE_POWER;

            if (($width & self::$BLOCK_SIZE_MASK) !== 0) {
                ++$subWidth;
            }
            $subHeight = $height >> self::$BLOCK_SIZE_POWER;

            if (($height & self::$BLOCK_SIZE_MASK) !== 0) {
                ++$subHeight;
            }
            $blackPoints = self::calculateBlackPoints($luminances, $subWidth, $subHeight, $width, $height);

            $newMatrix = new BitMatrix($width, $height);
            self::calculateThresholdForBlock($luminances, $subWidth, $subHeight, $width, $height, $blackPoints, $newMatrix);
            $this->matrix = $newMatrix;
        } else {
            // If the image is too small, fall back to the global histogram approach.
            $this->matrix = parent::getBlackMatrix();
        }

        return $this->matrix;
    }

    public function createBinarizer($source): self
    {
        return new self($source);
    }

    /**
     * Calculates a representative black point for every 8x8 block.
     *
     * Each block is reduced to a single threshold derived from its luminance
     * distribution. Nearby blocks are consulted when the local contrast is too low
     * to avoid manufacturing barcodes out of noise.
     *
     * @psalm-return array<int, array<int, int|mixed>|mixed>
     */
    private static function calculateBlackPoints(
        array $luminances,
        int $subWidth,
        int $subHeight,
        int $width,
        int $height,
    ): array {
        $blackPoints = fill_array(0, $subHeight, 0);

        foreach ($blackPoints as $key => $point) {
            $blackPoints[$key] = fill_array(0, $subWidth, 0);
        }

        for ($y = 0; $y < $subHeight; ++$y) {
            $yoffset = $y << self::$BLOCK_SIZE_POWER;
            $maxYOffset = $height - self::$BLOCK_SIZE;

            if ($yoffset > $maxYOffset) {
                $yoffset = $maxYOffset;
            }

            for ($x = 0; $x < $subWidth; ++$x) {
                $xoffset = $x << self::$BLOCK_SIZE_POWER;
                $maxXOffset = $width - self::$BLOCK_SIZE;

                if ($xoffset > $maxXOffset) {
                    $xoffset = $maxXOffset;
                }
                $sum = 0;
                $min = 0xFF;
                $max = 0;

                for ($yy = 0, $offset = $yoffset * $width + $xoffset; $yy < self::$BLOCK_SIZE; ++$yy, $offset += $width) {
                    for ($xx = 0; $xx < self::$BLOCK_SIZE; ++$xx) {
                        $pixel = $luminances[$offset + $xx] & 0xFF;
                        $sum += $pixel;

                        // still looking for good contrast
                        if ($pixel < $min) {
                            $min = $pixel;
                        }

                        if ($pixel <= $max) {
                            continue;
                        }

                        $max = $pixel;
                    }

                    // short-circuit min/max tests once dynamic range is met
                    if ($max - $min <= self::$MIN_DYNAMIC_RANGE) {
                        continue;
                    }

                    // finish the rest of the rows quickly
                    for (++$yy, $offset += $width; $yy < self::$BLOCK_SIZE; ++$yy, $offset += $width) {
                        for ($xx = 0; $xx < self::$BLOCK_SIZE; ++$xx) {
                            $sum += $luminances[$offset + $xx] & 0xFF;
                        }
                    }
                }

                // The default estimate is the average of the values in the block.
                $average = $sum >> (self::$BLOCK_SIZE_POWER * 2);

                if ($max - $min <= self::$MIN_DYNAMIC_RANGE) {
                    // If variation within the block is low, assume this is a block with only light or only
                    // dark pixels. In that case we do not want to use the average, as it would divide this
                    // low contrast area into black and white pixels, essentially creating data out of noise.
                    //
                    // The default assumption is that the block is light/background. Since no estimate for
                    // the level of dark pixels exists locally, use half the min for the block.
                    $average = (int) ($min / 2);

                    if ($y > 0 && $x > 0) {
                        // Correct the "white background" assumption for blocks that have neighbors by comparing
                        // the pixels in this block to the previously calculated black points. This is based on
                        // the fact that dark barcode symbology is always surrounded by some amount of light
                        // background for which reasonable black point estimates were made. The bp estimated at
                        // the boundaries is used for the interior.

                        // The (min < bp) is arbitrary but works better than other heuristics that were tried.
                        $averageNeighborBlackPoint =
                            (int) (($blackPoints[$y - 1][$x] + (2 * $blackPoints[$y][$x - 1]) + $blackPoints[$y - 1][$x - 1]) / 4);

                        if ($min < $averageNeighborBlackPoint) {
                            $average = $averageNeighborBlackPoint;
                        }
                    }
                }
                $blackPoints[$y][$x] = (int) $average;
            }
        }

        return $blackPoints;
    }

    /**
     * Applies the block thresholds back onto the output matrix.
     *
     * The threshold for each block is the average of a 5x5 neighborhood of black
     * points, with edge blocks clamped so cropped and partial blocks still produce
     * stable results.
     *
     * @psalm-param array<int, array<int, int|mixed>|mixed> $blackPoints
     */
    private static function calculateThresholdForBlock(
        array $luminances,
        int $subWidth,
        int $subHeight,
        int $width,
        int $height,
        array $blackPoints,
        BitMatrix $matrix,
    ): void {
        for ($y = 0; $y < $subHeight; ++$y) {
            $yoffset = $y << self::$BLOCK_SIZE_POWER;
            $maxYOffset = $height - self::$BLOCK_SIZE;

            if ($yoffset > $maxYOffset) {
                $yoffset = $maxYOffset;
            }

            for ($x = 0; $x < $subWidth; ++$x) {
                $xoffset = $x << self::$BLOCK_SIZE_POWER;
                $maxXOffset = $width - self::$BLOCK_SIZE;

                if ($xoffset > $maxXOffset) {
                    $xoffset = $maxXOffset;
                }
                $left = self::cap($x, 2, $subWidth - 3);
                $top = self::cap($y, 2, $subHeight - 3);
                $sum = 0;

                for ($z = -2; $z <= 2; ++$z) {
                    $blackRow = $blackPoints[$top + $z];
                    $sum += $blackRow[$left - 2] + $blackRow[$left - 1] + $blackRow[$left] + $blackRow[$left + 1] + $blackRow[$left + 2];
                }
                $average = (int) ($sum / 25);

                self::thresholdBlock($luminances, $xoffset, $yoffset, $average, $width, $matrix);
            }
        }
    }

    /**
     * Clamps a block coordinate to the valid interior sampling range.
     *
     * @psalm-param 0|positive-int $value
     * @psalm-param 2 $min
     * @psalm-return float|int<2, max>
     */
    private static function cap(int $value, int $min, int $max): int
    {
        if ($value < $min) {
            return $min;
        }

        if ($value > $max) {
            return $max;
        }

        return $value;
    }

    /**
     * Writes the thresholded pixels for one block into the output matrix.
     *
     * @param array<int, int> $luminances source luminance buffer
     * @param int             $xoffset    left edge of the block in pixels
     * @param int             $yoffset    top edge of the block in pixels
     */
    private static function thresholdBlock(
        array $luminances,
        int $xoffset,
        int $yoffset,
        int $threshold,
        int $stride,
        BitMatrix $matrix,
    ): void {
        for ($y = 0, $offset = $yoffset * $stride + $xoffset; $y < self::$BLOCK_SIZE; ++$y, $offset += $stride) {
            for ($x = 0; $x < self::$BLOCK_SIZE; ++$x) {
                // Comparison needs to be <= so that black == 0 pixels are black even if the threshold is 0.
                if (($luminances[$offset + $x] & 0xFF) > $threshold) {
                    continue;
                }

                $matrix->set($xoffset + $x, $yoffset + $y);
            }
        }
    }
}
