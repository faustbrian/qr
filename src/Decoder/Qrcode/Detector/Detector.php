<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Decoder\Qrcode\Detector;

use Cline\Qr\Decoder\Common\AbstractGridSampler;
use Cline\Qr\Decoder\Common\BitMatrix;
use Cline\Qr\Decoder\Common\Detector\MathUtils;
use Cline\Qr\Decoder\Common\DetectorResult;
use Cline\Qr\Decoder\Common\PerspectiveTransform;
use Cline\Qr\Decoder\DecodeHintType;
use Cline\Qr\Decoder\FormatException;
use Cline\Qr\Decoder\NotFoundException;
use Cline\Qr\Decoder\Qrcode\Decoder\Version;
use Cline\Qr\Decoder\ResultPoint;
use Cline\Qr\Decoder\ResultPointCallback;

use const NAN;

use function abs;
use function array_key_exists;
use function count;
use function is_countable;
use function is_nan;
use function max;
use function min;
use function round;

/**
 * Geometric QR Code detector.
 *
 * This class turns a binary image into a sampled QR matrix by locating finder
 * patterns, estimating module size, optionally recovering an alignment
 * pattern, and then applying a perspective transform to the image.
 *
 * @author Sean Owen
 */
final class Detector
{
    private $resultPointCallback;

    /**
     * @param BitMatrix $image Binarized image to inspect.
     */
    public function __construct(
        private readonly BitMatrix $image,
    ) {}

    /**
     * Detect a QR Code and return the sampled bits plus the detected points.
     *
     * @param null|array $hints Optional decode hints.
     *
     * @throws FormatException   if a QR Code cannot be decoded
     * @throws NotFoundException if QR Code cannot be found
     * @return DetectorResult    Detection result containing the sampled matrix and
     *                           reference points.
     */
    public function detect(?array $hints = null): DetectorResult
    {/* Map<DecodeHintType,?> */
        $resultPointCallback = ($hints !== null && array_key_exists('NEED_RESULT_POINT_CALLBACK', $hints)) ?
            $hints['NEED_RESULT_POINT_CALLBACK'] : null;
        /*
                * resultPointCallback = hints == null ? null :
                (ResultPointCallback) hints.get(DecodeHintType.NEED_RESULT_POINT_CALLBACK);
                */
        $finder = new FinderPatternFinder($this->image, $resultPointCallback);
        $info = $finder->find($hints);

        return $this->processFinderPatternInfo($info);
    }

    /**
     * Convert finder-pattern geometry into a sampled QR symbol.
     *
     * @param FinderPatternInfo $info Ordered finder patterns from detection.
     *
     * @return DetectorResult Sampled matrix and the points used to derive it.
     */
    protected function processFinderPatternInfo(FinderPatternInfo $info): DetectorResult
    {
        $topLeft = $info->getTopLeft();
        $topRight = $info->getTopRight();
        $bottomLeft = $info->getBottomLeft();

        $moduleSize = (float) $this->calculateModuleSize($topLeft, $topRight, $bottomLeft);

        if ($moduleSize < 1.0) {
            throw NotFoundException::getNotFoundInstance("Module size {$moduleSize} < 1.0");
        }
        $dimension = (int) self::computeDimension($topLeft, $topRight, $bottomLeft, $moduleSize);
        $provisionalVersion = Version::getProvisionalVersionForDimension($dimension);
        $modulesBetweenFPCenters = $provisionalVersion->getDimensionForVersion() - 7;

        $alignmentPattern = null;

        // Anything above version 1 has an alignment pattern
        if ((is_countable($provisionalVersion->getAlignmentPatternCenters()) ? count($provisionalVersion->getAlignmentPatternCenters()) : 0) > 0) {
            // Guess where a "bottom right" finder pattern would have been
            $bottomRightX = $topRight->getX() - $topLeft->getX() + $bottomLeft->getX();
            $bottomRightY = $topRight->getY() - $topLeft->getY() + $bottomLeft->getY();

            // Estimate that alignment pattern is closer by 3 modules
            // from "bottom right" to known top left location
            $correctionToTopLeft = 1.0 - 3.0 / (float) $modulesBetweenFPCenters;
            $estAlignmentX = (int) round($topLeft->getX() + $correctionToTopLeft * ($bottomRightX - $topLeft->getX()));
            $estAlignmentY = (int) round($topLeft->getY() + $correctionToTopLeft * ($bottomRightY - $topLeft->getY()));

            // Kind of arbitrary -- expand search radius before giving up
            for ($i = 4; $i <= 16; $i = $i << 1) { // ??????????
                try {
                    $alignmentPattern = $this->findAlignmentInRegion(
                        $moduleSize,
                        $estAlignmentX,
                        $estAlignmentY,
                        (float) $i,
                    );

                    break;
                } catch (NotFoundException $e) {
                    // try next round
                    $alignmentPattern = null;
                }
            }
            // If we didn't find alignment pattern... well try anyway without it
        }

        $transform = self::createTransform($topLeft, $topRight, $bottomLeft, $alignmentPattern, $dimension);

        $bits = self::sampleGrid($this->image, $transform, $dimension);

        $points = [];

        if ($alignmentPattern === null) {
            $points = [$bottomLeft, $topLeft, $topRight];
        } else {
            // die('$points = new ResultPoint[]{bottomLeft, topLeft, topRight, alignmentPattern};');
            $points = [$bottomLeft, $topLeft, $topRight, $alignmentPattern];
        }

        return new DetectorResult($bits, $points);
    }

