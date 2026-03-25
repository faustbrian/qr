<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Decoder;

/**
 * Null object for QR detection callbacks when the caller does not need them.
 * @psalm-immutable
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class NullResultPointCallback implements ResultPointCallback
{
    public function foundPossibleResultPoint(object $point): void
    {
        // Intentionally ignore intermediate detector points.
    }
}
