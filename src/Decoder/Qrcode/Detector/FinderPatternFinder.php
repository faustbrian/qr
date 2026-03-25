<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Decoder\Qrcode\Detector;

use Cline\Qr\Decoder\Common\BitMatrix;
use Cline\Qr\Decoder\NotFoundException;
use Cline\Qr\Decoder\ResultPoint;

use const NAN;

use function abs;
use function array_key_exists;
use function array_slice;
use function array_values;
use function count;
use function fill_array;
use function is_nan;
use function max;
use function sqrt;
use function usort;

/**
 * Scan the binary image for the three finder patterns that anchor the symbol.
 *
 * Finder patterns are the large corner markers that establish orientation,
 * scale, and perspective. The finder search is intentionally stateful because
 * repeated hits are merged into candidate centers until enough evidence exists
 * to pick the best trio.
 *
 * @author Sean Owen
 */
final class FinderPatternFinder
{
    protected static int $MIN_SKIP = 3;

    protected static int $MAX_MODULES = 57; // 1 pixel/module times 3 modules/center

    private static int $CENTER_QUORUM = 2;

    private ?float $average = null;

    private array $possibleCenters = []; // private final List<FinderPattern> possibleCenters;

    private bool $hasSkipped = false;

    /** @var array<int>|mixed */
    private $crossCheckStateCount;

    /**
     * Create a finder configured to search the supplied binary image.
     *
     * @param BitMatrix $image               Image to search.
     * @param mixed     $resultPointCallback Optional callback for intermediate hits.
     */
    public function __construct(
        private $image,
        private $resultPointCallback = null,
    ) {
        // new ArrayList<>();
        $this->crossCheckStateCount = fill_array(0, 5, 0);
    }

