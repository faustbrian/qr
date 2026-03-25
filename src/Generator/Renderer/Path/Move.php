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
 * Move-to path operation that starts a new subpath without drawing.
 * @author Brian Faust <brian@cline.sh>
 */
final class Move implements OperationInterface
{
    public function __construct(
        private readonly float $x,
        private readonly float $y,
    ) {}

    /**
     * Return the destination x coordinate.
     */
    public function getX(): float
    {
        return $this->x;
    }

    /**
     * Return the destination y coordinate.
     */
    public function getY(): float
    {
        return $this->y;
    }

    /**
     * Return a translated copy of the move operation.
     *
     * @return self
     */
    public function translate(float $x, float $y): OperationInterface
    {
        return new self($this->x + $x, $this->y + $y);
    }

    /**
     * Return a rotated copy of the move operation around the origin.
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
