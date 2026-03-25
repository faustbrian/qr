<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Generator\Renderer\RendererStyle;

use Cline\Qr\Generator\Renderer\Eye\EyeInterface;
use Cline\Qr\Generator\Renderer\Eye\ModuleEye;
use Cline\Qr\Generator\Renderer\Module\ModuleInterface;
use Cline\Qr\Generator\Renderer\Module\SquareModule;

/**
 * Aggregate of all renderer-level styling decisions.
 *
 * This object ties together output size, quiet-zone margin, module renderer,
 * eye renderer, and fill selection so a renderer can apply one coherent visual
 * style to an encoded QR matrix.
 * @author Brian Faust <brian@cline.sh>
 */
final class RendererStyle
{
    private ModuleInterface $module;

    private ?EyeInterface $eye;

    private Fill $fill;

    public function __construct(
        private readonly int $size,
        private readonly int $margin = 4,
        ?ModuleInterface $module = null,
        ?EyeInterface $eye = null,
        ?Fill $fill = null,
    ) {
        $this->module = $module ?: SquareModule::instance();
        $this->eye = $eye ?: new ModuleEye($this->module);
        $this->fill = $fill ?: Fill::default();
    }

    /**
     * Return a cloned style with a different output size.
     */
    public function withSize(int $size): self
    {
        $style = clone $this;
        $style->size = $size;

        return $style;
    }

    /**
     * Return a cloned style with a different quiet-zone margin.
     */
    public function withMargin(int $margin): self
    {
        $style = clone $this;
        $style->margin = $margin;

        return $style;
    }

    /**
     * Return the output size in pixels or units.
     */
    public function getSize(): int
    {
        return $this->size;
    }

    /**
     * Return the quiet-zone margin in module units.
     */
    public function getMargin(): int
    {
        return $this->margin;
    }

    /**
     * Return the module renderer used for the payload area.
     */
    public function getModule(): ModuleInterface
    {
        return $this->module;
    }

    /**
     * Return the eye renderer used for finder patterns.
     */
    public function getEye(): EyeInterface
    {
        return $this->eye;
    }

    /**
     * Return the fill configuration for background, modules, and eyes.
     */
    public function getFill(): Fill
    {
        return $this->fill;
    }
}
