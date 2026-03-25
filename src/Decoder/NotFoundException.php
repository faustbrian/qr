<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Decoder;

/**
 * Exception raised when the decoder cannot confirm a barcode location.
 *
 * The implementation keeps a cached instance because not-found failures are
 * common and usually do not need a unique stack trace or payload.
 *
 * @author Sean Owen
 */
final class NotFoundException extends AbstractReaderException
{
    private static ?NotFoundException $instance = null;

    /**
     * Return the shared not-found sentinel.
     *
     * The first message provided wins because the instance is cached and reused
     * across repeated decode failures.
     *
     * @param  string $message Optional diagnostic message.
     * @return self   Shared exception instance.
     */
    public static function getNotFoundInstance(string $message = ''): self
    {
        if (!self::$instance) {
            self::$instance = new self($message);
        }

        return self::$instance;
    }
}
