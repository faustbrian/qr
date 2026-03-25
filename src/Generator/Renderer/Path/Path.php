<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Generator\Renderer\Path;

use IteratorAggregate;
use Traversable;

use function array_merge;

/**
 * Immutable sequence of vector path operations.
 *
 * Renderers and module generators build up paths functionally: each mutator
 * returns a cloned path with one extra operation, which keeps preset shapes and
 * reused subpaths safe from accidental mutation.
 * @author Brian Faust <brian@cline.sh>
 */
final class Path implements IteratorAggregate
{
    /** @var array<OperationInterface> */
    private array $operations = [];

    /**
     * Return a new path with a move-to operation appended.
     */
    public function move(float $x, float $y): self
    {
        $path = clone $this;
        $path->operations[] = new Move($x, $y);

        return $path;
    }

    /**
     * Return a new path with a line-to operation appended.
     */
    public function line(float $x, float $y): self
    {
        $path = clone $this;
        $path->operations[] = new Line($x, $y);

        return $path;
    }

    /**
     * Return a new path with an elliptic-arc operation appended.
     */
    public function ellipticArc(
        float $xRadius,
        float $yRadius,
        float $xAxisRotation,
        bool $largeArc,
        bool $sweep,
        float $x,
        float $y,
    ): self {
        $path = clone $this;
        $path->operations[] = new EllipticArc($xRadius, $yRadius, $xAxisRotation, $largeArc, $sweep, $x, $y);

        return $path;
    }

    /**
     * Return a new path with a cubic-curve operation appended.
     */
    public function curve(float $x1, float $y1, float $x2, float $y2, float $x3, float $y3): self
    {
        $path = clone $this;
        $path->operations[] = new Curve($x1, $y1, $x2, $y2, $x3, $y3);

        return $path;
    }

    /**
     * Return a new path with a close-path operation appended.
     */
    public function close(): self
    {
        $path = clone $this;
        $path->operations[] = Close::instance();

        return $path;
    }

    /**
     * Return a new path containing this path followed by another path.
     */
    public function append(self $other): self
    {
        $path = clone $this;
        $path->operations = array_merge($this->operations, $other->operations);

        return $path;
    }

    /**
     * Return a translated copy of every operation in the path.
     */
    public function translate(float $x, float $y): self
    {
        $path = new self();

        foreach ($this->operations as $operation) {
            $path->operations[] = $operation->translate($x, $y);
        }

        return $path;
    }

    /**
     * Return a rotated copy of every operation in the path.
     */
    public function rotate(int $degrees): self
    {
        $path = new self();

        foreach ($this->operations as $operation) {
            $path->operations[] = $operation->rotate($degrees);
        }

        return $path;
    }

    /**
     * Iterate over the path operations in order.
     *
     * @return Traversable<int, OperationInterface>
     */
    public function getIterator(): Traversable
    {
        foreach ($this->operations as $operation) {
            yield $operation;
        }
    }
}
