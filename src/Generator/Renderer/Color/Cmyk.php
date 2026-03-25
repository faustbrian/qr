<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Generator\Renderer\Color;

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
    private readonly Percentage $cyan;

    private readonly Percentage $magenta;

    private readonly Percentage $yellow;

    private readonly Percentage $black;

    /**
     * @param int $cyan    Cyan percentage from `0` to `100`
     * @param int $magenta Magenta percentage from `0` to `100`
     * @param int $yellow  Yellow percentage from `0` to `100`
     * @param int $black   Black percentage from `0` to `100`
     *
     * @throws \Cline\Qr\Generator\Internal\Exception\InvalidArgumentException if any channel falls outside
     *                                                                         the supported percentage range
     */
    public function __construct(
        int $cyan,
        int $magenta,
        int $yellow,
        int $black,
    ) {
        $this->cyan = new Percentage($cyan);
        $this->magenta = new Percentage($magenta);
        $this->yellow = new Percentage($yellow);
        $this->black = new Percentage($black);
    }

    /**
     * Return the cyan channel percentage.
     */
    public function getCyan(): int
    {
        return $this->cyan->value();
    }

    /**
     * Return the magenta channel percentage.
     */
    public function getMagenta(): int
    {
        return $this->magenta->value();
    }

    /**
     * Return the yellow channel percentage.
     */
    public function getYellow(): int
    {
        return $this->yellow->value();
    }

    /**
     * Return the black channel percentage.
     */
    public function getBlack(): int
    {
        return $this->black->value();
    }

    /**
     * Convert the CMYK percentages into an RGB color.
     *
     * This is primarily used by raster backends and other paths that standardize
     * on RGB before painting modules.
     */
    public function toRgb(): Rgb
    {
        $c = $this->cyan->asFraction();
        $m = $this->magenta->asFraction();
        $y = $this->yellow->asFraction();
        $k = $this->black->asFraction();

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
