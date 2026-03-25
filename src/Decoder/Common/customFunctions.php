<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

if (!function_exists('arraycopy')) {
    /**
     * Copies a slice of an array into another array, mirroring Java's arraycopy.
     *
     * The decoder port relies on this helper in a few low-level routines where the
     * original Java implementation used mutable array segments.
     * @param mixed $srcArray
     * @param mixed $srcPos
     * @param mixed $destArray
     * @param mixed $destPos
     * @param mixed $length
     */
    function arraycopy($srcArray, $srcPos, $destArray, $destPos, $length): array
    {
        $srcArrayToCopy = array_slice($srcArray, $srcPos, $length);
        array_splice($destArray, $destPos, $length, $srcArrayToCopy);

        return $destArray;
    }
}

if (!function_exists('hashCode')) {
    /**
     * Computes the Java-style hash code for a string value.
     * @param mixed $s
     */
    function hashCode($s): int
    {
        $h = 0;
        $len = mb_strlen((string) $s);

        for ($i = 0; $i < $len; ++$i) {
            $h = 31 * $h + ord($s[$i]);
        }

        return $h;
    }
}

if (!function_exists('numberOfTrailingZeros')) {
    /**
     * Returns the number of trailing zero bits in the integer.
     *
     * @param mixed $i
     * @psalm-return 0|32|positive-int
     */
    function numberOfTrailingZeros($i): int
    {
        if ($i === 0) {
            return 32;
        }
        $num = 0;

        while (($i & 1) === 0) {
            $i >>= 1;
            ++$num;
        }

        return $num;
    }
}

if (!function_exists('uRShift')) {
    /**
     * Performs an unsigned right shift compatible with the decoder port.
     * @param mixed $a
     * @param mixed $b
     */
    function uRShift($a, $b)
    {
        static $mask = 8 * \PHP_INT_SIZE - 1;

        if ($b === 0) {
            return $a;
        }

        return ($a >> $b) & ~(1 << $mask >> ($b - 1));
    }
}

/*
function sdvig3($num,$count=1){//>>> 32 bit
    $s = decbin($num);

    $sarray  = str_split($s,1);
    $sarray = array_slice($sarray,-32);//32bit

    for($i=0;$i<=1;$i++) {
        array_pop($sarray);
        array_unshift($sarray, '0');
    }
    return bindec(implode($sarray));
}
*/

if (!function_exists('sdvig3')) {
    /**
     * Performs a right shift while preserving the port's historical semantics.
     * @param mixed $a
     * @param mixed $b
     */
    function sdvig3($a, $b): float|int
    {
        if ($a >= 0) {
            return bindec(decbin($a >> $b)); // simply right shift for positive number
        }

        $bin = decbin($a >> $b);

        $bin = mb_substr($bin, $b); // zero fill on the left side

        return bindec($bin);
    }
}

if (!function_exists('floatToIntBits')) {
    /**
     * Reinterprets a floating point value as its raw integer bits.
     * @param mixed $float_val
     */
    function floatToIntBits($float_val)
    {
        $int = unpack('i', pack('f', $float_val));

        return $int[1];
    }
}

if (!function_exists('fill_array')) {
    /**
     * Builds an array filled with the provided value.
     *
     * The helper mirrors the Java code's array initialization patterns and ensures
     * callers always receive at least one slot when the requested count is non-positive.
     *
     * @param mixed $index
     * @param mixed $count
     * @param mixed $value
     * @psalm-return array<int, mixed>
     */
    function fill_array($index, $count, $value): array
    {
        if ($count <= 0) {
            return [0];
        }

        return array_fill($index, $count, $value);
    }
}
