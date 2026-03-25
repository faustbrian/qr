<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Generator\Internal\Exception;

use RuntimeException;
use Throwable;

/**
 * Raised when the encoder cannot produce a valid QR payload or matrix.
 *
 * This is the primary failure type for oversized payloads, invalid version
 * requests, and other generation-time problems that should usually be surfaced
 * to callers as user-correctable configuration errors.
 * @author Brian Faust <brian@cline.sh>
 */
final class WriterException extends RuntimeException implements ExceptionInterface
{
    public static function withMessage(string $message, ?Throwable $previous = null): self
    {
        return new self($message, previous: $previous);
    }
}
