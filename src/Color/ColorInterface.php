<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Color;

/**
 * Canonical color contract used by renderers and style objects.
 *
 * The interface keeps callers detached from the concrete color implementation
 * while still exposing the exact channel and export semantics the writers need.
 *
 * @author Brian Faust <brian@cline.sh>
 */
interface ColorInterface
{
    /**
     * Return the red channel on the package's normalized 0-255 scale.
     *
     * @return int<0, 255>
     */
    public function getRed(): int;

    /**
     * Return the green channel on the package's normalized 0-255 scale.
     *
     * @return int<0, 255>
     */
    public function getGreen(): int;

    /**
     * Return the blue channel on the package's normalized 0-255 scale.
     *
     * @return int<0, 255>
     */
    public function getBlue(): int;

    /**
     * Return the GD alpha channel used by the rendering backends.
     *
     * @return int<0, 127>
     */
    public function getAlpha(): int;

    /**
     * Return the alpha channel converted into an opacity fraction.
     */
    public function getOpacity(): float;

    /**
     * Return the color in hexadecimal RGB form for presentation or debugging.
     */
    public function getHex(): string;

    /**
     * Export the channel values for serialization and array-based consumers.
     *
     * @return array<string, int>
     */
    public function toArray(): array;
}
