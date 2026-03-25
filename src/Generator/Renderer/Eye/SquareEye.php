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
 * Default finder-eye preset that keeps both outer and inner shapes square.
 *
 * This mirrors the standard QR finder artwork and is exposed as a singleton so
 * renderers can share one immutable preset instance.
 * @author Brian Faust <brian@cline.sh>
 */
final class SquareEye implements EyeInterface
{
    private static ?SquareEye $instance = null;

    private function __construct() {}

    /**
     * Return the shared square-eye preset instance.
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
     * Return the default square inner finder-eye path.
     */
    public function getInternalPath(): Path
    {
        return new Path()
            ->move(-1.5, -1.5)
            ->line(1.5, -1.5)
            ->line(1.5, 1.5)
            ->line(-1.5, 1.5)
            ->close();
    }
}
