<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Decoder\Common\Detector;

use function sqrt;

/**
 * Numeric helpers used by the detector geometry code.
 *
 * The detector repeatedly rounds and measures distances while searching for
 * QR finder and alignment patterns. Keeping these operations in a tiny helper
 * avoids scattering floating-point edge cases through the geometric code.
 * @author Brian Faust <brian@cline.sh>
 */
final class MathUtils
{
    /**
     * Round a floating-point value to the nearest integer.
     *
     * The implementation matches the decoder's shortcut rather than PHP's native
     * rounding semantics for negative half values.
     *
     * @param float $d real value to round
     *
     * @return int rounded integer
     */
    public static function round(float $d)
    {
        return (int) ($d + ($d < 0.0 ? -0.5 : 0.5));
    }

    /**
     * Compute the Euclidean distance between two points.
     */
    public static function distance(float|int $aX, float|int $aY, float $bX, float $bY): float
    {
        $xDiff = $aX - $bX;
        $yDiff = $aY - $bY;

        return (float) sqrt($xDiff * $xDiff + $yDiff * $yDiff);
    }
}
