<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Generator\Renderer\Module;

use Cline\Qr\Generator\Encoder\ByteMatrix;
use Cline\Qr\Generator\Renderer\Module\EdgeIterator\EdgeIterator;
use Cline\Qr\Generator\Renderer\Path\Path;

use function count;

/**
 * Merge adjacent dark modules into one contour-based path.
 *
 * This is the default module renderer for square QR artwork. It traces each
 * connected region through the `EdgeIterator` and turns the simplified contour
 * points into a single closed polygon path.
 * @author Brian Faust <brian@cline.sh>
 */
final class SquareModule implements ModuleInterface
{
    private static ?SquareModule $instance = null;

    private function __construct() {}

    /**
     * Return the shared square-module renderer instance.
     */
    public static function instance(): self
    {
        return self::$instance ?: self::$instance = new self();
    }

    /**
     * Convert all connected module groups into closed polygon paths.
     */
    public function createPath(ByteMatrix $matrix): Path
    {
        $path = new Path();

        foreach (new EdgeIterator($matrix) as $edge) {
            $points = $edge->getSimplifiedPoints();
            $length = count($points);
            $path = $path->move($points[0][0], $points[0][1]);

            for ($i = 1; $i < $length; ++$i) {
                $path = $path->line($points[$i][0], $points[$i][1]);
            }

            $path = $path->close();
        }

        return $path;
    }
}
