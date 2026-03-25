<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Decoder\Qrcode\Decoder;

use Cline\Qr\Decoder\Common\BitMatrix;
use Cline\Qr\Decoder\InvalidArgumentException;

/**
 * QR data-mask definitions from ISO 18004.
 *
 * The decoder uses these mask formulas to unmask the raw symbol matrix before
 * reading codewords. For simplicity, the entire matrix is processed in place,
 * including regions that are later ignored by the reader.
 *
 * @author Sean Owen
 */
abstract class AbstractDataMask
{
    /**
     * Cached mask implementations for the eight QR mask patterns.
     */
    private static array $DATA_MASKS = [];

    public function __construct() {}

    /**
     * Populate the mask lookup table.
     */
    public static function Init(): void
    {
        self::$DATA_MASKS = [
            new DataMask000(),
            new DataMask001(),
            new DataMask010(),
            new DataMask011(),
            new DataMask100(),
            new DataMask101(),
            new DataMask110(),
            new DataMask111(),
        ];
    }

    /**
     * Resolve one of the eight QR mask implementations.
     *
     * @param  int  $reference Mask reference from 0 to 7.
     * @return self Selected mask implementation.
     */
    public static function forReference($reference)
    {
        if ($reference < 0 || $reference > 7) {
            throw InvalidArgumentException::withMessage();
        }

        return self::$DATA_MASKS[$reference];
    }

    /**
     * Unmask a QR matrix in place.
     *
     * @param BitMatrix $bits      QR code matrix to modify.
     * @param int       $dimension Matrix dimension.
     */
    final public function unmaskBitMatrix($bits, $dimension): void
    {
        for ($i = 0; $i < $dimension; ++$i) {
            for ($j = 0; $j < $dimension; ++$j) {
                if (!$this->isMasked($i, $j)) {
                    continue;
                }

                $bits->flip($j, $i);
            }
        }
    }

    /**
     * Determine whether a module at the given coordinates is masked.
     *
     * @psalm-param 0|positive-int $i Row coordinate.
     * @psalm-param 0|positive-int $j Column coordinate.
     */
    abstract public function isMasked(int $i, int $j);
}

AbstractDataMask::Init();

/**
 * Mask pattern 000: modules where row plus column is even.
 * @author Brian Faust <brian@cline.sh>
 */
final class DataMask000 extends AbstractDataMask
{
    public function isMasked($i, $j): bool
    {
        return (($i + $j) & 0x01) === 0;
    }
}

/**
 * Mask pattern 001: modules in even-numbered rows.
 * @author Brian Faust <brian@cline.sh>
 */
final class DataMask001 extends AbstractDataMask
{
    public function isMasked($i, $j): bool
    {
        return ($i & 0x01) === 0;
    }
}

/**
 * Mask pattern 010: modules in columns divisible by three.
 * @author Brian Faust <brian@cline.sh>
 */
final class DataMask010 extends AbstractDataMask
{
    public function isMasked($i, $j): bool
    {
        return $j % 3 === 0;
    }
}

/**
 * Mask pattern 011: modules where row plus column is divisible by three.
 * @author Brian Faust <brian@cline.sh>
 */
final class DataMask011 extends AbstractDataMask
{
    public function isMasked($i, $j): bool
    {
        return ($i + $j) % 3 === 0;
    }
}

/**
 * Mask pattern 100: modules where the half-row and third-column sum is even.
 * @author Brian Faust <brian@cline.sh>
 */
final class DataMask100 extends AbstractDataMask
{
    public function isMasked($i, $j): bool
    {
        return (int) (((int) ($i / 2) + (int) ($j / 3)) & 0x01) === 0;
    }
}

/**
 * Mask pattern 101: modules where the row/column product hits the mask rule.
 * @author Brian Faust <brian@cline.sh>
 */
final class DataMask101 extends AbstractDataMask
{
    public function isMasked($i, $j): bool
    {
        $temp = $i * $j;

        return ($temp & 0x01) + ($temp % 3) === 0;
    }
}

/**
 * Mask pattern 110: parity of the row/column product mask sum.
 * @author Brian Faust <brian@cline.sh>
 */
final class DataMask110 extends AbstractDataMask
{
    public function isMasked($i, $j): bool
    {
        $temp = $i * $j;

        return ((($temp & 0x01) + ($temp % 3)) & 0x01) === 0;
    }
}

/**
 * Mask pattern 111: parity of the combined row-plus-column and product mask.
 * @author Brian Faust <brian@cline.sh>
 */
final class DataMask111 extends AbstractDataMask
{
    public function isMasked($i, $j): bool
    {
        return (((($i + $j) & 0x01) + (($i * $j) % 3)) & 0x01) === 0;
    }
}
