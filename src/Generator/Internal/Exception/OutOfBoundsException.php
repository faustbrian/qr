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
 * Raised when QR metadata or lookup indexes fall outside legal bounds.
 *
 * Generator code uses this for bit-pattern lookups and enum conversions where
 * the input is structurally valid data but references a value outside the QR
 * specification's supported range.
 * @author Brian Faust <brian@cline.sh>
 */
final class OutOfBoundsException extends \OutOfBoundsException implements ExceptionInterface
{
    public static function withMessage(string $message, ?Throwable $previous = null): self
    {
        return new self($message, previous: $previous);
    }
}
