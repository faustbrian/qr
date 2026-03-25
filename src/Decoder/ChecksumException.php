<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Decoder;

/**
 * Exception used when checksum validation fails after a candidate has decoded.
 *
 * The decoder distinguishes this failure from detection and format failures so
 * callers can decide whether to retry, fall back to another image, or surface a
 * more specific error to users. The class also preserves the decoder's reusable
 * singleton pattern when stack traces are disabled.
 *
 * @author Sean Owen
 */
final class ChecksumException extends AbstractReaderException
{
    private static ?ChecksumException $instance = null;

    /**
     * Return a checksum exception instance, reusing a cached singleton when
     * stack traces are disabled.
     *
     * This mirrors the decoder's low-allocation failure path: callers receive a fresh
     * exception only when stack traces are explicitly enabled for diagnostics.
     * @param mixed $cause
     */
    public static function getChecksumInstance($cause = ''): self
    {
        if (self::$isStackTrace) {
            return new self($cause);
        }

        if (!self::$instance) {
            self::$instance = new self($cause);
        }

        return self::$instance;
    }
}
