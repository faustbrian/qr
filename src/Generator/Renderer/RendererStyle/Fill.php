<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Generator\Renderer\RendererStyle;

use Cline\Qr\Generator\Internal\Exception\RuntimeException;
use Cline\Qr\Generator\Renderer\Color\ColorInterface;
use Cline\Qr\Generator\Renderer\Color\Gray;

/**
 * Aggregate of background, foreground, gradient, and eye-fill styling.
 *
 * Renderers consume this value object to decide whether modules are painted
 * with a flat color or gradient and whether finder eyes inherit those colors or
 * override them individually.
 * @author Brian Faust <brian@cline.sh>
 */
final class Fill
{
    private static ?Fill $default = null;

    private function __construct(
        private readonly ColorInterface $backgroundColor,
        private readonly ?ColorInterface $foregroundColor,
        private readonly ?Gradient $foregroundGradient,
        private readonly EyeFill $topLeftEyeFill,
        private readonly EyeFill $topRightEyeFill,
        private readonly EyeFill $bottomLeftEyeFill,
    ) {}

    /**
     * Return the shared default fill used by the package.
     */
    public static function default(): self
    {
        return self::$default ?: self::$default = self::uniformColor(
            new Gray(100),
            new Gray(0),
        );
    }

    /**
     * Create a fill that paints modules with a flat foreground color.
     */
    public static function withForegroundColor(
        ColorInterface $backgroundColor,
        ColorInterface $foregroundColor,
        EyeFill $topLeftEyeFill,
        EyeFill $topRightEyeFill,
        EyeFill $bottomLeftEyeFill,
    ): self {
        return new self(
            $backgroundColor,
            $foregroundColor,
            null,
            $topLeftEyeFill,
            $topRightEyeFill,
            $bottomLeftEyeFill,
        );
    }

    /**
     * Create a fill that paints modules with a foreground gradient.
     */
    public static function withForegroundGradient(
        ColorInterface $backgroundColor,
        Gradient $foregroundGradient,
        EyeFill $topLeftEyeFill,
        EyeFill $topRightEyeFill,
        EyeFill $bottomLeftEyeFill,
    ): self {
        return new self(
            $backgroundColor,
            null,
            $foregroundGradient,
            $topLeftEyeFill,
            $topRightEyeFill,
            $bottomLeftEyeFill,
        );
    }

    /**
     * Create a fill with uniform background and foreground colors and default
     * eye inheritance.
     */
    public static function uniformColor(ColorInterface $backgroundColor, ColorInterface $foregroundColor): self
    {
        return new self(
            $backgroundColor,
            $foregroundColor,
            null,
            EyeFill::inherit(),
            EyeFill::inherit(),
            EyeFill::inherit(),
        );
    }

    /**
     * Create a fill with a uniform background and shared foreground gradient.
     */
    public static function uniformGradient(ColorInterface $backgroundColor, Gradient $foregroundGradient): self
    {
        return new self(
            $backgroundColor,
            null,
            $foregroundGradient,
            EyeFill::inherit(),
            EyeFill::inherit(),
            EyeFill::inherit(),
        );
    }

    /**
     * Return whether the foreground is expressed as a gradient.
     */
    public function hasGradientFill(): bool
    {
        return null !== $this->foregroundGradient;
    }

    /**
     * Return the background color.
     */
    public function getBackgroundColor(): ColorInterface
    {
        return $this->backgroundColor;
    }

    /**
     * Return the flat foreground color.
     *
     * @throws RuntimeException when the fill uses a gradient instead
     */
    public function getForegroundColor(): ColorInterface
    {
        if (null === $this->foregroundColor) {
            throw RuntimeException::withMessage('Fill uses a gradient, thus no foreground color is available');
        }

        return $this->foregroundColor;
    }

    /**
     * Return the foreground gradient.
     *
     * @throws RuntimeException when the fill uses a flat color instead
     */
    public function getForegroundGradient(): Gradient
    {
        if (null === $this->foregroundGradient) {
            throw RuntimeException::withMessage('Fill uses a single color, thus no foreground gradient is available');
        }

        return $this->foregroundGradient;
    }

    /**
     * Return the styling for the top-left finder eye.
     */
    public function getTopLeftEyeFill(): EyeFill
    {
        return $this->topLeftEyeFill;
    }

    /**
     * Return the styling for the top-right finder eye.
     */
    public function getTopRightEyeFill(): EyeFill
    {
        return $this->topRightEyeFill;
    }

    /**
     * Return the styling for the bottom-left finder eye.
     */
    public function getBottomLeftEyeFill(): EyeFill
    {
        return $this->bottomLeftEyeFill;
    }
}