    /**
     * Locate and rank the three best finder patterns in the image.
     *
     * Search hints alter the scan strategy:
     * - TRY_HARDER reduces row skipping.
     * - PURE_BARCODE disables the diagonal cross-check.
     * - Optional numeric hints adjust the allowed variance thresholds.
     *
     * @param null|array $hints Decode hints supplied by the caller.
     *
     * @return FinderPatternInfo Ordered finder-pattern metadata.
     */
    public function find(?array $hints): FinderPatternInfo
    {/* final FinderPatternInfo find(Map<DecodeHintType,?> hints) throws NotFoundException { */
        $tryHarder = $hints !== null && array_key_exists('TRY_HARDER', $hints) && $hints['TRY_HARDER'];
        $pureBarcode = $hints !== null && array_key_exists('PURE_BARCODE', $hints) && $hints['PURE_BARCODE'];
        $nrOfRowsSkippable = $hints !== null && array_key_exists('NR_ALLOW_SKIP_ROWS', $hints) ? $hints['NR_ALLOW_SKIP_ROWS'] : ($tryHarder ? 0 : null);
        $allowedDeviation = $hints !== null && array_key_exists('ALLOWED_DEVIATION', $hints) ? $hints['ALLOWED_DEVIATION'] : 0.05;
        $maxVariance = $hints !== null && array_key_exists('MAX_VARIANCE', $hints) ? $hints['MAX_VARIANCE'] : 0.5;
        $maxI = $this->image->getHeight();
        $maxJ = $this->image->getWidth();
        // We are looking for black/white/black/white/black modules in
        // 1:1:3:1:1 ratio; this tracks the number of such modules seen so far

        // Let's assume that the maximum version QR Code we support takes up 1/4 the height of the
        // image, and then account for the center being 3 modules in size. This gives the smallest
        // number of pixels the center could be, so skip this often. When trying harder, look for all
        // QR versions regardless of how dense they are.
        $iSkip = (int) ((3 * $maxI) / (4 * self::$MAX_MODULES));

        if ($iSkip < self::$MIN_SKIP || $tryHarder) {
            $iSkip = self::$MIN_SKIP;
        }

        $done = false;
        $stateCount = [];

        for ($i = $iSkip - 1; $i < $maxI && !$done; $i += $iSkip) {
            // Get a row of black/white values
            $stateCount[0] = 0;
            $stateCount[1] = 0;
            $stateCount[2] = 0;
            $stateCount[3] = 0;
            $stateCount[4] = 0;
            $currentState = 0;

            for ($j = 0; $j < $maxJ; ++$j) {
                if ($this->image->get($j, $i)) {
                    // Black pixel
                    if (($currentState & 1) === 1) { // Counting white pixels
                        ++$currentState;
                    }
                    ++$stateCount[$currentState];
                } else { // White pixel
                    if (($currentState & 1) === 0) { // Counting black pixels
                        if ($currentState === 4) { // A winner?
                            if (self::foundPatternCross($stateCount, $maxVariance)) { // Yes
                                $confirmed = $this->handlePossibleCenter($stateCount, $i, $j, $pureBarcode);

                                if (!$confirmed) {
                                    $stateCount[0] = $stateCount[2];
                                    $stateCount[1] = $stateCount[3];
                                    $stateCount[2] = $stateCount[4];
                                    $stateCount[3] = 1;
                                    $stateCount[4] = 0;
                                    $currentState = 3;

                                    continue;
                                }

                                // Start examining every other line. Checking each line turned out to be too
                                // expensive and didn't improve performance.
                                $iSkip = 3;

                                if ($this->hasSkipped) {
                                    $done = $this->haveMultiplyConfirmedCenters($allowedDeviation);
                                } else {
                                    $rowSkip = $nrOfRowsSkippable === null ? $this->findRowSkip() : $nrOfRowsSkippable;

                                    if ($rowSkip > $stateCount[2]) {
                                        // Skip rows between row of lower confirmed center
                                        // and top of presumed third confirmed center
                                        // but back up a bit to get a full chance of detecting
                                        // it, entire width of center of finder pattern

                                        // Skip by rowSkip, but back off by $stateCount[2] (size of last center
                                        // of pattern we saw) to be conservative, and also back off by iSkip which
                                        // is about to be re-added
                                        $i += $rowSkip - $stateCount[2] - $iSkip;
                                        $j = $maxJ - 1;
                                    }
                                }
                                // Clear state to start looking again
                                $currentState = 0;
                                $stateCount[0] = 0;
                                $stateCount[1] = 0;
                                $stateCount[2] = 0;
                                $stateCount[3] = 0;
                                $stateCount[4] = 0;
                            } else { // No, shift counts back by two
                                $stateCount[0] = $stateCount[2];
                                $stateCount[1] = $stateCount[3];
                                $stateCount[2] = $stateCount[4];
                                $stateCount[3] = 1;
                                $stateCount[4] = 0;
                                $currentState = 3;
                            }
                        } else {
                            ++$stateCount[++$currentState];
                        }
                    } else { // Counting white pixels
                        ++$stateCount[$currentState];
                    }
                }
            }

            if (!self::foundPatternCross($stateCount, $maxVariance)) {
                continue;
            }

            $confirmed = $this->handlePossibleCenter($stateCount, $i, $maxJ, $pureBarcode);

            if (!$confirmed) {
                continue;
            }

            $iSkip = $stateCount[0];

            if (!$this->hasSkipped) {
                continue;
            }

            // Found a third one
            $done = $this->haveMultiplyConfirmedCenters($allowedDeviation);
        }

        $patternInfo = $this->selectBestPatterns();
        $patternInfo = ResultPoint::orderBestPatterns($patternInfo);

        return new FinderPatternInfo($patternInfo);
    }

    /**
     * Sort candidates by how far they are from the average module size.
     *
     * @param mixed $center1
     * @param mixed $center2
     * @psalm-return -1|0|1
     */
    public function FurthestFromAverageComparator($center1, $center2): int
    {
        $dA = abs($center2->getEstimatedModuleSize() - $this->average);
        $dB = abs($center1->getEstimatedModuleSize() - $this->average);

        if ($dA < $dB) {
            return -1;
        }

        if ($dA === $dB) {
            return 0;
        }

        return 1;
    }

    /**
     * Compare candidates by confidence, then by proximity to the average size.
     * @param mixed $center1
     * @param mixed $center2
     */
    public function CenterComparator($center1, $center2)
    {
        if ($center2->getCount() === $center1->getCount()) {
            $dA = abs($center2->getEstimatedModuleSize() - $this->average);
            $dB = abs($center1->getEstimatedModuleSize() - $this->average);

            if ($dA < $dB) {
                return 1;
            }

            if ($dA === $dB) {
                return 0;
            }

            return -1;
        }

        return $center2->getCount() - $center1->getCount();
    }

