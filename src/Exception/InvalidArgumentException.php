<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Exception;

use Throwable;

/**
 * Package invalid-argument failure with a factory-backed constructor.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class InvalidArgumentException extends \InvalidArgumentException implements QrExceptionInterface
{
    public static function withMessage(string $message, ?Throwable $previous = null): self
    {
        return new self($message, previous: $previous);
    }
}
