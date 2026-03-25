<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Generator\Renderer\Module;

use Cline\Qr\Generator\Encoder\ByteMatrix;
use Cline\Qr\Generator\Internal\Exception\InvalidArgumentException;
use Cline\Qr\Generator\Renderer\Path\Path;

/**
 * Render each active module as an isolated circular dot.
 *
 * Unlike contour-based module renderers, this implementation does not merge
 * adjacent modules. It generates one closed circular path per active cell,
 * which is useful for soft, decorative QR styles.
 * @author Brian Faust <brian@cline.sh>
 */
final class DotsModule implements ModuleInterface
{
    public const int LARGE = 1;

    public const float MEDIUM = .8;

    public const float SMALL = .6;

    /**
     * @throws InvalidArgumentException if size is outside the `(0, 1]` range
     */
    public function __construct(
        private readonly float $size,
    ) {
        if ($size <= 0 || $size > 1) {
            throw InvalidArgumentException::withMessage('Size must between 0 (exclusive) and 1 (inclusive)');
        }
    }

    /**
     * Create a path containing one dot for every active module in the matrix.
     */
    public function createPath(ByteMatrix $matrix): Path
    {
        $width = $matrix->getWidth();
        $height = $matrix->getHeight();
        $path = new Path();
        $halfSize = $this->size / 2;
        $margin = (1 - $this->size) / 2;

        for ($y = 0; $y < $height; ++$y) {
            for ($x = 0; $x < $width; ++$x) {
                if (!$matrix->get($x, $y)) {
                    continue;
                }

                $pathX = $x + $margin;
                $pathY = $y + $margin;

                $path = $path
                    ->move($pathX + $this->size, $pathY + $halfSize)
                    ->ellipticArc($halfSize, $halfSize, 0, false, true, $pathX + $halfSize, $pathY + $this->size)
                    ->ellipticArc($halfSize, $halfSize, 0, false, true, $pathX, $pathY + $halfSize)
                    ->ellipticArc($halfSize, $halfSize, 0, false, true, $pathX + $halfSize, $pathY)
                    ->ellipticArc($halfSize, $halfSize, 0, false, true, $pathX + $this->size, $pathY + $halfSize)
                    ->close();
            }
        }

        return $path;
    }
}
