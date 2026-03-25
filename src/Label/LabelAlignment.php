<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Label;

/**
 * Supported horizontal label alignment modes.
 *
 * Writers use these values to position the measured label text relative to the
 * QR symbol and the available output width.
 *
 * @author Brian Faust <brian@cline.sh>
 */
enum LabelAlignment: string
{
    case Center = 'center';
    case Left = 'left';
    case Right = 'right';
}
