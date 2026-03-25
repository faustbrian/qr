<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Generator\Renderer\Eye;

use Cline\Qr\Generator\Encoder\ByteMatrix;
use Cline\Qr\Generator\Renderer\Module\ModuleInterface;
use Cline\Qr\Generator\Renderer\Path\Path;

/**
 * Build finder-eye paths from a module renderer.
 *
 * Instead of hardcoding SVG-style path instructions, this implementation
 * constructs miniature byte matrices for the outer ring and inner square, then
 * delegates their contour generation to the configured module renderer.
 * @author Brian Faust <brian@cline.sh>
 */
final class ModuleEye implements EyeInterface
{
    public function __construct(
        private readonly ModuleInterface $module,
    ) {}

    /**
     * Create the outer 7x7 finder-eye path using the module renderer.
     */
    public function getExternalPath(): Path
    {
        $matrix = new ByteMatrix(7, 7);

        for ($x = 0; $x < 7; ++$x) {
            $matrix->set($x, 0, 1);
            $matrix->set($x, 6, 1);
        }

        for ($y = 1; $y < 6; ++$y) {
            $matrix->set(0, $y, 1);
            $matrix->set(6, $y, 1);
        }

        return $this->module->createPath($matrix)->translate(-3.5, -3.5);
    }

    /**
     * Create the inner 3x3 finder-eye path using the module renderer.
     */
    public function getInternalPath(): Path
    {
        $matrix = new ByteMatrix(3, 3);

        for ($x = 0; $x < 3; ++$x) {
            for ($y = 0; $y < 3; ++$y) {
                $matrix->set($x, $y, 1);
            }
        }

        return $this->module->createPath($matrix)->translate(-1.5, -1.5);
    }
}
