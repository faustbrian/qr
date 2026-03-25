<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Generator\Renderer\Image;

use Cline\Qr\Generator\Internal\Exception\RuntimeException;
use Cline\Qr\Generator\Renderer\Color\ColorInterface;
use Cline\Qr\Generator\Renderer\Path\Path;
use Cline\Qr\Generator\Renderer\RendererStyle\Gradient;

/**
 * Contract for path-oriented image back ends.
 *
 * Renderers hand normalized vector paths to these back ends after they have
 * already decided module shapes, eye styling, and fills. Implementations are
 * responsible for tracking transforms, applying flat colors or gradients, and
 * returning the final serialized image blob.
 * @author Brian Faust <brian@cline.sh>
 */
interface ImageBackEndInterface
{
    /**
     * Start a new image canvas.
     *
     * Calling this resets any previously accumulated document state.
     */
    public function new(int $size, ColorInterface $backgroundColor): void;

    /**
     * Scale all subsequent drawing operations by the supplied factor.
     *
     * @throws RuntimeException if no image was started yet.
     */
    public function scale(float $size): void;

    /**
     * Translate all subsequent drawing operations by the supplied offset.
     *
     * @throws RuntimeException if no image was started yet.
     */
    public function translate(float $x, float $y): void;

    /**
     * Rotate all subsequent drawing operations by the supplied number of degrees.
     *
     * @throws RuntimeException if no image was started yet.
     */
    public function rotate(int $degrees): void;

    /**
     * Push the current transform state so temporary eye-local transforms can be
     * applied and later restored.
     *
     * @throws RuntimeException if no image was started yet.
     */
    public function push(): void;

    /**
     * Restore the previously pushed transform state.
     *
     * @throws RuntimeException if no image was started yet.
     */
    public function pop(): void;

    /**
     * Fill the supplied path with a flat color.
     *
     * @throws RuntimeException if no image was started yet.
     */
    public function drawPathWithColor(Path $path, ColorInterface $color): void;

    /**
     * Fill the supplied path with a gradient spanning the given bounding box.
     *
     * @throws RuntimeException if no image was started yet.
     */
    public function drawPathWithGradient(
        Path $path,
        Gradient $gradient,
        float $x,
        float $y,
        float $width,
        float $height,
    ): void;

    /**
     * Finalize the image and return its serialized bytes.
     *
     * Implementations are expected to reset their internal state afterward, so
     * callers should treat this as a one-shot finalization step per image.
     *
     * @throws RuntimeException if no image was started yet.
     */
    public function done(): string;
}
