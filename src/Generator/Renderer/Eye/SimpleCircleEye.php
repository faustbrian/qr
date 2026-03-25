<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Generator\Renderer\Eye;

use Cline\Qr\Generator\Renderer\Path\Path;

/**
 * Finder-eye preset with a square outer ring and circular inner element.
 *
 * This preset keeps the default square finder boundary while softening the
 * center point. It is exposed as a singleton because the geometry is static.
 * @author Brian Faust <brian@cline.sh>
 */
final class SimpleCircleEye implements EyeInterface
{
    private static ?SimpleCircleEye $instance = null;

    private function __construct() {}

    /**
     * Return the shared simple-circle eye preset instance.
     */
    public static function instance(): self
    {
        return self::$instance ?: self::$instance = new self();
    }

    /**
     * Return the default square outer finder-eye path.
     */
    public function getExternalPath(): Path
    {
        return new Path()
            ->move(-3.5, -3.5)
            ->line(3.5, -3.5)
            ->line(3.5, 3.5)
            ->line(-3.5, 3.5)
            ->close()
            ->move(-2.5, -2.5)
            ->line(-2.5, 2.5)
            ->line(2.5, 2.5)
            ->line(2.5, -2.5)
            ->close();
    }

    /**
     * Return the circular inner finder-eye path.
     */
    public function getInternalPath(): Path
    {
        return new Path()
            ->move(1.5, 0)
            ->ellipticArc(1.5, 1.5, 0., false, true, 0., 1.5)
            ->ellipticArc(1.5, 1.5, 0., false, true, -1.5, 0.)
            ->ellipticArc(1.5, 1.5, 0., false, true, 0., -1.5)
            ->ellipticArc(1.5, 1.5, 0., false, true, 1.5, 0.)
            ->close();
    }
}
