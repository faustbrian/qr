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

/**
 * Describes how one finder eye should inherit or override foreground colors.
 *
 * Each eye can either inherit the renderer's foreground fill or provide custom
 * external and internal colors. This lets callers theme finder eyes without
 * changing the rest of the module fill behavior.
 * @author Brian Faust <brian@cline.sh>
 */
final class EyeFill
{
    private static ?EyeFill $inherit = null;

    public function __construct(
        private readonly ?ColorInterface $externalColor,
        private readonly ?ColorInterface $internalColor,
    ) {}

    /**
     * Create an eye fill that uses the same explicit color for both eye parts.
     */
    public static function uniform(ColorInterface $color): self
    {
        return new self($color, $color);
    }

    /**
     * Return the shared eye fill that inherits both colors from the foreground.
     */
    public static function inherit(): self
    {
        return self::$inherit ?: self::$inherit = new self(null, null);
    }

    /**
     * Return whether both eye parts inherit the foreground fill.
     */
    public function inheritsBothColors(): bool
    {
        return null === $this->externalColor && null === $this->internalColor;
    }

    /**
     * Return whether the outer eye should inherit the foreground fill.
     */
    public function inheritsExternalColor(): bool
    {
        return null === $this->externalColor;
    }

    /**
     * Return whether the inner eye should inherit the foreground fill.
     */
    public function inheritsInternalColor(): bool
    {
        return null === $this->internalColor;
    }

    /**
     * Return the explicit outer-eye color.
     *
     * @throws RuntimeException when the outer eye inherits the foreground color
     */
    public function getExternalColor(): ColorInterface
    {
        if (null === $this->externalColor) {
            throw RuntimeException::withMessage('External eye color inherits foreground color');
        }

        return $this->externalColor;
    }

    /**
     * Return the explicit inner-eye color.
     *
     * @throws RuntimeException when the inner eye inherits the foreground color
     */
    public function getInternalColor(): ColorInterface
    {
        if (null === $this->internalColor) {
            throw RuntimeException::withMessage('Internal eye color inherits foreground color');
        }

        return $this->internalColor;
    }
}
