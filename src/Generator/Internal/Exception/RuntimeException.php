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
 * Raised when generator state becomes inconsistent during processing.
 *
 * This type is reserved for failures discovered after work has already begun,
 * such as impossible matrix states or invariant violations that indicate a
 * logic error or corrupted intermediate data.
 * @author Brian Faust <brian@cline.sh>
 */
final class RuntimeException extends \RuntimeException implements ExceptionInterface
{
    public static function withMessage(string $message, ?Throwable $previous = null): self
    {
        return new self($message, previous: $previous);
    }
}
