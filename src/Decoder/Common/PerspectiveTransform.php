<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Decoder\Common;

use function count;
use function is_countable;

/**
 * Computes the projective transform used to rectify QR code quadrilaterals.
 *
 * The decoder uses this transform to map a detected quadrilateral back to a
 * square module grid. That makes it the mathematical bridge between finder
 * pattern detection and grid sampling.
 *
 * @author Sean Owen
 */
final class PerspectiveTransform
{
    /**
     * @param float $a11 matrix coefficient
     * @param float $a21 matrix coefficient
     * @param float $a31 matrix coefficient
     * @param float $a12 matrix coefficient
     * @param float $a22 matrix coefficient
     * @param float $a32 matrix coefficient
     * @param float $a13 matrix coefficient
     * @param float $a23 matrix coefficient
     * @param float $a33 matrix coefficient
     */
    private function __construct(
        private readonly float $a11,
        private readonly float $a21,
        private readonly float $a31,
        private readonly float $a12,
        private readonly float $a22,
        private readonly float $a32,
        private readonly float $a13,
        private readonly float $a23,
        private readonly float $a33,
    ) {}

    /**
     * Builds the transform that maps one quadrilateral directly onto another.
     *
     * The result is composed from a square-to-quadrilateral transform and its
     * inverse so callers do not need to manage the intermediate matrices.
     */
    public static function quadrilateralToQuadrilateral(
        float $x0,
        float $y0,
        float $x1,
        float $y1,
        float $x2,
        float $y2,
        float $x3,
        float $y3,
        float $x0p,
        float $y0p,
        float $x1p,
        float $y1p,
        float $x2p,
        float $y2p,
        float $x3p,
        float $y3p,
    ): self {
        $qToS = self::quadrilateralToSquare($x0, $y0, $x1, $y1, $x2, $y2, $x3, $y3);
        $sToQ = self::squareToQuadrilateral($x0p, $y0p, $x1p, $y1p, $x2p, $y2p, $x3p, $y3p);

        return $sToQ->times($qToS);
    }

    /**
     * Returns the inverse transform for the given quadrilateral.
     *
     * When the quadrilateral is affine, the inverse is computed via the adjoint;
     * otherwise the projective coefficients are derived directly.
     */
    public static function quadrilateralToSquare(
        float $x0,
        float $y0,
        float $x1,
        float $y1,
        float $x2,
        float $y2,
        float $x3,
        float $y3,
    ): self {
        // Here, the adjoint serves as the inverse:
        return self::squareToQuadrilateral($x0, $y0, $x1, $y1, $x2, $y2, $x3, $y3)->buildAdjoint();
    }

    /**
     * Builds a transform that maps the unit square to the supplied quadrilateral.
     *
     * This is the core constructor used to rectify the sampled QR code region.
     */
    public static function squareToQuadrilateral(
        float $x0,
        float $y0,
        float $x1,
        float $y1,
        float $x2,
        float $y2,
        float $x3,
        float $y3,
    ): self {
        $dx3 = $x0 - $x1 + $x2 - $x3;
        $dy3 = $y0 - $y1 + $y2 - $y3;

        if ($dx3 === 0.0 && $dy3 === 0.0) {
            // Affine
            return new self(
                $x1 - $x0,
                $x2 - $x1,
                $x0,
                $y1 - $y0,
                $y2 - $y1,
                $y0,
                0.0,
                0.0,
                1.0,
            );
        }

        $dx1 = $x1 - $x2;
        $dx2 = $x3 - $x2;
        $dy1 = $y1 - $y2;
        $dy2 = $y3 - $y2;
        $denominator = $dx1 * $dy2 - $dx2 * $dy1;
        $a13 = ($dx3 * $dy2 - $dx2 * $dy3) / $denominator;
        $a23 = ($dx1 * $dy3 - $dx3 * $dy1) / $denominator;

        return new self(
            $x1 - $x0 + $a13 * $x1,
            $x3 - $x0 + $a23 * $x3,
            $x0,
            $y1 - $y0 + $a13 * $y1,
            $y3 - $y0 + $a23 * $y3,
            $y0,
            $a13,
            $a23,
            1.0,
        );
    }

