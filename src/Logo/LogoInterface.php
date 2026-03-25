<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Logo;

/**
 * Contract for logo configuration consumed by writers and image-data helpers.
 *
 * The logo pipeline depends on the source path plus optional target dimensions
 * and punchout behavior, regardless of the concrete logo value object.
 *
 * @author Brian Faust <brian@cline.sh>
 */
interface LogoInterface
{
    /**
     * Return the local path or remote URL for the logo source.
     */
    public function getPath(): string;

    /**
     * Return the requested target width, if any.
     */
    public function getResizeToWidth(): ?int;

    /**
     * Return the requested target height, if any.
     */
    public function getResizeToHeight(): ?int;

    /**
     * Return whether the background under the logo should be punched out.
     */
    public function getPunchoutBackground(): bool;
}