    /**
     * Estimate the module size from the three finder patterns.
     *
     * The detector averages the top-left/top-right and top-left/bottom-left
     * edges to reduce bias from skew or local distortion.
     *
     * @param FinderPattern $topLeft    Detected top-left finder pattern center.
     * @param FinderPattern $topRight   Detected top-right finder pattern center.
     * @param FinderPattern $bottomLeft Detected bottom-left finder pattern center.
     *
     * @return float Estimated module size in pixels.
     */
    protected function calculateModuleSize($topLeft, $topRight, $bottomLeft): float
    {
        // Take the average
        return ($this->calculateModuleSizeOneWay($topLeft, $topRight) +
            $this->calculateModuleSizeOneWay($topLeft, $bottomLeft)) / 2.0;
    }

    /**
     * Search the predicted alignment-pattern region.
     *
     * @param float $overallEstModuleSize Estimated module size.
     * @param int   $estAlignmentX        Predicted alignment-pattern center X.
     * @param int   $estAlignmentY        Predicted alignment-pattern center Y.
     * @param float $allowanceFactor      Search radius multiplier around the estimate.
     *
     * @throws NotFoundException if an unexpected error occurs during detection
     * @return AlignmentPattern  Confirmed alignment pattern when found.
     */
    protected function findAlignmentInRegion(
        float $overallEstModuleSize,
        int $estAlignmentX,
        int $estAlignmentY,
        float $allowanceFactor,
    ) {
        // Look for an alignment pattern (3 modules in size) around where it
        // should be
        $allowance = (int) ($allowanceFactor * $overallEstModuleSize);
        $alignmentAreaLeftX = max(0, $estAlignmentX - $allowance);
        $alignmentAreaRightX = min($this->image->getWidth() - 1, $estAlignmentX + $allowance);

        if ($alignmentAreaRightX - $alignmentAreaLeftX < $overallEstModuleSize * 3) {
            throw NotFoundException::getNotFoundInstance("Alignment area right smaller than overall module size: {$alignmentAreaRightX} - {$alignmentAreaLeftX} < {$overallEstModuleSize} * 3. Allowance: {$allowance}, estimage of x: {$estAlignmentX}");
        }

        $alignmentAreaTopY = max(0, $estAlignmentY - $allowance);
        $alignmentAreaBottomY = min($this->image->getHeight() - 1, $estAlignmentY + $allowance);

        if ($alignmentAreaBottomY - $alignmentAreaTopY < $overallEstModuleSize * 3) {
            throw NotFoundException::getNotFoundInstance("Alignment area bottom smaller than overall module size: {$alignmentAreaBottomY} - {$alignmentAreaTopY} < {$overallEstModuleSize} * 3. Allowance: {$allowance}, estimage of y: {$estAlignmentY}");
        }

        $alignmentFinder =
            new AlignmentPatternFinder(
                $this->image,
                $alignmentAreaLeftX,
                $alignmentAreaTopY,
                $alignmentAreaRightX - $alignmentAreaLeftX,
                $alignmentAreaBottomY - $alignmentAreaTopY,
                $overallEstModuleSize,
                $this->resultPointCallback,
            );

        return $alignmentFinder->find();
    }

