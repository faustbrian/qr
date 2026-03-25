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
 * Straight-line path operation.
 *
 * The operation stores only its end point because the start point is implied by
 * the preceding path operation.
 * @author Brian Faust <brian@cline.sh>
 */
final class Line implements OperationInterface
{
    public function __construct(
        private readonly float $x,
        private readonly float $y,
    ) {}

    /**
     * Return the end-point x coordinate.
     */
    public function getX(): float
    {
        return $this->x;
    }

    /**
     * Return the end-point y coordinate.
     */
    public function getY(): float
    {
        return $this->y;
    }

    /**
     * Return a translated copy of the line end point.
     *
     * @return self
     */
    public function translate(float $x, float $y): OperationInterface
    {
        return new self($this->x + $x, $this->y + $y);
    }

    /**
     * Return a rotated copy of the line end point around the origin.
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

        return new self($xr, $yr);
    }
}
