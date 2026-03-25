<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Generator\Renderer\Module;

use Cline\Qr\Generator\Encoder\ByteMatrix;
use Cline\Qr\Generator\Internal\Exception\InvalidArgumentException;
use Cline\Qr\Generator\Renderer\Module\EdgeIterator\EdgeIterator;
use Cline\Qr\Generator\Renderer\Path\Path;

use function count;

/**
 * Render merged module groups with rounded corners.
 *
 * This module first traces the contours of connected regions and then replaces
 * sharp corners with short arcs whose radius is derived from the configured
 * intensity.
 * @author Brian Faust <brian@cline.sh>
 */
final class RoundnessModule implements ModuleInterface
{
    public const int STRONG = 1;

    public const float MEDIUM = .5;

    public const float SOFT = .25;

    /**
     * @throws InvalidArgumentException if intensity is outside the `(0, 1]`
     *                                  range
     */
    public function __construct(
        private float $intensity,
    ) {
        if ($intensity <= 0 || $intensity > 1) {
            throw InvalidArgumentException::withMessage('Intensity must between 0 (exclusive) and 1 (inclusive)');
        }

        $this->intensity = $intensity / 2;
    }

    /**
     * Convert the matrix contours into a rounded vector path.
     */
    public function createPath(ByteMatrix $matrix): Path
    {
        $path = new Path();

        foreach (new EdgeIterator($matrix) as $edge) {
            $points = $edge->getSimplifiedPoints();
            $length = count($points);

            $currentPoint = $points[0];
            $nextPoint = $points[1];
            $horizontal = $currentPoint[1] === $nextPoint[1];

            if ($horizontal) {
                $right = $nextPoint[0] > $currentPoint[0];
                $path = $path->move(
                    $currentPoint[0] + ($right ? $this->intensity : -$this->intensity),
                    $currentPoint[1],
                );
            } else {
                $up = $nextPoint[0] < $currentPoint[0];
                $path = $path->move(
                    $currentPoint[0],
                    $currentPoint[1] + ($up ? -$this->intensity : $this->intensity),
                );
            }

            for ($i = 1; $i <= $length; ++$i) {
                if ($i === $length) {
                    $previousPoint = $points[$length - 1];
                    $currentPoint = $points[0];
                    $nextPoint = $points[1];
                } else {
                    $previousPoint = $points[(0 === $i ? $length : $i) - 1];
                    $currentPoint = $points[$i];
                    $nextPoint = $points[($length - 1 === $i ? -1 : $i) + 1];
                }

                $horizontal = $previousPoint[1] === $currentPoint[1];

                if ($horizontal) {
                    $right = $previousPoint[0] < $currentPoint[0];
                    $up = $nextPoint[1] < $currentPoint[1];
                    $sweep = ($up xor $right);

                    if ($this->intensity < 0.5
                        || ($right && $previousPoint[0] !== $currentPoint[0] - 1)
                        || (!$right && $previousPoint[0] - 1 !== $currentPoint[0])
                    ) {
                        $path = $path->line(
                            $currentPoint[0] + ($right ? -$this->intensity : $this->intensity),
                            $currentPoint[1],
                        );
                    }

                    $path = $path->ellipticArc(
                        $this->intensity,
                        $this->intensity,
                        0,
                        false,
                        $sweep,
                        $currentPoint[0],
                        $currentPoint[1] + ($up ? -$this->intensity : $this->intensity),
                    );
                } else {
                    $up = $previousPoint[1] > $currentPoint[1];
                    $right = $nextPoint[0] > $currentPoint[0];
                    $sweep = !($up xor $right);

                    if ($this->intensity < 0.5
                        || ($up && $previousPoint[1] !== $currentPoint[1] + 1)
                        || (!$up && $previousPoint[0] + 1 !== $currentPoint[0])
                    ) {
                        $path = $path->line(
                            $currentPoint[0],
                            $currentPoint[1] + ($up ? $this->intensity : -$this->intensity),
                        );
                    }

                    $path = $path->ellipticArc(
                        $this->intensity,
                        $this->intensity,
                        0,
                        false,
                        $sweep,
                        $currentPoint[0] + ($right ? $this->intensity : -$this->intensity),
                        $currentPoint[1],
                    );
                }
            }

            $path = $path->close();
        }

        return $path;
    }
}
