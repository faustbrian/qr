<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Generator\Common;

use const STR_PAD_LEFT;

use function decbin;
use function mb_str_pad;
use function mb_strrpos;

/**
 * Low-level helpers shared by the packed-bit data structures.
 *
 * The QR implementation stores data in 32-bit chunks, so these helpers provide
 * the missing operations that PHP does not expose directly in a portable way.
 * @author Brian Faust <brian@cline.sh>
 */
final class BitUtils
{
    private function __construct() {}

    /**
     * Perform an unsigned right shift on a signed integer.
     */
    public static function unsignedRightShift(int $a, int $b): int
    {
        return
            $a >= 0
            ? $a >> $b
            : (($a & 0x7F_FF_FF_FF) >> $b) | (0x40_00_00_00 >> ($b - 1));
    }

    /**
     * Count the number of trailing zero bits in a 32-bit value.
     */
    public static function numberOfTrailingZeros(int $i): int
    {
        $lastPos = mb_strrpos(mb_str_pad(decbin($i), 32, '0', STR_PAD_LEFT), '1');

        return $lastPos === false ? 32 : 31 - $lastPos;
    }
}
