<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Generator\Renderer\RendererStyle;

use Cline\Qr\Generator\Renderer\Color\ColorInterface;

/**
 * Immutable foreground gradient description.
 *
 * Renderers use this value object to keep the gradient endpoints and direction
 * together while delegating the backend-specific fill implementation.
 * @author Brian Faust <brian@cline.sh>
 */
final class Gradient
{
    public function __construct(
        private readonly ColorInterface $startColor,
        private readonly ColorInterface $endColor,
        private readonly GradientType $type,
    ) {}

    /**
     * Return the start color for the gradient.
     */
    public function getStartColor(): ColorInterface
    {
        return $this->startColor;
    }

    /**
     * Return the end color for the gradient.
     */
    public function getEndColor(): ColorInterface
    {
        return $this->endColor;
    }

    /**
     * Return the gradient direction/type.
     */
    public function getType(): GradientType
    {
        return $this->type;
    }
}
