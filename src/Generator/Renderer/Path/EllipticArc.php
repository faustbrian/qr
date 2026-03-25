<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Generator\Renderer\Path;

use const M_PI;

use function abs;
use function acos;
use function cos;
use function deg2rad;
use function fmod;
use function max;
use function min;
use function sin;
use function sqrt;
use function tan;

/**
 * Elliptic arc path operation using the SVG-style arc parameterization.
 *
 * Some back ends can render arcs natively, while others ask this object to
 * approximate the same geometry with one or more cubic Bezier curves.
 * @author Brian Faust <brian@cline.sh>
 */
final class EllipticArc implements OperationInterface
{
    private const float ZERO_TOLERANCE = 1e-05;

    private float $xRadius;

    private float $yRadius;

    private float $xAxisAngle;

    public function __construct(
        float $xRadius,
        float $yRadius,
        float $xAxisAngle,
        private readonly bool $largeArc,
        private readonly bool $sweep,
        private readonly float $x,
        private readonly float $y,
    ) {
        $this->xRadius = abs($xRadius);
        $this->yRadius = abs($yRadius);
        $this->xAxisAngle = $xAxisAngle % 360;
    }

    /**
     * Return the normalized x radius.
     */
    public function getXRadius(): float
    {
        return $this->xRadius;
    }

    /**
     * Return the normalized y radius.
     */
    public function getYRadius(): float
    {
        return $this->yRadius;
    }

    /**
     * Return the x-axis rotation in degrees.
     */
    public function getXAxisAngle(): float
    {
        return $this->xAxisAngle;
    }

    /**
     * Return whether the large-arc branch should be used.
     */
    public function isLargeArc(): bool
    {
        return $this->largeArc;
    }

    /**
     * Return whether the arc should sweep in the positive-angle direction.
     */
    public function isSweep(): bool
    {
        return $this->sweep;
    }

    /**
     * Return the arc end-point x coordinate.
     */
    public function getX(): float
    {
        return $this->x;
    }

    /**
     * Return the arc end-point y coordinate.
     */
    public function getY(): float
    {
        return $this->y;
    }

    /**
     * Return a translated copy of the arc.
     *
     * @return self
     */
    public function translate(float $x, float $y): OperationInterface
    {
        return new self(
            $this->xRadius,
            $this->yRadius,
            $this->xAxisAngle,
            $this->largeArc,
            $this->sweep,
            $this->x + $x,
            $this->y + $y,
        );
    }

    /**
     * Return a rotated copy of the arc around the origin.
     *
     * @return self
     */
    public function rotate(int $degrees): OperationInterface
    {
        $radians = deg2rad($degrees);
        $sin = sin($radians);
        $cos = cos($radians);
        $xr = $this->x * $cos - $this->y * $sin;
        $yr = $this->x * $sin + $this->y * $cos;

        return new self(
            $this->xRadius,
            $this->yRadius,
            $this->xAxisAngle,
            $this->largeArc,
            $this->sweep,
            $xr,
            $yr,
        );
    }

    /**
     * Convert the arc into one or more cubic curves when needed.
     *
     * When the start and end points coincide, the arc contributes nothing. When
     * either radius collapses to zero, the arc degenerates into a straight
     * line.
     *
     * @see https://mortoray.com/2017/02/16/rendering-an-svg-elliptical-arc-as-bezier-curves/
     * @return array<Curve|Line>
     */
    public function toCurves(float $fromX, float $fromY): array
    {
        if (sqrt(($fromX - $this->x) ** 2 + ($fromY - $this->y) ** 2) < self::ZERO_TOLERANCE) {
            return [];
        }

        if ($this->xRadius < self::ZERO_TOLERANCE || $this->yRadius < self::ZERO_TOLERANCE) {
            return [new Line($this->x, $this->y)];
        }

        return $this->createCurves($fromX, $fromY);
    }

    /**
     * Return the signed angle between two vectors.
     */
    private static function angle(float $ux, float $uy, float $vx, float $vy): float
    {
        // F.6.5.4
        $dot = $ux * $vx + $uy * $vy;
        $length = sqrt($ux ** 2 + $uy ** 2) * sqrt($vx ** 2 + $vy ** 2);
        $angle = acos(min(1, max(-1, $dot / $length)));

        if (($ux * $vy - $uy * $vx) < 0) {
            return -$angle;
        }

        return $angle;
    }

    /**
     * Return the point reached on the ellipse at the given parametric angle.
     *
     * @return array<float>
     */
    private static function point(
        float $centerX,
        float $centerY,
        float $radiusX,
        float $radiusY,
        float $xAngle,
        float $angle,
    ): array {
        return [
            $centerX + $radiusX * cos($xAngle) * cos($angle) - $radiusY * sin($xAngle) * sin($angle),
            $centerY + $radiusX * sin($xAngle) * cos($angle) + $radiusY * cos($xAngle) * sin($angle),
        ];
    }