    /**
     * Test whether the observed 1:1:3:1:1 run matches a finder pattern.
     *
     * @param array<int, int> $stateCount  Counts for the five scan segments.
     * @param float           $maxVariance Allowed relative variance from the ideal ratio.
     *
     * @return bool True when the run-lengths are consistent with a finder pattern.
     */
    protected static function foundPatternCross(array $stateCount, float $maxVariance = 0.5): bool
    {
        $totalModuleSize = 0;

        for ($i = 0; $i < 5; ++$i) {
            $count = $stateCount[$i];

            if ($count === 0) {
                return false;
            }
            $totalModuleSize += $count;
        }

        if ($totalModuleSize < 7) {
            return false;
        }
        $moduleSize = $totalModuleSize / 7.0;
        $maxVariance = $moduleSize * $maxVariance;

        // Allow less than 50% variance from 1-1-3-1-1 proportions
        return
            abs($moduleSize - $stateCount[0]) < $maxVariance
            && abs($moduleSize - $stateCount[1]) < $maxVariance
            && abs(3.0 * $moduleSize - $stateCount[2]) < 3 * $maxVariance
            && abs($moduleSize - $stateCount[3]) < $maxVariance
            && abs($moduleSize - $stateCount[4]) < $maxVariance;
    }

    /**
     * Promote a horizontal hit into a confirmed finder-pattern candidate.
     *
     * The method cross-checks vertically, then horizontally, and optionally
     * diagonally in pure-barcode mode. Repeated observations are merged into a
     * weighted center instead of creating duplicate candidates.
     *
     * @param array<int, int> $stateCount  State counts from the horizontal scan.
     * @param int             $i           Row where the candidate was found.
     * @param int             $j           End column of the candidate run.
     * @param bool            $pureBarcode Whether the stricter diagonal cross-check applies.
     *
     * @return bool True when the candidate survived the cross-check pipeline.
     */
    protected function handlePossibleCenter($stateCount, int $i, int $j, bool $pureBarcode): bool
    {
        $stateCountTotal = $stateCount[0] + $stateCount[1] + $stateCount[2] + $stateCount[3] +
            $stateCount[4];
        $centerJ = self::centerFromEnd($stateCount, $j);
        $centerI = $this->crossCheckVertical($i, (int) $centerJ, $stateCount[2], $stateCountTotal);

        if (!is_nan($centerI)) {
            // Re-cross check
            $centerJ = $this->crossCheckHorizontal((int) $centerJ, (int) $centerI, $stateCount[2], $stateCountTotal);

            if (
                !is_nan($centerJ)
                && (!$pureBarcode || $this->crossCheckDiagonal((int) $centerI, (int) $centerJ, $stateCount[2], $stateCountTotal))
            ) {
                $estimatedModuleSize = (float) $stateCountTotal / 7.0;
                $found = false;

                for ($index = 0; $index < count($this->possibleCenters); ++$index) {
                    $center = $this->possibleCenters[$index];

                    // Look for about the same center and module size:
                    if ($center->aboutEquals($estimatedModuleSize, $centerI, $centerJ)) {
                        $this->possibleCenters[$index] = $center->combineEstimate($centerI, $centerJ, $estimatedModuleSize);
                        $found = true;

                        break;
                    }
                }

                if (!$found) {
                    $point = new FinderPattern($centerJ, $centerI, $estimatedModuleSize);
                    $this->possibleCenters[] = $point;

                    if ($this->resultPointCallback !== null) {
                        $this->resultPointCallback->foundPossibleResultPoint($point);
                    }
                }

                return true;
            }
        }

        return false;
    }

    /**
     * Return the current binary image being scanned.
     */
    protected function getImage(): BitMatrix
    {
        return $this->image;
    }

    /**
     * Return the current candidate list, ordered by detection confidence.
     */
    protected function getPossibleCenters(): array
    { // List<FinderPattern> getPossibleCenters()
        return $this->possibleCenters;
    }

    /**
     * Compute the center of a 1:1:3:1:1 run from its end position.
     */
    private static function centerFromEnd(array $stateCount, int $end)
    {
        return (float) ($end - $stateCount[4] - $stateCount[3]) - $stateCount[2] / 2.0;
    }

