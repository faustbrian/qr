<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr;

/**
 * Strategies for reconciling requested image size with discrete QR block sizes.
 *
 * Because QR matrices are built from whole modules, the package may need to
 * enlarge, shrink, or absorb the remainder into the margin when the requested
 * size does not divide evenly by the block count.
 *
 * @author Brian Faust <brian@cline.sh>
 */
enum RoundBlockSizeMode: string
{
    case Enlarge = 'enlarge';
    case Margin = 'margin';
    case None = 'none';
    case Shrink = 'shrink';
}
