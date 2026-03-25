<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Decoder\Common\Detector;

use Cline\Qr\Decoder\BinaryBitmap;
use Cline\Qr\Decoder\NotFoundException;
use Cline\Qr\Decoder\ResultPoint;

use function max;

/**
 * Detect a rectangular barcode region by scanning outward from image center.
 *
 * The detector is used when the QR code is expected to occupy a roughly
 * rectangular area with enough contrast for the geometry search to lock onto
 * the outer bounds. It trades some strictness for resilience: when the shape is
 * only approximately rectangular, the detector prefers a best-effort corner
 * estimate over early failure.
 *
 * @author Sean Owen
 */
final class MonochromeRectangleDetector
{
    private static int $MAX_MODULES = 32;

    public function __construct(
        private readonly BinaryBitmap $image,
    ) {}

    /**
     * Locate the corners of the barcode-like rectangle in the current image.
     *
     * @throws NotFoundException  if no sufficiently rectangular region is found
     * @return array<ResultPoint> ordered corner points describing the detected region
     */
    public function detect(): ResultPoint
    {
        $height = $this->image->getHeight();
        $width = $this->image->getWidth();
        $halfHeight = $height / 2;
        $halfWidth = $width / 2;

        $deltaY = max(1, $height / (self::$MAX_MODULES * 8));
        $deltaX = max(1, $width / (self::$MAX_MODULES * 8));

        $top = 0;
        $bottom = $height;
        $left = 0;
        $right = $width;
        $pointA = $this->findCornerFromCenter(
            $halfWidth,
            0,
            $left,
            $right,
            $halfHeight,
            -$deltaY,
            $top,
            $bottom,
            $halfWidth / 2,
        );
        $top = (int) $pointA->getY() - 1;
        $pointB = $this->findCornerFromCenter(
            $halfWidth,
            -$deltaX,
            $left,
            $right,
            $halfHeight,
            0,
            $top,
            $bottom,
            $halfHeight / 2,
        );
        $left = (int) $pointB->getX() - 1;
        $pointC = $this->findCornerFromCenter(
            $halfWidth,
            $deltaX,
            $left,
            $right,
            $halfHeight,
            0,
            $top,
            $bottom,
            $halfHeight / 2,
        );
        $right = (int) $pointC->getX() + 1;
        $pointD = $this->findCornerFromCenter(
            $halfWidth,
            0,
            $left,
            $right,
            $halfHeight,
            $deltaY,
            $top,
            $bottom,
            $halfWidth / 2,
        );
        $bottom = (int) $pointD->getY() + 1;

        // Re-run the top-corner search with the improved bounds from the other
        // corners. This compensates for the first pass being slightly off-center.
        $pointA = $this->findCornerFromCenter(
            $halfWidth,
            0,
            $left,
            $right,
            $halfHeight,
            -$deltaY,
            $top,
            $bottom,
            $halfWidth / 4,
        );

        return new ResultPoint($pointA, $pointB, $pointC, $pointD);
    }

