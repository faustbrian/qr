<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Generator\Renderer\Path;

use function cos;
use function deg2rad;
use function sin;

/**
 * Cubic Bezier curve path operation.
 *
 * The operation stores two control points and one end point. It is immutable so
 * transformed variants can be created safely without mutating existing paths.
 * @author Brian Faust <brian@cline.sh>
 */
final class Curve implements OperationInterface
{
    public function __construct(
        private readonly float $x1,
        private readonly float $y1,
        private readonly float $x2,
        private readonly float $y2,
        private readonly float $x3,
        private readonly float $y3,
    ) {}

    /**
     * Return the first control-point x coordinate.
     */
    public function getX1(): float
    {
        return $this->x1;
    }

    /**
     * Return the first control-point y coordinate.
     */
    public function getY1(): float
    {
        return $this->y1;
    }

    /**
     * Return the second control-point x coordinate.
     */
    public function getX2(): float
    {
        return $this->x2;
    }

    /**
     * Return the second control-point y coordinate.
     */
    public function getY2(): float
    {
        return $this->y2;
    }

    /**
     * Return the end-point x coordinate.
     */
    public function getX3(): float
    {
        return $this->x3;
    }

    /**
     * Return the end-point y coordinate.
     */
    public function getY3(): float
    {
        return $this->y3;
    }

    /**
     * Return a translated copy of the curve.
     *
     * @return self
     */
    public function translate(float $x, float $y): OperationInterface
    {
        return new self(
            $this->x1 + $x,
            $this->y1 + $y,
            $this->x2 + $x,
            $this->y2 + $y,
            $this->x3 + $x,
            $this->y3 + $y,
        );
    }

    /**
     * Return a rotated copy of the curve around the origin.
     *
     * @return self
     */
    public function rotate(int $degrees): OperationInterface
    {
        $radians = deg2rad($degrees);
        $sin = sin($radians);
        $cos = cos($radians);
        $x1r = $this->x1 * $cos - $this->y1 * $sin;
        $y1r = $this->x1 * $sin + $this->y1 * $cos;
        $x2r = $this->x2 * $cos - $this->y2 * $sin;
        $y2r = $this->x2 * $sin + $this->y2 * $cos;
        $x3r = $this->x3 * $cos - $this->y3 * $sin;
        $y3r = $this->x3 * $sin + $this->y3 * $cos;

        return new self(
            $x1r,
            $y1r,
            $x2r,
            $y2r,
            $x3r,
            $y3r,
        );
    }
}