    /**
     * Return the binary image currently under analysis.
     */
    protected function getImage()
    {
        return $this->image;
    }

    /**
     * Return the optional callback used to report intermediate result points.
     */
    protected function getResultPointCallback()
    {
        return $this->resultPointCallback;
    }

    /**
     * Compute the symbol dimension from finder geometry and module size.
     *
     * The returned value is normalized to a valid QR Code dimension and adjusted
     * to the nearest admissible modulo-4 class.
     * @param mixed $topLeft
     * @param mixed $topRight
     * @param mixed $bottomLeft
     */
    private static function computeDimension(
        $topLeft,
        $topRight,
        $bottomLeft,
        float $moduleSize,
    ): int {
        $tltrCentersDimension = MathUtils::round(ResultPoint::distance($topLeft, $topRight) / $moduleSize);
        $tlblCentersDimension = MathUtils::round(ResultPoint::distance($topLeft, $bottomLeft) / $moduleSize);
        $dimension = (int) round((($tltrCentersDimension + $tlblCentersDimension) / 2) + 7);

        switch ($dimension & 0x03) { // mod 4
            case 0:
                $dimension++;

                break;

                // 1? do nothing
            case 2:
                $dimension--;

                break;

            case 3:
                throw NotFoundException::getNotFoundInstance("Dimension ({$dimension}) mod 4 == 3 unusable");
        }

        return (int) round($dimension);
    }

    private static function createTransform(
        $topLeft,
        $topRight,
        $bottomLeft,
        $alignmentPattern,
        int $dimension,
    ): PerspectiveTransform {
        $dimMinusThree = (float) $dimension - 3.5;
        $bottomRightX = 0.0;
        $bottomRightY = 0.0;
        $sourceBottomRightX = 0.0;
        $sourceBottomRightY = 0.0;

        if ($alignmentPattern !== null) {
            $bottomRightX = $alignmentPattern->getX();
            $bottomRightY = $alignmentPattern->getY();
            $sourceBottomRightX = $dimMinusThree - 3.0;
            $sourceBottomRightY = $sourceBottomRightX;
        } else {
            // Don't have an alignment pattern, just make up the bottom-right point
            $bottomRightX = ($topRight->getX() - $topLeft->getX()) + $bottomLeft->getX();
            $bottomRightY = ($topRight->getY() - $topLeft->getY()) + $bottomLeft->getY();
            $sourceBottomRightX = $dimMinusThree;
            $sourceBottomRightY = $dimMinusThree;
        }

        return PerspectiveTransform::quadrilateralToQuadrilateral(
            3.5,
            3.5,
            $dimMinusThree,
            3.5,
            $sourceBottomRightX,
            $sourceBottomRightY,
            3.5,
            $dimMinusThree,
            $topLeft->getX(),
            $topLeft->getY(),
            $topRight->getX(),
            $topRight->getY(),
            $bottomRightX,
            $bottomRightY,
            $bottomLeft->getX(),
            $bottomLeft->getY(),
        );
    }

    private static function sampleGrid(
        $image,
        PerspectiveTransform $transform,
        int $dimension,
    ): BitMatrix {
        $sampler = AbstractGridSampler::getInstance();

        return $sampler->sampleGrid_($image, $dimension, $dimension, $transform);
    }

    /**
     * Estimate module size using a single pair of finder patterns.
     *
     * The method measures the black/white/black run between the pattern centers
     * in both directions and then normalizes by the width of a finder pattern.
     */
    private function calculateModuleSizeOneWay(FinderPattern $pattern, FinderPattern $otherPattern): float
    {
        $moduleSizeEst1 = $this->sizeOfBlackWhiteBlackRunBothWays(
            (int) $pattern->getX(),
            (int) $pattern->getY(),
            (int) $otherPattern->getX(),
            (int) $otherPattern->getY(),
        );
        $moduleSizeEst2 = $this->sizeOfBlackWhiteBlackRunBothWays(
            (int) $otherPattern->getX(),
            (int) $otherPattern->getY(),
            (int) $pattern->getX(),
            (int) $pattern->getY(),
        );

        if (is_nan($moduleSizeEst1)) {
            return $moduleSizeEst2 / 7.0;
        }

        if (is_nan($moduleSizeEst2)) {
            return $moduleSizeEst1 / 7.0;
        }

        // Average them, and divide by 7 since we've counted the width of 3 black modules,
        // and 1 white and 1 black module on either side. Ergo, divide sum by 14.
        return ($moduleSizeEst1 + $moduleSizeEst2) / 14.0;
    }