    /**
     * Verify the candidate by scanning vertically through its center.
     *
     * @param int       $startI                  Row where the horizontal run was detected.
     * @param int       $centerJ                 Center column estimate.
     * @param int       $maxCount                Maximum run length allowed for any state.
     * @param float|int $originalStateCountTotal Original horizontal run total.
     *
     * @return float Vertical center, or NAN when the candidate fails.
     */
    private function crossCheckVertical(
        int $startI,
        int $centerJ,
        int $maxCount,
        int|float $originalStateCountTotal,
    ) {
        $image = $this->image;

        $maxI = $image->getHeight();
        $stateCount = $this->getCrossCheckStateCount();

        // Start counting up from center
        $i = $startI;

        while ($i >= 0 && $image->get($centerJ, $i)) {
            ++$stateCount[2];
            --$i;
        }

        if ($i < 0) {
            return NAN;
        }

        while ($i >= 0 && !$image->get($centerJ, $i) && $stateCount[1] <= $maxCount) {
            ++$stateCount[1];
            --$i;
        }

        // If already too many modules in this state or ran off the edge:
        if ($i < 0 || $stateCount[1] > $maxCount) {
            return NAN;
        }

        while ($i >= 0 && $image->get($centerJ, $i) && $stateCount[0] <= $maxCount) {
            ++$stateCount[0];
            --$i;
        }

        if ($stateCount[0] > $maxCount) {
            return NAN;
        }

        // Now also count down from center
        $i = $startI + 1;

        while ($i < $maxI && $image->get($centerJ, $i)) {
            ++$stateCount[2];
            ++$i;
        }

        if ($i === $maxI) {
            return NAN;
        }

        while ($i < $maxI && !$image->get($centerJ, $i) && $stateCount[3] < $maxCount) {
            ++$stateCount[3];
            ++$i;
        }

        if ($i === $maxI || $stateCount[3] >= $maxCount) {
            return NAN;
        }

        while ($i < $maxI && $image->get($centerJ, $i) && $stateCount[4] < $maxCount) {
            ++$stateCount[4];
            ++$i;
        }

        if ($stateCount[4] >= $maxCount) {
            return NAN;
        }

        // If we found a finder-pattern-like section, but its size is more than 40% different than
        // the original, assume it's a false positive
        $stateCountTotal = $stateCount[0] + $stateCount[1] + $stateCount[2] + $stateCount[3] +
            $stateCount[4];

        if (5 * abs($stateCountTotal - $originalStateCountTotal) >= 2 * $originalStateCountTotal) {
            return NAN;
        }

        return self::foundPatternCross($stateCount) ? self::centerFromEnd($stateCount, $i) : NAN;
    }

    /**
     * Reset and reuse the scratch state array for cross-check scans.
     */
    private function getCrossCheckStateCount()
    {
        $this->crossCheckStateCount[0] = 0;
        $this->crossCheckStateCount[1] = 0;
        $this->crossCheckStateCount[2] = 0;
        $this->crossCheckStateCount[3] = 0;
        $this->crossCheckStateCount[4] = 0;

        return $this->crossCheckStateCount;
    }

    /**
     * Verify the candidate by scanning horizontally through its center.
     */
    private function crossCheckHorizontal(
        int $startJ,
        int $centerI,
        int $maxCount,
        int|float $originalStateCountTotal,
    ) {
        $image = $this->image;

        $maxJ = $this->image->getWidth();
        $stateCount = $this->getCrossCheckStateCount();

        $j = $startJ;

        while ($j >= 0 && $image->get($j, $centerI)) {
            ++$stateCount[2];
            --$j;
        }

        if ($j < 0) {
            return NAN;
        }

        while ($j >= 0 && !$image->get($j, $centerI) && $stateCount[1] <= $maxCount) {
            ++$stateCount[1];
            --$j;
        }

        if ($j < 0 || $stateCount[1] > $maxCount) {
            return NAN;
        }

        while ($j >= 0 && $image->get($j, $centerI) && $stateCount[0] <= $maxCount) {
            ++$stateCount[0];
            --$j;
        }

        if ($stateCount[0] > $maxCount) {
            return NAN;
        }

        $j = $startJ + 1;

        while ($j < $maxJ && $image->get($j, $centerI)) {
            ++$stateCount[2];
            ++$j;
        }

        if ($j === $maxJ) {
            return NAN;
        }

        while ($j < $maxJ && !$image->get($j, $centerI) && $stateCount[3] < $maxCount) {
            ++$stateCount[3];
            ++$j;
        }

        if ($j === $maxJ || $stateCount[3] >= $maxCount) {
            return NAN;
        }

        while ($j < $maxJ && $this->image->get($j, $centerI) && $stateCount[4] < $maxCount) {
            ++$stateCount[4];
            ++$j;
        }

        if ($stateCount[4] >= $maxCount) {
            return NAN;
        }

        // If we found a finder-pattern-like section, but its size is significantly different than
        // the original, assume it's a false positive
        $stateCountTotal = $stateCount[0] + $stateCount[1] + $stateCount[2] + $stateCount[3] +
            $stateCount[4];

        if (5 * abs($stateCountTotal - $originalStateCountTotal) >= $originalStateCountTotal) {
            return NAN;
        }

        return self::foundPatternCross($stateCount) ? self::centerFromEnd($stateCount, $j) : NAN;
    }

