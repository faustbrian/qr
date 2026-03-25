<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Generator\Common;

use Cline\Qr\Generator\Internal\Exception\OutOfBoundsException;
use ValueError;

/**
 * QR error-correction strength.
 *
 * The enum mirrors the four correction levels defined by ISO 18004 and keeps
 * the encoded bit values available for format information generation.
 * @author Brian Faust <brian@cline.sh>
 */
enum ErrorCorrectionLevel: int
{
    case L = 0x01;
    case M = 0x00;
    case Q = 0x03;
    case H = 0x02;

    /**
     * Resolve a correction level from its two-bit QR format value.
     *
     * @throws OutOfBoundsException if number of bits is invalid
     */
    public static function forBits(int $bits): self
    {
        try {
            return self::from($bits);
        } catch (ValueError) {
            throw OutOfBoundsException::withMessage('Invalid number of bits');
        }
    }

    /**
     * Return the encoded two-bit value used in QR format information.
     */
    public function getBits(): int
    {
        return $this->value;
    }
}
