<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Label\Font;

/**
 * Contract for label font configuration.
 *
 * Writer code depends only on the font path and size, which keeps label
 * measurement and rendering decoupled from the concrete font value object.
 *
 * @author Brian Faust <brian@cline.sh>
 */
interface FontInterface
{
    /**
     * Return the path to the font file.
     */
    public function getPath(): string;

    /**
     * Return the configured font size.
     */
    public function getSize(): int;
}
