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
 * Raised when decoded or computed values do not match expected QR semantics.
 *
 * The generator uses this when data makes it through initial type checks but
 * still resolves to an unsupported or contradictory value during later stages
 * of matrix construction or metadata interpretation.
 * @author Brian Faust <brian@cline.sh>
 */
final class UnexpectedValueException extends \UnexpectedValueException implements ExceptionInterface
{
    public static function withMessage(string $message, ?Throwable $previous = null): self
    {
        return new self($message, previous: $previous);
    }
}
