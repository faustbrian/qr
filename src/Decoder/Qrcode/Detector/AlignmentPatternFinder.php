<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Decoder\Qrcode\Detector;

use Cline\Qr\Decoder\NotFoundException;
use Cline\Qr\Decoder\NullResultPointCallback;
use Cline\Qr\Decoder\ResultPointCallback;

use const NAN;

use function abs;
use function count;
use function is_nan;

/**
 * Searches a restricted region of the image for the alignment pattern.
 *
 * This finder is a performance-oriented specialization of FinderPatternFinder.
 * It only searches the estimated bottom-right alignment area because the three
 * finder patterns have already established the symbol's coarse geometry by the
 * time this class is used.
 *
 * @author Sean Owen
 */
final class AlignmentPatternFinder
{
    private array $possibleCenters = [];

    private array $crossCheckStateCount = [];

    /**
     * Create a finder scoped to the predicted alignment-pattern region.
     *
     * @param mixed $image               Image backend used for pixel reads.
     * @param int   $startX              Left edge of the search window.
     * @param int   $startY              Top edge of the search window.
     * @param float $width               Search window width.
     * @param float $height              Search window height.
     * @param float $moduleSize          Estimated module size from the finder patterns.
     * @param mixed $resultPointCallback Optional callback for intermediate points.
     */
    public function __construct(
        private $image,
        private $startX,
        private $startY,
        private $width,
        private $height,
        private $moduleSize,
        private readonly ResultPointCallback $resultPointCallback = new NullResultPointCallback(),
    ) {}

    /**
     * Locate the alignment pattern, preferring a confirmed match over a guess.
     *
     * The scan expands outward from the middle of the search window, reusing the
     * same 1:1:1 run-length test as the original decoder implementation. If no
     * candidate is confirmed twice, the best observed center is returned as a
     * fallback; otherwise a NotFoundException is thrown.
     *
     * @throws NotFoundException if not found
     * @return AlignmentPattern  Confirmed or best-effort alignment pattern.
     */
    public function find()
    {
        $startX = $this->startX;
        $height = $this->height;
        $maxJ = $startX + $this->width;
        $middleI = $this->startY + ($height / 2);
        // We are looking for black/white/black modules in 1:1:1 ratio;
        // this tracks the number of black/white/black modules seen so far
        $stateCount = [];

        for ($iGen = 0; $iGen < $height; ++$iGen) {
            // Search from middle outwards
            $i = $middleI + (($iGen & 0x01) === 0 ? ($iGen + 1) / 2 : -(($iGen + 1) / 2));
            $i = (int) $i;
            $stateCount[0] = 0;
            $stateCount[1] = 0;
            $stateCount[2] = 0;
            $j = $startX;

            // Burn off leading white pixels before anything else; if we start in the middle of
            // a white run, it doesn't make sense to count its length, since we don't know if the
            // white run continued to the left of the start point
            while ($j < $maxJ && !$this->image->get($j, $i)) {
                ++$j;
            }
            $currentState = 0;

            while ($j < $maxJ) {
                if ($this->image->get($j, $i)) {
                    // Black pixel
                    if ($currentState === 1) { // Counting black pixels
                        ++$stateCount[$currentState];
                    } else { // Counting white pixels
                        if ($currentState === 2) { // A winner?
                            if ($this->foundPatternCross($stateCount)) { // Yes
                                $confirmed = $this->handlePossibleCenter($stateCount, $i, $j);

                                if ($confirmed !== null) {
                                    return $confirmed;
                                }
                            }
                            $stateCount[0] = $stateCount[2];
                            $stateCount[1] = 1;
                            $stateCount[2] = 0;
                            $currentState = 1;
                        } else {
                            ++$stateCount[++$currentState];
                        }
                    }
                } else { // White pixel
                    if ($currentState === 1) { // Counting black pixels
                        ++$currentState;
                    }
                    ++$stateCount[$currentState];
                }
                ++$j;
            }

            if (!$this->foundPatternCross($stateCount)) {
                continue;
            }

            $confirmed = $this->handlePossibleCenter($stateCount, $i, $maxJ);

            if ($confirmed !== null) {
                return $confirmed;
            }
        }

        // Hmm, nothing we saw was observed and confirmed twice. If we had
        // any guess at all, return it.
        if (count($this->possibleCenters)) {
            return $this->possibleCenters[0];
        }

        throw new NotFoundException('Bottom right alignment pattern not found');
    }

    /**
     * Compute the center point of the observed black/white/black run.
     */
    private static function centerFromEnd(array $stateCount, int $end)
    {
        return (float) ($end - $stateCount[2]) - $stateCount[1] / 2.0;
    }

