<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Generator\Renderer\Color;

/**
 * Shared contract for renderer color models.
 *
 * Writer backends can choose the most natural color space for their output,
 * but they all need a reliable way to convert between RGB, CMYK, and grayscale
 * representations without knowing the concrete source type.
 * @author Brian Faust <brian@cline.sh>
 */
interface ColorInterface
{
    /**
     * Convert the color to RGB for raster-oriented backends.
     */
    public function toRgb(): Rgb;

    /**
     * Convert the color to CMYK for vector-oriented backends.
     */
    public function toCmyk(): Cmyk;

    /**
     * Convert the color to a grayscale representation.
     */
    public function toGray(): Gray;
}