    /**
     * Measure a run in both directions to reduce skew-induced error.
     */
    private function sizeOfBlackWhiteBlackRunBothWays(int $fromX, int $fromY, int $toX, int $toY): float
    {
        $result = $this->sizeOfBlackWhiteBlackRun($fromX, $fromY, $toX, $toY);

        // Now count other way -- don't run off image though of course
        $scale = 1.0;
        $otherToX = $fromX - ($toX - $fromX);

        if ($otherToX < 0) {
            $scale = (float) $fromX / (float) ($fromX - $otherToX);
            $otherToX = 0;
        } elseif ($otherToX >= $this->image->getWidth()) {
            $scale = (float) ($this->image->getWidth() - 1 - $fromX) / (float) ($otherToX - $fromX);
            $otherToX = $this->image->getWidth() - 1;
        }
        $otherToY = (int) ($fromY - ($toY - $fromY) * $scale);

        $scale = 1.0;

        if ($otherToY < 0) {
            $scale = (float) $fromY / (float) ($fromY - $otherToY);
            $otherToY = 0;
        } elseif ($otherToY >= $this->image->getHeight()) {
            $scale = (float) ($this->image->getHeight() - 1 - $fromY) / (float) ($otherToY - $fromY);
            $otherToY = $this->image->getHeight() - 1;
        }
        $otherToX = (int) ($fromX + ($otherToX - $fromX) * $scale);

        $result += $this->sizeOfBlackWhiteBlackRun($fromX, $fromY, $otherToX, $otherToY);

        // Middle pixel is double-counted this way; subtract 1
        return $result - 1.0;
    }

    /**
     * Measure the black/white/black run along the line between two points.
     *
     * The result is used to infer finder-pattern width even when the symbol is
     * rotated, skewed, or partially clipped by the image boundary.
     */
    private function sizeOfBlackWhiteBlackRun(int $fromX, int $fromY, int $toX, int|float $toY): float
    {
        // Mild variant of Bresenham's algorithm;
        // see http://en.wikipedia.org/wiki/Bresenham's_line_algorithm
        $steep = abs($toY - $fromY) > abs($toX - $fromX);

        if ($steep) {
            $temp = $fromX;
            $fromX = $fromY;
            $fromY = $temp;
            $temp = $toX;
            $toX = $toY;
            $toY = $temp;
        }

        $dx = abs($toX - $fromX);
        $dy = abs($toY - $fromY);
        $error = -$dx / 2;
        $xstep = $fromX < $toX ? 1 : -1;
        $ystep = $fromY < $toY ? 1 : -1;

        // In black pixels, looking for white, first or second time.
        $state = 0;
        // Loop up until x == toX, but not beyond
        $xLimit = $toX + $xstep;

        for ($x = $fromX, $y = $fromY; $x !== $xLimit; $x += $xstep) {
            $realX = $steep ? $y : $x;
            $realY = $steep ? $x : $y;

            // Does current pixel mean we have moved white to black or vice versa?
            // Scanning black in state 0,2 and white in state 1, so if we find the wrong
            // color, advance to next state or end if we are in state 2 already
            if (($state === 1) === $this->image->get($realX, $realY)) {
                if ($state === 2) {
                    return MathUtils::distance($x, $y, $fromX, $fromY);
                }
                ++$state;
            }

            $error += $dy;

            if ($error <= 0) {
                continue;
            }

            if ($y === $toY) {
                break;
            }
            $y += $ystep;
            $error -= $dx;
        }

        // Found black-white-black; give the benefit of the doubt that the next pixel outside the image
        // is "white" so this last po$at (toX+xStep,toY) is the right ending. This is really a
        // small approximation; (toX+xStep,toY+yStep) might be really correct. Ignore this.
        if ($state === 2) {
            return MathUtils::distance($toX + $xstep, $toY, $fromX, $fromY);
        }

        // else we didn't find even black-white-black; no estimate is really possible
        return NAN;
    }
}
