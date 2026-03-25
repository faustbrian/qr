<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Decoder\Common;

use Cline\Qr\Decoder\AbstractBinarizer;
use Cline\Qr\Decoder\NotFoundException;

use function count;
use function fill_array;
use function is_countable;

/**
 * Shared global-histogram binarizer behavior for decoder implementations.
 *
 * Concrete strategies can reuse the global row-thresholding path and the
 * histogram-based full-matrix fallback without inheriting from one another.
 * This keeps the shared algorithm in one place while allowing each concrete
 * binarizer to remain `final`.
 *
 * @author dswitkin@google.com (Daniel Switkin)
 * @author Sean Owen
 */
abstract class AbstractGlobalHistogramBinarizer extends AbstractBinarizer
{
    private static int $LUMINANCE_BITS = 5;

    private static int $LUMINANCE_SHIFT = 3;

    private static int $LUMINANCE_BUCKETS = 32;

    private static array $EMPTY = [];

    private array $luminances = [];

    private array $buckets = [];

    /**
     * Cached source used while building rows and the full matrix.
     *
     * @var \Cline\Qr\Decoder\AbstractLuminanceSource|mixed
     */
    private $source = [];

    /**
     * Initialize the histogram state for a luminance source.
     *
     * The constructor also prepares the bucket array that is reused for both
     * row and full-matrix threshold estimation.
     *
     * @param mixed $source luminance source backing this binarizer
     */
    public function __construct($source)
    {
        self::$LUMINANCE_SHIFT = 8 - self::$LUMINANCE_BITS;
        self::$LUMINANCE_BUCKETS = 1 << self::$LUMINANCE_BITS;

        parent::__construct($source);

        $this->luminances = self::$EMPTY;
        $this->buckets = fill_array(0, self::$LUMINANCE_BUCKETS, 0);
        $this->source = $source;
    }

    /**
     * Threshold a single row using the global histogram estimate.
     *
     * The row path applies a small sharpening kernel before comparing against the
     * black point so one-dimensional readers get slightly cleaner edges.
     */
    public function getBlackRow(int $y, ?BitArray $row = null): BitArray
    {
        $this->source = $this->getLuminanceSource();
        $width = $this->source->getWidth();

        if ($row === null || $row->getSize() < $width) {
            $row = new BitArray($width);
        } else {
            $row->clear();
        }

        $this->initArrays($width);
        $localLuminances = $this->source->getRow($y, $this->luminances);
        $localBuckets = $this->buckets;

        for ($x = 0; $x < $width; ++$x) {
            $pixel = $localLuminances[$x] & 0xFF;
            ++$localBuckets[$pixel >> self::$LUMINANCE_SHIFT];
        }
        $blackPoint = self::estimateBlackPoint($localBuckets);

        $left = $localLuminances[0] & 0xFF;
        $center = $localLuminances[1] & 0xFF;

        for ($x = 1; $x < $width - 1; ++$x) {
            $right = $localLuminances[$x + 1] & 0xFF;
            $luminance = (($center * 4) - $left - $right) / 2;

            if ($luminance < $blackPoint) {
                $row->set($x);
            }

            $left = $center;
            $center = $right;
        }

        return $row;
    }

    /**
     * Build a black/white matrix using the estimated global threshold.
     */
    public function getBlackMatrix()
    {
        $source = $this->getLuminanceSource();
        $width = $source->getWidth();
        $height = $source->getHeight();
        $matrix = new BitMatrix($width, $height);

        $this->initArrays($width);
        $localBuckets = $this->buckets;

        for ($y = 1; $y < 5; ++$y) {
            $row = (int) ($height * $y / 5);
            $localLuminances = $source->getRow($row, $this->luminances);
            $right = (int) (($width * 4) / 5);

            for ($x = (int) ($width / 5); $x < $right; ++$x) {
                $pixel = $localLuminances[$x] & 0xFF;
                ++$localBuckets[$pixel >> self::$LUMINANCE_SHIFT];
            }
        }

        $blackPoint = self::estimateBlackPoint($localBuckets);
        $localLuminances = $source->getMatrix();

        for ($y = 0; $y < $height; ++$y) {
            $offset = $y * $width;

            for ($x = 0; $x < $width; ++$x) {
                $pixel = (int) ($localLuminances[$offset + $x] & 0xFF);

                if ($pixel >= $blackPoint) {
                    continue;
                }

                $matrix->set($x, $y);
            }
        }

        return $matrix;
    }

    /**
     * Estimate a global black point from the luminance histogram.
     *
     * @param array<int, int> $buckets histogram buckets produced from the source
     *
     * @throws NotFoundException when the histogram does not contain enough contrast
     * @return int               threshold value in luminance space
     */
    final protected static function estimateBlackPoint(array $buckets): int
    {
        $numBuckets = is_countable($buckets) ? count($buckets) : 0;
        $maxBucketCount = 0;
        $firstPeak = 0;
        $firstPeakSize = 0;

        for ($x = 0; $x < $numBuckets; ++$x) {
            if ($buckets[$x] > $firstPeakSize) {
                $firstPeak = $x;
                $firstPeakSize = $buckets[$x];
            }

            if ($buckets[$x] <= $maxBucketCount) {
                continue;
            }

            $maxBucketCount = $buckets[$x];
        }

        $secondPeak = 0;
        $secondPeakScore = 0;

        for ($x = 0; $x < $numBuckets; ++$x) {
            $distanceToBiggest = $x - $firstPeak;
            $score = $buckets[$x] * $distanceToBiggest * $distanceToBiggest;

            if ($score <= $secondPeakScore) {
                continue;
            }

            $secondPeak = $x;
            $secondPeakScore = $score;
        }

        if ($firstPeak > $secondPeak) {
            $temp = $firstPeak;
            $firstPeak = $secondPeak;
            $secondPeak = $temp;
        }

        if ($secondPeak - $firstPeak <= $numBuckets / 16) {
            throw NotFoundException::getNotFoundInstance('too little contrast in the image to pick a meaningful black point');
        }

        $bestValley = $secondPeak - 1;
        $bestValleyScore = -1;

        for ($x = $secondPeak - 1; $x > $firstPeak; --$x) {
            $fromFirst = $x - $firstPeak;
            $score = $fromFirst * $fromFirst * ($secondPeak - $x) * ($maxBucketCount - $buckets[$x]);

            if ($score <= $bestValleyScore) {
                continue;
            }

            $bestValley = $x;
            $bestValleyScore = $score;
        }

        return $bestValley << self::$LUMINANCE_SHIFT;
    }

    /**
     * Reset the reusable luminance buffers before another histogram pass.
     *
     * The full-matrix path does not sharpen the data; it only needs enough state
     * to estimate a stable global threshold.
     */
    final protected function initArrays(int $luminanceSize): void
    {
        if (count($this->luminances) < $luminanceSize) {
            $this->luminances = [];
        }

        for ($x = 0; $x < self::$LUMINANCE_BUCKETS; ++$x) {
            $this->buckets[$x] = 0;
        }
    }
}
