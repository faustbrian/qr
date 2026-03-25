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
 * Contract for finder-eye shape presets.
 *
 * The renderer treats each eye as two separate paths: the external ring and
 * the internal center element. Implementations define those shapes in a
 * normalized local coordinate system centered around the origin.
 * @author Brian Faust <brian@cline.sh>
 */
interface EyeInterface
{
    /**
     * Return the outer finder-eye path.
     *
     * The path origin point (0, 0) must be anchored at the middle of the path.
     */
    public function getExternalPath(): Path;

    /**
     * Return the inner finder-eye path.
     *
     * The path origin point (0, 0) must be anchored at the middle of the path.
     */
    public function getInternalPath(): Path;
}