    /**
     * Returns the adjoint matrix, which serves as the inverse for affine cases.
     */
    public function buildAdjoint(): self
    {
        // Adjoint is the transpose of the cofactor matrix:
        return new self(
            $this->a22 * $this->a33 - $this->a23 * $this->a32,
            $this->a23 * $this->a31 - $this->a21 * $this->a33,
            $this->a21 * $this->a32 - $this->a22 * $this->a31,
            $this->a13 * $this->a32 - $this->a12 * $this->a33,
            $this->a11 * $this->a33 - $this->a13 * $this->a31,
            $this->a12 * $this->a31 - $this->a11 * $this->a32,
            $this->a12 * $this->a23 - $this->a13 * $this->a22,
            $this->a13 * $this->a21 - $this->a11 * $this->a23,
            $this->a11 * $this->a22 - $this->a12 * $this->a21,
        );
    }

    /**
     * Composes this transform with another transform.
     *
     * Matrix multiplication order matters here: the other transform is applied
     * first, then this transform is applied to the result.
     */
    public function times(self $other): self
    {
        return new self(
            $this->a11 * $other->a11 + $this->a21 * $other->a12 + $this->a31 * $other->a13,
            $this->a11 * $other->a21 + $this->a21 * $other->a22 + $this->a31 * $other->a23,
            $this->a11 * $other->a31 + $this->a21 * $other->a32 + $this->a31 * $other->a33,
            $this->a12 * $other->a11 + $this->a22 * $other->a12 + $this->a32 * $other->a13,
            $this->a12 * $other->a21 + $this->a22 * $other->a22 + $this->a32 * $other->a23,
            $this->a12 * $other->a31 + $this->a22 * $other->a32 + $this->a32 * $other->a33,
            $this->a13 * $other->a11 + $this->a23 * $other->a12 + $this->a33 * $other->a13,
            $this->a13 * $other->a21 + $this->a23 * $other->a22 + $this->a33 * $other->a23,
            $this->a13 * $other->a31 + $this->a23 * $other->a32 + $this->a33 * $other->a33,
        );
    }

    /**
     * Transforms interleaved x/y point pairs in place.
     *
     * @param array<float|mixed> $points  point buffer containing x/y pairs
     * @param mixed              $yValues
     *
     * @psalm-param array<int, float|mixed> $points
     */
    public function transformPoints(array &$points, $yValues = 0): void
    {
        if ($yValues) {
            $this->transformPoints_($points, $yValues);

            return;
        }
        $max = is_countable($points) ? count($points) : 0;
        $a11 = $this->a11;
        $a12 = $this->a12;
        $a13 = $this->a13;
        $a21 = $this->a21;
        $a22 = $this->a22;
        $a23 = $this->a23;
        $a31 = $this->a31;
        $a32 = $this->a32;
        $a33 = $this->a33;

        for ($i = 0; $i < $max; $i += 2) {
            $x = $points[$i];
            $y = $points[$i + 1];
            $denominator = $a13 * $x + $a23 * $y + $a33;

            // TODO: think what we do if $denominator == 0 (division by zero)
            if ($denominator === 0.0) {
                continue;
            }

            $points[$i] = ($a11 * $x + $a21 * $y + $a31) / $denominator;
            $points[$i + 1] = ($a12 * $x + $a22 * $y + $a32) / $denominator;
        }
    }

    /**
     * Transforms parallel x and y arrays in place.
     *
     * @param array<float|mixed> $xValues x-coordinate buffer
     * @param mixed              $yValues
     *
     * @psalm-param array<int, float|mixed> $xValues
     */
    public function transformPoints_(array &$xValues, $yValues): void
    {
        $n = is_countable($xValues) ? count($xValues) : 0;

        for ($i = 0; $i < $n; ++$i) {
            $x = $xValues[$i];
            $y = $yValues[$i];
            $denominator = $this->a13 * $x + $this->a23 * $y + $this->a33;
            $xValues[$i] = ($this->a11 * $x + $this->a21 * $y + $this->a31) / $denominator;
            $yValues[$i] = ($this->a12 * $x + $this->a22 * $y + $this->a32) / $denominator;
        }
    }
}
