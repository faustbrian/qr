<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Generator\Renderer\Color;

use Cline\Qr\Generator\Internal\Exception;
use Exception\InvalidArgumentException;

use function min;
use function round;

/**
 * RGB renderer color with validated 8-bit channels.
 *
 * Raster backends typically consume RGB directly, while vector backends can
 * convert the value on demand to CMYK or grayscale. Validation happens eagerly
 * so downstream rendering code can assume each channel is already normalized.
 * @author Brian Faust <brian@cline.sh>
 */
final class Rgb implements ColorInterface
{
    /**
     * @param int $red   Red channel from `0` to `255`
     * @param int $green Green channel from `0` to `255`
     * @param int $blue  Blue channel from `0` to `255`
     *
     * @throws Exception\InvalidArgumentException if any channel is outside the
     *                                            8-bit range
     */
    public function __construct(
        private readonly int $red,
        private readonly int $green,
        private readonly int $blue,
    ) {
        if ($red < 0 || $red > 255) {
            throw InvalidArgumentException::withMessage('Red must be between 0 and 255');
        }

        if ($green < 0 || $green > 255) {
            throw InvalidArgumentException::withMessage('Green must be between 0 and 255');
        }

        if ($blue < 0 || $blue > 255) {
            throw InvalidArgumentException::withMessage('Blue must be between 0 and 255');
        }
    }

    /**
     * Return the red channel.
     */
    public function getRed(): int
    {
        return $this->red;
    }

    /**
     * Return the green channel.
     */
    public function getGreen(): int
    {
        return $this->green;
    }

    /**
     * Return the blue channel.
     */
    public function getBlue(): int
    {
        return $this->blue;
    }

    /**
     * Return the current value because it is already RGB.
     */
    public function toRgb(): self
    {
        return $this;
    }

    /**
     * Convert the RGB channels into CMYK percentages.
     *
     * Pure black is handled explicitly to avoid dividing by zero in the normal
     * conversion formula.
     */
    public function toCmyk(): Cmyk
    {
        // avoid division by zero with input rgb(0,0,0), by handling it as a specific case
        if (0 === $this->red && 0 === $this->green && 0 === $this->blue) {
            return new Cmyk(0, 0, 0, 100);
        }

        $c = 1 - ($this->red / 255);
        $m = 1 - ($this->green / 255);
        $y = 1 - ($this->blue / 255);
        $k = min($c, $m, $y);

        return new Cmyk(
            (int) round(100 * ($c - $k) / (1 - $k)),
            (int) round(100 * ($m - $k) / (1 - $k)),
            (int) round(100 * ($y - $k) / (1 - $k)),
            (int) round(100 * $k),
        );
    }

    /**
     * Convert the RGB channels into a luminance-weighted grayscale value.
     */
    public function toGray(): Gray
    {
        // use integer-based calculation to avoid floating-point precision loss
        return new Gray((int) round(($this->red * 2_126 + $this->green * 7_152 + $this->blue * 722) / 25_500));
    }
}
