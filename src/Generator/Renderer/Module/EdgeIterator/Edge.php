<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Generator\Renderer\Module\EdgeIterator;

use const PHP_INT_MAX;

use function count;
use function max;
use function min;

/**
 * One traced contour extracted from a module matrix.
 *
 * The edge stores the raw contour points discovered by the iterator and lazily
 * derives a simplified point list that removes collinear segments before the
 * path renderer consumes it.
 * @author Brian Faust <brian@cline.sh>
 */
final class Edge
{
    /** @var array<array<int>> */
    private array $points = [];

    /** @var null|array<array<int>> */
    private ?array $simplifiedPoints = null;

    private int $minX = PHP_INT_MAX;

    private int $minY = PHP_INT_MAX;

    private int $maxX = -1;

    private int $maxY = -1;

    public function __construct(
        private readonly bool $positive,
    ) {}

    /**
     * Append a contour point and update the cached bounds.
     */
    public function addPoint(int $x, int $y): void
    {
        $this->points[] = [$x, $y];
        $this->minX = min($this->minX, $x);
        $this->minY = min($this->minY, $y);
        $this->maxX = max($this->maxX, $x);
        $this->maxY = max($this->maxY, $y);
    }

    /**
     * Return whether this contour encloses a positive-filled region.
     */
    public function isPositive(): bool
    {
        return $this->positive;
    }

    /**
     * Return the raw traced contour points in traversal order.
     *
     * @return array<array<int>>
     */
    public function getPoints(): array
    {
        return $this->points;
    }

    /**
     * Return the largest x-coordinate encountered while tracing this edge.
     */
    public function getMaxX(): int
    {
        return $this->maxX;
    }

    /**
     * Return a simplified contour with collinear points removed.
     *
     * The simplified list is cached because contour-based module renderers may
     * inspect it multiple times while building rounded paths.
     */
    public function getSimplifiedPoints(): array
    {
        if (null !== $this->simplifiedPoints) {
            return $this->simplifiedPoints;
        }

        $points = [];
        $length = count($this->points);

        for ($i = 0; $i < $length; ++$i) {
            $previousPoint = $this->points[(0 === $i ? $length : $i) - 1];
            $nextPoint = $this->points[($length - 1 === $i ? -1 : $i) + 1];
            $currentPoint = $this->points[$i];

            if (($previousPoint[0] === $currentPoint[0] && $currentPoint[0] === $nextPoint[0])
                || ($previousPoint[1] === $currentPoint[1] && $currentPoint[1] === $nextPoint[1])
            ) {
                continue;
            }

            $points[] = $currentPoint;
        }

        return $this->simplifiedPoints = $points;
    }
}
