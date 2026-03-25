<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Generator\Renderer\Color;

/**
 * Decorate another renderer color with an explicit opacity percentage.
 *
 * The alpha wrapper preserves the underlying color model while carrying the
 * transparency information needed by backends that can emit semi-transparent
 * output. Conversion methods intentionally delegate to the base color because
 * alpha and channel-model conversion are orthogonal concerns in this layer.
 * @author Brian Faust <brian@cline.sh>
 */
final class Alpha implements ColorInterface
{
    private readonly Percentage $alpha;

    /**
     * @param int $alpha Opacity percentage from `0` to `100`.
     *
     * @throws \Cline\Qr\Generator\Internal\Exception\InvalidArgumentException if alpha is outside the valid
     *                                                                         percentage range
     */
    public function __construct(
        int $alpha,
        private readonly ColorInterface $baseColor,
    ) {
        $this->alpha = new Percentage($alpha);
    }

    /**
     * Return the opacity percentage carried by this wrapper.
     */
    public function getAlpha(): int
    {
        return $this->alpha->value();
    }

    /**
     * Return the underlying non-alpha color model.
     */
    public function getBaseColor(): ColorInterface
    {
        return $this->baseColor;
    }

    /**
     * Convert the wrapped color channels to RGB without altering alpha.
     */
    public function toRgb(): Rgb
    {
        return $this->baseColor->toRgb();
    }

    /**
     * Convert the wrapped color channels to CMYK without altering alpha.
     */
    public function toCmyk(): Cmyk
    {
        return $this->baseColor->toCmyk();
    }

    /**
     * Convert the wrapped color channels to grayscale without altering alpha.
     */
    public function toGray(): Gray
    {
        return $this->baseColor->toGray();
    }
}
