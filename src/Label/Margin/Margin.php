<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Label\Margin;

/**
 * Immutable four-sided margin value object for labels.
 *
 * Writers use this object when reserving space around the rendered label so
 * text placement stays explicit and backend-independent.
 *
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class Margin implements MarginInterface
{
    public function __construct(
        private int $top,
        private int $right,
        private int $bottom,
        private int $left,
    ) {}

    /**
     * Return the top margin.
     */
    public function getTop(): int
    {
        return $this->top;
    }

    public function withTop(int $top): self
    {
        return new self(
            top: $top,
            right: $this->right,
            bottom: $this->bottom,
            left: $this->left,
        );
    }

    /**
     * Return the right margin.
     */
    public function getRight(): int
    {
        return $this->right;
    }

    public function withRight(int $right): self
    {
        return new self(
            top: $this->top,
            right: $right,
            bottom: $this->bottom,
            left: $this->left,
        );
    }

    /**
     * Return the bottom margin.
     */
    public function getBottom(): int
    {
        return $this->bottom;
    }

    public function withBottom(int $bottom): self
    {
        return new self(
            top: $this->top,
            right: $this->right,
            bottom: $bottom,
            left: $this->left,
        );
    }

    /**
     * Return the left margin.
     */
    public function getLeft(): int
    {
        return $this->left;
    }

    public function withLeft(int $left): self
    {
        return new self(
            top: $this->top,
            right: $this->right,
            bottom: $this->bottom,
            left: $left,
        );
    }

    /**
     * Export the margin values as a named array.
     *
     * @return array<string, int>
     */
    public function toArray(): array
    {
        return [
            'top' => $this->top,
            'right' => $this->right,
            'bottom' => $this->bottom,
            'left' => $this->left,
        ];
    }
}