    /**
     * Return the derivative vector on the ellipse at the given angle.
     *
     * @return array<float>
     */
    private static function derivative(float $radiusX, float $radiusY, float $xAngle, float $angle): array
    {
        return [
            -$radiusX * cos($xAngle) * sin($angle) - $radiusY * sin($xAngle) * cos($angle),
            -$radiusX * sin($xAngle) * sin($angle) + $radiusY * cos($xAngle) * cos($angle),
        ];
    }

    /**
     * Build the cubic curve segments that approximate this arc.
     *
     * @return array<Curve>
     */
    private function createCurves(float $fromX, float $fromY): array
    {
        $xAngle = deg2rad($this->xAxisAngle);
        [$centerX, $centerY, $radiusX, $radiusY, $startAngle, $deltaAngle] =
            $this->calculateCenterPointParameters($fromX, $fromY, $xAngle);

        $s = $startAngle;
        $e = $s + $deltaAngle;
        $sign = ($e < $s) ? -1 : 1;
        $remain = abs($e - $s);
        $p1 = self::point($centerX, $centerY, $radiusX, $radiusY, $xAngle, $s);
        $curves = [];

        while ($remain > self::ZERO_TOLERANCE) {
            $step = min($remain, M_PI / 2);
            $signStep = $step * $sign;
            $p2 = self::point($centerX, $centerY, $radiusX, $radiusY, $xAngle, $s + $signStep);

            $alphaT = tan($signStep / 2);
            $alpha = sin($signStep) * (sqrt(4 + 3 * $alphaT ** 2) - 1) / 3;
            $d1 = self::derivative($radiusX, $radiusY, $xAngle, $s);
            $d2 = self::derivative($radiusX, $radiusY, $xAngle, $s + $signStep);

            $curves[] = new Curve(
                $p1[0] + $alpha * $d1[0],
                $p1[1] + $alpha * $d1[1],
                $p2[0] - $alpha * $d2[0],
                $p2[1] - $alpha * $d2[1],
                $p2[0],
                $p2[1],
            );

            $s += $signStep;
            $remain -= $step;
            $p1 = $p2;
        }

        return $curves;
    }

    /**
     * Resolve the center-point representation used for arc subdivision.
     *
     * @return array<float>
     */
    private function calculateCenterPointParameters(float $fromX, float $fromY, float $xAngle): array
    {
        $rX = $this->xRadius;
        $rY = $this->yRadius;

        // F.6.5.1
        $dx2 = ($fromX - $this->x) / 2;
        $dy2 = ($fromY - $this->y) / 2;
        $x1p = cos($xAngle) * $dx2 + sin($xAngle) * $dy2;
        $y1p = -sin($xAngle) * $dx2 + cos($xAngle) * $dy2;

        // F.6.5.2
        $rxs = $rX ** 2;
        $rys = $rY ** 2;
        $x1ps = $x1p ** 2;
        $y1ps = $y1p ** 2;
        $cr = $x1ps / $rxs + $y1ps / $rys;

        if ($cr > 1) {
            $s = sqrt($cr);
            $rX *= $s;
            $rY *= $s;
            $rxs = $rX ** 2;
            $rys = $rY ** 2;
        }

        $dq = $rxs * $y1ps + $rys * $x1ps;
        $pq = ($rxs * $rys - $dq) / $dq;
        $q = sqrt(max(0, $pq));

        if ($this->largeArc === $this->sweep) {
            $q = -$q;
        }

        $cxp = $q * $rX * $y1p / $rY;
        $cyp = -$q * $rY * $x1p / $rX;

        // F.6.5.3
        $cx = cos($xAngle) * $cxp - sin($xAngle) * $cyp + ($fromX + $this->x) / 2;
        $cy = sin($xAngle) * $cxp + cos($xAngle) * $cyp + ($fromY + $this->y) / 2;

        // F.6.5.5
        $theta = self::angle(1, 0, ($x1p - $cxp) / $rX, ($y1p - $cyp) / $rY);

        // F.6.5.6
        $delta = self::angle(($x1p - $cxp) / $rX, ($y1p - $cyp) / $rY, (-$x1p - $cxp) / $rX, (-$y1p - $cyp) / $rY);
        $delta = fmod($delta, M_PI * 2);

        if (!$this->sweep) {
            $delta -= 2 * M_PI;
        }

        return [$cx, $cy, $rX, $rY, $theta, $delta];
    }
}
