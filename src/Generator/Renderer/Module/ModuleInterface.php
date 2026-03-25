<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Generator\Renderer\Module;

use Cline\Qr\Generator\Encoder\ByteMatrix;
use Cline\Qr\Generator\Renderer\Path\Path;

/**
 * Contract for converting a module matrix into a vector path.
 *
 * Implementations receive a byte matrix containing `1` and `0` module values
 * and return a path whose origin `(0, 0)` matches the top-left corner of the
 * first matrix cell.
 * @author Brian Faust <brian@cline.sh>
 */
interface ModuleInterface
{
    /**
     * Convert the supplied module matrix into a vector path.
     */
    public function createPath(ByteMatrix $matrix): Path;
}