    /**
     * Verify the candidate diagonally when pure-barcode mode is active.
     *
     * @param int       $startI                  Row where the candidate was detected.
     * @param int       $centerJ                 Center column estimate.
     * @param float|int $maxCount                Maximum run length allowed in a state.
     * @param float|int $originalStateCountTotal Original horizontal run total.
     *
     * @return bool True when the diagonal cross-check stays within tolerance.
     */
    private function crossCheckDiagonal(int $startI, int $centerJ, $maxCount, int|float $originalStateCountTotal): bool
    {
        $stateCount = $this->getCrossCheckStateCount();

        // Start counting up, left from center finding black center mass
        $i = 0;

        while ($startI >= $i && $centerJ >= $i && $this->image->get($centerJ - $i, $startI - $i)) {
            ++$stateCount[2];
            ++$i;
        }

        if ($startI < $i || $centerJ < $i) {
            return false;
        }

        // Continue up, left finding white space
        while (
            $startI >= $i && $centerJ >= $i && !$this->image->get($centerJ - $i, $startI - $i)
            && $stateCount[1] <= $maxCount
        ) {
            ++$stateCount[1];
            ++$i;
        }

        // If already too many modules in this state or ran off the edge:
        if ($startI < $i || $centerJ < $i || $stateCount[1] > $maxCount) {
            return false;
        }

        // Continue up, left finding black border
        while (
            $startI >= $i && $centerJ >= $i && $this->image->get($centerJ - $i, $startI - $i)
            && $stateCount[0] <= $maxCount
        ) {
            ++$stateCount[0];
            ++$i;
        }

        if ($stateCount[0] > $maxCount) {
            return false;
        }

        $maxI = $this->image->getHeight();
        $maxJ = $this->image->getWidth();

        // Now also count down, right from center
        $i = 1;

        while ($startI + $i < $maxI && $centerJ + $i < $maxJ && $this->image->get($centerJ + $i, $startI + $i)) {
            ++$stateCount[2];
            ++$i;
        }

        // Ran off the edge?
        if ($startI + $i >= $maxI || $centerJ + $i >= $maxJ) {
            return false;
        }

        while (
            $startI + $i < $maxI && $centerJ + $i < $maxJ && !$this->image->get($centerJ + $i, $startI + $i)
            && $stateCount[3] < $maxCount
        ) {
            ++$stateCount[3];
            ++$i;
        }

        if ($startI + $i >= $maxI || $centerJ + $i >= $maxJ || $stateCount[3] >= $maxCount) {
            return false;
        }

        while (
            $startI + $i < $maxI && $centerJ + $i < $maxJ && $this->image->get($centerJ + $i, $startI + $i)
            && $stateCount[4] < $maxCount
        ) {
            ++$stateCount[4];
            ++$i;
        }

        if ($stateCount[4] >= $maxCount) {
            return false;
        }

        // If we found a finder-pattern-like section, but its size is more than 100% different than
        // the original, assume it's a false positive
        $stateCountTotal = $stateCount[0] + $stateCount[1] + $stateCount[2] + $stateCount[3] + $stateCount[4];

        return
            abs($stateCountTotal - $originalStateCountTotal) < 2 * $originalStateCountTotal
            && self::foundPatternCross($stateCount);
    }

