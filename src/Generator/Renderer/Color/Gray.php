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

use function round;

/**
 * Grayscale renderer color expressed as a percentage from black to white.
 *
 * This value object is useful for formats that can emit native grayscale and
 * for gradients that want a neutral midpoint without carrying full RGB or CMYK
 * channel data.
 * @author Brian Faust <brian@cline.sh>
 */
final class Gray implements ColorInterface
{
    /**
     * @param int $gray Grayscale percentage between `0` (black) and `100`
     *                  (white)
     *
     * @throws Exception\InvalidArgumentException if the gray value is outside
     *                                            the supported percentage range
     */
    public function __construct(
        private readonly int $gray,
    ) {
        if ($gray < 0 || $gray > 100) {
            throw InvalidArgumentException::withMessage('Gray must be between 0 and 100');
        }
    }

    /**
     * Return the grayscale percentage.
     */
    public function getGray(): int
    {
        return $this->gray;
    }

    /**
     * Convert the grayscale value into an RGB color with equal channels.
     *
     * The conversion uses integer math to avoid precision drift for values such
     * as `100`, which should stay exactly white.
     */
    public function toRgb(): Rgb
    {
        // use 255/100 instead of 2.55 to avoid floating-point precision loss (100 * 2.55 = 254.999...)
        $value = (int) round($this->gray * 255 / 100);

        return new Rgb($value, $value, $value);
    }

    /**
     * Convert the grayscale value into a CMYK representation.
     */
    public function toCmyk(): Cmyk
    {
        return new Cmyk(0, 0, 0, 100 - $this->gray);
    }

    /**
     * Return the current instance because it is already grayscale.
     */
    public function toGray(): self
    {
        return $this;
    }
}