    /**
     * Scan outward from the center until a corner is bounded by white runs.
     *
     * The method walks one axis at a time and uses the last confirmed black/white
     * range to infer the corner when the scan no longer finds a valid transition.
     *
     * @param float|int $centerX     horizontal starting coordinate
     * @param float|int $deltaX      horizontal step per scan iteration
     * @param int       $left        left boundary for the search
     * @param int       $right       right boundary for the search
     * @param float|int $centerY     vertical starting coordinate
     * @param float|int $deltaY      vertical step per scan iteration
     * @param int       $top         top boundary for the search
     * @param int       $bottom      bottom boundary for the search
     * @param float|int $maxWhiteRun longest white segment still considered inside
     *
     * @throws NotFoundException if no corner can be inferred from the scan
     * @return ResultPoint       detected corner
     */
    private function findCornerFromCenter(
        int|float $centerX,
        int|float $deltaX,
        int $left,
        int $right,
        int|float $centerY,
        float|int $deltaY,
        int $top,
        int $bottom,
        int|float $maxWhiteRun,
    ): ResultPoint {
        $lastRange = null;

        for ($y = $centerY, $x = $centerX;
            $y < $bottom && $y >= $top && $x < $right && $x >= $left;
            $y += $deltaY, $x += $deltaX) {
            $range = 0;

            if ($deltaX === 0) {
                // horizontal slices, up and down
                $range = $this->blackWhiteRange($y, $maxWhiteRun, $left, $right, true);
            } else {
                // vertical slices, left and right
                $range = $this->blackWhiteRange($x, $maxWhiteRun, $top, $bottom, false);
            }

            if ($range === null) {
                if ($lastRange === null) {
                    throw NotFoundException::getNotFoundInstance('No corner from center found');
                }

                // lastRange was found
                if ($deltaX === 0) {
                    $lastY = $y - $deltaY;

                    if ($lastRange[0] < $centerX) {
                        if ($lastRange[1] > $centerX) {
                            // straddle, choose one or the other based on direction
                            return new ResultPoint($deltaY > 0 ? $lastRange[0] : $lastRange[1], $lastY);
                        }

                        return new ResultPoint($lastRange[0], $lastY);
                    }

                    return new ResultPoint($lastRange[1], $lastY);
                }

                $lastX = $x - $deltaX;

                if ($lastRange[0] < $centerY) {
                    if ($lastRange[1] > $centerY) {
                        return new ResultPoint($lastX, $deltaX < 0 ? $lastRange[0] : $lastRange[1]);
                    }

                    return new ResultPoint($lastX, $lastRange[0]);
                }

                return new ResultPoint($lastX, $lastRange[1]);
            }
            $lastRange = $range;
        }

        throw NotFoundException::getNotFoundInstance('No corner from center found');
    }

    /**
     * Find the black/white span around the center line of the search.
     *
     * The method returns the contiguous range that still looks like barcode
     * content once short white gaps are tolerated.
     *
     * @param float|int $fixedDimension the fixed axis coordinate being scanned
     * @param float|int $maxWhiteRun    maximum white run allowed before the span is
     *                                  considered broken
     * @param int       $minDim         lower scan bound
     * @param int       $maxDim         upper scan bound
     * @param bool      $horizontal     `true` when scanning horizontally
     *
     * @return null|array{0: float|int, 1: float|int} detected span or `null`
     */
    private function blackWhiteRange(float|int $fixedDimension, int|float $maxWhiteRun, int $minDim, int $maxDim, bool $horizontal): ?array
    {
        $center = ($minDim + $maxDim) / 2;

        // Scan left/up first
        $start = $center;

        while ($start >= $minDim) {
            if ($horizontal ? $this->image->get($start, $fixedDimension) : $this->image->get($fixedDimension, $start)) {
                --$start;
            } else {
                $whiteRunStart = $start;

                do {
                    --$start;
                } while ($start >= $minDim && !($horizontal ? $this->image->get($start, $fixedDimension) :
                    $this->image->get($fixedDimension, $start)));
                $whiteRunSize = $whiteRunStart - $start;

                if ($start < $minDim || $whiteRunSize > $maxWhiteRun) {
                    $start = $whiteRunStart;

                    break;
                }
            }
        }
        ++$start;

        // Then try right/down
        $end = $center;

        while ($end < $maxDim) {
            if ($horizontal ? $this->image->get($end, $fixedDimension) : $this->image->get($fixedDimension, $end)) {
                ++$end;
            } else {
                $whiteRunStart = $end;

                do {
                    ++$end;
                } while ($end < $maxDim && !($horizontal ? $this->image->get($end, $fixedDimension) :
                    $this->image->get($fixedDimension, $end)));
                $whiteRunSize = $end - $whiteRunStart;

                if ($end >= $maxDim || $whiteRunSize > $maxWhiteRun) {
                    $end = $whiteRunStart;

                    break;
                }
            }
        }
        --$end;

        return $end > $start ? [$start, $end] : null;
    }
}