    /**
     * Decide whether the candidate set is stable enough to stop scanning.
     *
     * The search can terminate early once at least three centers have been seen
     * often enough and their estimated module sizes remain close to each other.
     */
    private function haveMultiplyConfirmedCenters(?float $allowedDeviation = 0.05): bool
    {
        $confirmedCount = 0;
        $totalModuleSize = 0.0;
        $max = count($this->possibleCenters);

        foreach ($this->possibleCenters as $pattern) {
            if ($pattern->getCount() < self::$CENTER_QUORUM) {
                continue;
            }

            ++$confirmedCount;
            $totalModuleSize += $pattern->getEstimatedModuleSize();
        }

        if ($confirmedCount < 3) {
            return false;
        }
        // OK, we have at least 3 confirmed centers, but, it's possible that one is a "false positive"
        // and that we need to keep looking. We detect this by asking if the estimated module sizes
        // vary too much. We arbitrarily say that when the total deviation from average exceeds
        // 5% of the total module size estimates, it's too much.
        $average = $totalModuleSize / (float) $max;
        $totalDeviation = 0.0;

        foreach ($this->possibleCenters as $pattern) {
            $totalDeviation += abs($pattern->getEstimatedModuleSize() - $average);
        }

        return $totalDeviation <= $allowedDeviation * $totalModuleSize;
    }

    /**
     * Estimate how many rows can be skipped after two strong confirmations.
     *
     * The skip value is derived from the spacing between the first two confirmed
     * centers and is only used as an acceleration hint for later scans.
     */
    private function findRowSkip()
    {
        $max = count($this->possibleCenters);

        if ($max <= 1) {
            return 0;
        }
        $firstConfirmedCenter = null;

        foreach ($this->possibleCenters as $center) {
            if ($center->getCount() < self::$CENTER_QUORUM) {
                continue;
            }

            if ($firstConfirmedCenter !== null) {
                // We have two confirmed centers
                // How far down can we skip before resuming looking for the next
                // pattern? In the worst case, only the difference between the
                // difference in the x / y coordinates of the two centers.
                // This is the case where you find top left last.
                $this->hasSkipped = true;

                return (int) ((abs($firstConfirmedCenter->getX() - $center->getX()) -
                    abs($firstConfirmedCenter->getY() - $center->getY())) / 2);
            }

            $firstConfirmedCenter = $center;
        }

        return 0;
    }

    /**
     * Select the three most credible finder-pattern candidates.
     *
     * Candidates are ranked by hit count first and by closeness to the average
     * module size second, which suppresses outliers before perspective math is
     * applied.
     *
     * @throws NotFoundException    if 3 such finder patterns do not exist
     * @return array<FinderPattern> The three best candidates.
     */
    private function selectBestPatterns()
    {
        $startSize = count($this->possibleCenters);

        if ($startSize < 3) {
            // Couldn't find enough finder patterns
            throw NotFoundException::getNotFoundInstance("Could not find 3 finder patterns ({$startSize} found)");
        }

        // Filter outlier possibilities whose module size is too different
        if ($startSize > 3) {
            // But we can only afford to do so if we have at least 4 possibilities to choose from
            $totalModuleSize = 0.0;
            $square = 0.0;

            foreach ($this->possibleCenters as $center) {
                $size = $center->getEstimatedModuleSize();
                $totalModuleSize += $size;
                $square += $size * $size;
            }
            $this->average = $totalModuleSize / (float) $startSize;
            $stdDev = (float) sqrt($square / $startSize - $this->average * $this->average);

            usort($this->possibleCenters, $this->FurthestFromAverageComparator(...));

            $limit = max(0.2 * $this->average, $stdDev);

            for ($i = 0; $i < count($this->possibleCenters) && count($this->possibleCenters) > 3; ++$i) {
                $pattern = $this->possibleCenters[$i];

                if (abs($pattern->getEstimatedModuleSize() - $this->average) <= $limit) {
                    continue;
                }

                unset($this->possibleCenters[$i]); // возможно что ключи меняются в java при вызове .remove(i) ???
                $this->possibleCenters = array_values($this->possibleCenters);
                --$i;
            }
        }

        if (count($this->possibleCenters) > 3) {
            // Throw away all but those first size candidate points we found.

            $totalModuleSize = 0.0;

            foreach ($this->possibleCenters as $possibleCenter) {
                $totalModuleSize += $possibleCenter->getEstimatedModuleSize();
            }

            $this->average = $totalModuleSize / (float) count($this->possibleCenters);

            usort($this->possibleCenters, $this->CenterComparator(...));

            array_slice($this->possibleCenters, 3, count($this->possibleCenters) - 3);
        }

        return [$this->possibleCenters[0], $this->possibleCenters[1], $this->possibleCenters[2]];
    }
}
