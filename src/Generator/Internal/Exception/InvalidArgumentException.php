<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Generator\Internal\Exception;

use Throwable;

/**
 * Raised when caller-supplied input violates generator preconditions.
 *
 * This exception is used for invalid dimensions, unsupported color channel
 * values, and other argument-level contract violations detected before the
 * encoder or renderer can proceed safely.
 * @author Brian Faust <brian@cline.sh>
 */
final class InvalidArgumentException extends \InvalidArgumentException implements ExceptionInterface
{
    public static function withMessage(string $message, ?Throwable $previous = null): self
    {
        return new self($message, previous: $previous);
    }
}