    /**
     * Test whether the current run-length counts match the 1:1:1 alignment shape.
     *
     * @param array<int, int> $stateCount Black/white/black counts read so far.
     *
     * @return bool True when the observed run is close enough to the expected
     *              alignment-pattern proportions.
     */
    private function foundPatternCross($stateCount): bool
    {
        $moduleSize = $this->moduleSize;
        $maxVariance = $moduleSize / 2.0;

        for ($i = 0; $i < 3; ++$i) {
            if (abs($moduleSize - $stateCount[$i]) >= $maxVariance) {
                return false;
            }
        }

        return true;
    }

    /**
     * Promote a horizontal hit into a confirmed alignment-pattern candidate.
     *
     * The method cross-checks vertically, compares the result against previous
     * sightings, and only stores a new candidate when the observation is still
     * consistent after both checks.
     *
     * @param array<int, int> $stateCount State-module counts from the scan line.
     * @param int             $i          Row where the candidate was found.
     * @param int             $j          Column where the candidate ended.
     *
     * @return null|AlignmentPattern Confirmed pattern when one exists.
     */
    private function handlePossibleCenter($stateCount, $i, $j)
    {
        $stateCountTotal = $stateCount[0] + $stateCount[1] + $stateCount[2];
        $centerJ = self::centerFromEnd($stateCount, $j);
        $centerI = $this->crossCheckVertical($i, (int) $centerJ, 2 * $stateCount[1], $stateCountTotal);

        if (!is_nan($centerI)) {
            $estimatedModuleSize = (float) ($stateCount[0] + $stateCount[1] + $stateCount[2]) / 3.0;

            foreach ($this->possibleCenters as $center) {
                // Look for about the same center and module size:
                if ($center->aboutEquals($estimatedModuleSize, $centerI, $centerJ)) {
                    return $center->combineEstimate($centerI, $centerJ, $estimatedModuleSize);
                }
            }
            // Hadn't found this before; save it
            $point = new AlignmentPattern($centerJ, $centerI, $estimatedModuleSize);
            $this->possibleCenters[] = $point;
            $this->resultPointCallback->foundPossibleResultPoint($point);
        }

        return null;
    }

    /**
     * Cross-check the candidate vertically to confirm the 1:1:1 shape.
     *
     * @param int       $startI                  Row where the candidate was detected.
     * @param int       $centerJ                 Estimated center column.
     * @param int       $maxCount                Maximum count tolerated for any run.
     * @param float|int $originalStateCountTotal Total run-length count from the
     *                                           horizontal scan.
     *
     * @return float Vertical center of the pattern, or NAN when the candidate
     *               does not survive the cross-check.
     */
    private function crossCheckVertical(
        int $startI,
        int $centerJ,
        $maxCount,
        $originalStateCountTotal,
    ) {
        $image = $this->image;

        $maxI = $image->getHeight();
        $stateCount = $this->crossCheckStateCount;
        $stateCount[0] = 0;
        $stateCount[1] = 0;
        $stateCount[2] = 0;

        // Start counting up from center
        $i = $startI;

        while ($i >= 0 && $image->get($centerJ, $i) && $stateCount[1] <= $maxCount) {
            ++$stateCount[1];
            --$i;
        }

        // If already too many modules in this state or ran off the edge:
        if ($i < 0 || $stateCount[1] > $maxCount) {
            return NAN;
        }

        while ($i >= 0 && !$image->get($centerJ, $i) && $stateCount[0] <= $maxCount) {
            ++$stateCount[0];
            --$i;
        }

        if ($stateCount[0] > $maxCount) {
            return NAN;
        }

        // Now also count down from center
        $i = $startI + 1;

        while ($i < $maxI && $image->get($centerJ, $i) && $stateCount[1] <= $maxCount) {
            ++$stateCount[1];
            ++$i;
        }

        if ($i === $maxI || $stateCount[1] > $maxCount) {
            return NAN;
        }

        while ($i < $maxI && !$image->get($centerJ, $i) && $stateCount[2] <= $maxCount) {
            ++$stateCount[2];
            ++$i;
        }

        if ($stateCount[2] > $maxCount) {
            return NAN;
        }

        $stateCountTotal = $stateCount[0] + $stateCount[1] + $stateCount[2];

        if (5 * abs($stateCountTotal - $originalStateCountTotal) >= 2 * $originalStateCountTotal) {
            return NAN;
        }

        return $this->foundPatternCross($stateCount) ? self::centerFromEnd($stateCount, $i) : NAN;
    }
}
