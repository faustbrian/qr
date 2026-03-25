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
 * Finder-eye preset with a pointed outer shell and circular inner element.
 *
 * The singleton keeps this preset allocation-free because its geometry never
 * changes. Renderers can reuse the same instance across many symbols.
 * @author Brian Faust <brian@cline.sh>
 */
final class PointyEye implements EyeInterface
{
    /** @var null|self */
    private static $instance;

    private function __construct() {}

    /**
     * Return the shared pointy-eye preset instance.
     */
    public static function instance(): self
    {
        return self::$instance ?: self::$instance = new self();
    }

    /**
     * Return the outer finder-eye path with one curved, pointed corner profile.
     */
    public function getExternalPath(): Path
    {
        return new Path()
            ->move(-3.5, 3.5)
            ->line(-3.5, 0)
            ->ellipticArc(3.5, 3.5, 0, false, true, 0, -3.5)
            ->line(3.5, -3.5)
            ->line(3.5, 3.5)
            ->close()
            ->move(2.5, 0)
            ->ellipticArc(2.5, 2.5, 0, false, true, 0, 2.5)
            ->ellipticArc(2.5, 2.5, 0, false, true, -2.5, 0)
            ->ellipticArc(2.5, 2.5, 0, false, true, 0, -2.5)
            ->ellipticArc(2.5, 2.5, 0, false, true, 2.5, 0)
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
