<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Generator\Renderer\RendererStyle;

/**
 * Supported gradient directions for renderer fills.
 * @author Brian Faust <brian@cline.sh>
 */
enum GradientType
{
    case VERTICAL;
    case HORIZONTAL;
    case DIAGONAL;
    case INVERSE_DIAGONAL;
    case RADIAL;
}
