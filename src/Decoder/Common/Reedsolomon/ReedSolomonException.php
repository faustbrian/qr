<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Decoder\Common\Reedsolomon;

use Cline\Qr\Exception\QrExceptionInterface;
use Exception;

/**
 * Signals that Reed-Solomon correction could not recover the codewords.
 *
 * This is a recoverable decode failure rather than a programming error. Callers
 * typically surface it as an unreadable or corrupted QR code.
 *
 * @author Sean Owen
 */
final class ReedSolomonException extends Exception implements QrExceptionInterface
{
    public static function withMessage(string $message): self
    {
        return new self($message);
    }
}
