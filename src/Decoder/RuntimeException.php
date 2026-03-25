<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Decoder;

use Cline\Qr\Exception\QrExceptionInterface;
use Throwable;

/**
 * Raised when decoder state becomes inconsistent at runtime.
 * @author Brian Faust <brian@cline.sh>
 */
final class RuntimeException extends \RuntimeException implements QrExceptionInterface
{
    public static function withMessage(string $message = '', ?Throwable $previous = null): self
    {
        return new self($message, previous: $previous);
    }
}
