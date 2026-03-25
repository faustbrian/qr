<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Label\Margin;

/**
 * Contract for label margin values.
 *
 * Writers read these values when spacing the label away from the QR matrix and
 * the surrounding output canvas.
 *
 * @author Brian Faust <brian@cline.sh>
 */
interface MarginInterface
{
    /**
     * Return the top margin.
     */
    public function getTop(): int;

    /**
     * Return the right margin.
     */
    public function getRight(): int;

    /**
     * Return the bottom margin.
     */
    public function getBottom(): int;

    /**
     * Return the left margin.
     */
    public function getLeft(): int;

    /**
     * Export the margin values as a named array.
     *
     * @return array<string, int>
     */
    public function toArray(): array;
}
