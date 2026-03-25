<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Exception;

use Exception;

/**
 * Thrown when a requested QR block size cannot be rendered safely.
 *
 * The exception protects renderers from producing unreadable output when the
 * caller requests a block size that is smaller than the package's supported
 * minimum for the current symbol or styling configuration.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class BlockSizeTooSmallException extends Exception implements QrExceptionInterface
{
    public static function dueToDataDensity(): self
    {
        return new self(
            'Too much data: increase image dimensions or lower error correction level',
        );
    }
}
