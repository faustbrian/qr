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
 * CMYK renderer color value with normalized percentage channels.
 *
 * This representation is useful for vector backends such as PDF or EPS that
 * can emit native CMYK instructions without first converting through RGB. The
 * class validates every channel eagerly so later rendering code can assume a
 * legal percentage range.
 * @author Brian Faust <brian@cline.sh>
 */
final class Cmyk implements ColorInterface
{
    /**
     * @param int $cyan    Cyan percentage from `0` to `100`
     * @param int $magenta Magenta percentage from `0` to `100`
     * @param int $yellow  Yellow percentage from `0` to `100`
     * @param int $black   Black percentage from `0` to `100`
     *
     * @throws Exception\InvalidArgumentException if any channel falls outside
     *                                            the supported percentage range
     */
    public function __construct(
        private readonly int $cyan,
        private readonly int $magenta,
        private readonly int $yellow,
        private readonly int $black,
    ) {
        if ($cyan < 0 || $cyan > 100) {
            throw InvalidArgumentException::withMessage('Cyan must be between 0 and 100');
        }

        if ($magenta < 0 || $magenta > 100) {
            throw InvalidArgumentException::withMessage('Magenta must be between 0 and 100');
        }

        if ($yellow < 0 || $yellow > 100) {
            throw InvalidArgumentException::withMessage('Yellow must be between 0 and 100');
        }

        if ($black < 0 || $black > 100) {
            throw InvalidArgumentException::withMessage('Black must be between 0 and 100');
        }
    }

    /**
     * Return the cyan channel percentage.
     */
    public function getCyan(): int
    {
        return $this->cyan;
    }

    /**
     * Return the magenta channel percentage.
     */
    public function getMagenta(): int
    {
        return $this->magenta;
    }

    /**
     * Return the yellow channel percentage.
     */
    public function getYellow(): int
    {
        return $this->yellow;
    }

    /**
     * Return the black channel percentage.
     */
    public function getBlack(): int
    {
        return $this->black;
    }

    /**
     * Convert the CMYK percentages into an RGB color.
     *
     * This is primarily used by raster backends and other paths that standardize
     * on RGB before painting modules.
     */
    public function toRgb(): Rgb
    {
        $c = $this->cyan / 100;
        $m = $this->magenta / 100;
        $y = $this->yellow / 100;
        $k = $this->black / 100;

        return new Rgb(
            (int) round(255 * (1 - $c) * (1 - $k)),
            (int) round(255 * (1 - $m) * (1 - $k)),
            (int) round(255 * (1 - $y) * (1 - $k)),
        );
    }

    /**
     * Return the current value because it is already expressed in CMYK.
     */
    public function toCmyk(): self
    {
        return $this;
    }

    /**
     * Convert the current color to grayscale through the RGB representation.
     */
    public function toGray(): Gray
    {
        return $this->toRgb()->toGray();
    }
}
