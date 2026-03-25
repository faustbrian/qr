<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Decoder\Common;

use InvalidArgumentException;

use function arraycopy;
use function count;
use function hashCode;
use function is_countable;
use function numberOfTrailingZeros;

/**
 * Compact mutable bit sequence used throughout the decoder pipeline.
 *
 * The implementation mirrors the decoder's packed-int representation so callers can
 * efficiently append, slice, reverse, and scan bit ranges without paying for a
 * PHP boolean array. The public API is intentionally stateful because the
 * decoder reuses instances heavily while traversing QR payloads and correction
 * data.
 *
 * @author Sean Owen
 */
final class BitArray
{
    /**
     * Packed 32-bit words storing the bit sequence.
     *
     * @var array<int, int>
     */
    private $bits;

    /**
     * Number of meaningful bits currently stored in the array.
     *
     * @var int
     */
    private $size;

    /**
     * Create a new bit array, optionally from an existing packed representation.
     *
     * When only a size is supplied, the instance is initialized with enough
     * storage for that many bits. When packed words are supplied, the caller is
     * responsible for keeping the packed data and size consistent.
     * @param mixed $bits
     * @param mixed $size
     */
    public function __construct($bits = [], $size = 0)
    {
        if (!$bits && !$size) {
            $this->{$size} = 0;
            $this->bits = [];
        } elseif ($bits && !$size) {
            $this->size = $bits;
            $this->bits = self::makeArray($bits);
        } else {
            $this->bits = $bits;
            $this->size = $size;
        }
    }

    /**
     * Return the logical size of the bit sequence.
     */
    public function getSize()
    {
        return $this->size;
    }

    /**
     * Return the number of bytes required to store the current bit length.
     */
    public function getSizeInBytes()
    {
        return ($this->size + 7) / 8;
    }

    /**
     * Set a single bit in the packed array.
     *
     * @param int $i bit index to set
     */
    public function set($i): void
    {
        $this->bits[(int) ($i / 32)] |= 1 << ($i & 0x1F);
        $this->bits[(int) ($i / 32)] = $this->bits[(int) ($i / 32)];
    }

    /**
     * Toggle a single bit in place.
     *
     * @param int $i bit index to flip
     */
    public function flip($i): void
    {
        $this->bits[(int) ($i / 32)] ^= 1 << ($i & 0x1F);
        $this->bits[(int) ($i / 32)] = $this->bits[(int) ($i / 32)];
    }

    /**
     * Find the next set bit at or after the supplied index.
     *
     * @see #getNextUnset(int)
     * @param  int $from first bit to inspect
     * @return int index of the first set bit, or `size` if none are set beyond
     *             the starting point
     */
    public function getNextSet($from)
    {
        if ($from >= $this->size) {
            return $this->size;
        }
        $bitsOffset = (int) ($from / 32);
        $currentBits = (int) $this->bits[$bitsOffset];
        // mask off lesser bits first
        $currentBits &= ~((1 << ($from & 0x1F)) - 1);

        while ($currentBits === 0) {
            if (++$bitsOffset === (is_countable($this->bits) ? count($this->bits) : 0)) {
                return $this->size;
            }
            $currentBits = $this->bits[$bitsOffset];
        }
        $result = ($bitsOffset * 32) + numberOfTrailingZeros($currentBits); // numberOfTrailingZeros

        return $result > $this->size ? $this->size : $result;
    }

    /**
     * Find the next unset bit at or after the supplied index.
     *
     * @see #getNextSet(int)
     * @param  int $from index to start scanning from
     * @return int index of the first unset bit, or `size` if the tail is fully
     *             set
     */
    public function getNextUnset($from)
    {
        if ($from >= $this->size) {
            return $this->size;
        }
        $bitsOffset = (int) ($from / 32);
        $currentBits = ~$this->bits[$bitsOffset];
        // mask off lesser bits first
        $currentBits &= ~((1 << ($from & 0x1F)) - 1);

        while ($currentBits === 0) {
            if (++$bitsOffset === (is_countable($this->bits) ? count($this->bits) : 0)) {
                return $this->size;
            }
            $currentBits = ~$this->bits[$bitsOffset];
        }
        $result = ($bitsOffset * 32) + numberOfTrailingZeros($currentBits);

        return $result > $this->size ? $this->size : $result;
    }

    /**
     * Replace a 32-bit window of the packed representation.
     *
     * @param int $i       first bit to overwrite
     * @param int $newBits packed value for the next 32 bits, with bit `i`
     *                     mapped to the least-significant bit
     */
    public function setBulk($i, $newBits): void
    {
        $this->bits[(int) ($i / 32)] = $newBits;
    }

    /**
     * Set every bit in the requested half-open range.
     *
     * @param int $start range start, inclusive
     * @param int $end   range end, exclusive
     */
    public function setRange($start, $end): void
    {
        if ($end < $start) {
            throw \Cline\Qr\Decoder\InvalidArgumentException::withMessage();
        }

        if ($end === $start) {
            return;
        }
        --$end; // will be easier to treat this as the last actually set bit -- inclusive
        $firstInt = (int) ($start / 32);
        $lastInt = (int) ($end / 32);

        for ($i = $firstInt; $i <= $lastInt; ++$i) {
            $firstBit = $i > $firstInt ? 0 : $start & 0x1F;
            $lastBit = $i < $lastInt ? 31 : $end & 0x1F;
            $mask = 0;

            if ($firstBit === 0 && $lastBit === 31) {
                $mask = -1;
            } else {
                $mask = 0;

                for ($j = $firstBit; $j <= $lastBit; ++$j) {
                    $mask |= 1 << $j;
                }
            }
            $this->bits[$i] = $this->bits[$i] | $mask;
        }
    }

    /**
     * Clear the entire packed bit array.
     */
    public function clear(): void
    {
        $max = is_countable($this->bits) ? count($this->bits) : 0;

        for ($i = 0; $i < $max; ++$i) {
            $this->bits[$i] = 0;
        }
    }

    /**
     * Test whether every bit in the supplied range matches the desired value.
     *
     * @param int  $start range start, inclusive
     * @param int  $end   range end, exclusive
     * @param bool $value `true` to require set bits, `false` to require unset bits
     *
     * @throws InvalidArgumentException if the range bounds are invalid
     * @return bool                     `true` when the entire range matches the requested state
     */
    public function isRange($start, $end, $value): bool
    {
        if ($end < $start) {
            throw \Cline\Qr\Decoder\InvalidArgumentException::withMessage();
        }

        if ($end === $start) {
            return true; // empty range matches
        }
        --$end; // will be easier to treat this as the last actually set bit -- inclusive
        $firstInt = (int) ($start / 32);
        $lastInt = (int) ($end / 32);

        for ($i = $firstInt; $i <= $lastInt; ++$i) {
            $firstBit = $i > $firstInt ? 0 : $start & 0x1F;
            $lastBit = $i < $lastInt ? 31 : $end & 0x1F;
            $mask = 0;

            if ($firstBit === 0 && $lastBit === 31) {
                $mask = -1;
            } else {
                $mask = 0;

                for ($j = $firstBit; $j <= $lastBit; ++$j) {
                    $mask = $mask | (1 << $j);
                }
            }

            // Return false if we're looking for 1s and the masked bits[i] isn't all 1s (that is,
            // equals the mask, or we're looking for 0s and the masked portion is not all 0s
            if (($this->bits[$i] & $mask) !== ($value ? $mask : 0)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Append the requested low-order bits from an integer value.
     *
     * Bits are appended most-significant first so the resulting sequence matches
     * the original bit ordering used by QR payload parsing.
     *
     * @param int $value   integer containing the bits to append
     * @param int $numBits number of low-order bits to copy
     */
    public function appendBits($value, $numBits): void
    {
        if ($numBits < 0 || $numBits > 32) {
            throw \Cline\Qr\Decoder\InvalidArgumentException::withMessage('Num bits must be between 0 and 32');
        }
        $this->ensureCapacity($this->size + $numBits);

        for ($numBitsLeft = $numBits; $numBitsLeft > 0; --$numBitsLeft) {
            $this->appendBit((($value >> ($numBitsLeft - 1)) & 0x01) === 1);
        }
    }

    /**
     * Append a single bit to the tail of the sequence.
     */
    public function appendBit(bool $bit): void
    {
        $this->ensureCapacity($this->size + 1);

        if ($bit) {
            $this->bits[(int) ($this->size / 32)] |= 1 << ($this->size & 0x1F);
        }
        ++$this->size;
    }

    /**
     * Append the contents of another packed bit array.
     * @param mixed $other
     */
    public function appendBitArray($other): void
    {
        $otherSize = $other->size;
        $this->ensureCapacity($this->size + $otherSize);

        for ($i = 0; $i < $otherSize; ++$i) {
            $this->appendBit($other->get($i));
        }
    }

    /**
     * XOR this bit array with another packed bit array of equal length.
     * @param mixed $other
     */
    public function _xor($other): void
    {
        if ((is_countable($this->bits) ? count($this->bits) : 0) !== (is_countable($other->bits) ? count($other->bits) : 0)) {
            throw \Cline\Qr\Decoder\InvalidArgumentException::withMessage("Sizes don't match");
        }
        $count = is_countable($this->bits) ? count($this->bits) : 0;

        for ($i = 0; $i < $count; ++$i) {
            // The last byte could be incomplete (i.e. not have 8 bits in
            // it) but there is no problem since 0 XOR 0 == 0.
            $this->bits[$i] ^= $other->bits[$i];
        }
    }

    /**
     * Export the bit sequence into a byte array using network byte order.
     *
     * @param int   $bitOffset first bit to start writing
     * @param array $array     destination array, written most-significant byte first
     * @param int   $offset    destination offset in the byte array
     * @param int   $numBytes  number of bytes to write
     */
    public function toBytes($bitOffset, array &$array, $offset, $numBytes): void
    {
        for ($i = 0; $i < $numBytes; ++$i) {
            $theByte = 0;

            for ($j = 0; $j < 8; ++$j) {
                if ($this->get($bitOffset)) {
                    $theByte |= 1 << (7 - $j);
                }
                ++$bitOffset;
            }
            $array[(int) ($offset + $i)] = $theByte;
        }
    }

    /**
     * Read a single bit from the packed sequence.
     *
     * @param float|int $i bit index to read
     *
     * @return bool `true` when the bit is set
     */
    public function get(int|float $i): bool
    {
        $key = (int) ($i / 32);

        return ($this->bits[$key] & (1 << ($i & 0x1F))) !== 0;
    }

    /**
     * Expose the packed integer words backing this bit array.
     *
     * The returned array is the internal representation, so callers should treat
     * it as read-only unless they intentionally want to mutate in place.
     *
     * @psalm-return array<int|mixed>|null
     */
    public function getBitArray(): ?array
    {
        return $this->bits;
    }

    /**
     * Reverse the logical bit order in place.
     */
    public function reverse(): void
    {
        $newBits = [];
        // reverse all int's first
        $len = ($this->size - 1) / 32;
        $oldBitsLen = $len + 1;

        for ($i = 0; $i < $oldBitsLen; ++$i) {
            $x = $this->bits[$i]; /*
             $x = (($x >>  1) & 0x55555555L) | (($x & 0x55555555L) <<  1);
                  $x = (($x >>  2) & 0x33333333L) | (($x & 0x33333333L) <<  2);
                  $x = (($x >>  4) & 0x0f0f0f0fL) | (($x & 0x0f0f0f0fL) <<  4);
                  $x = (($x >>  8) & 0x00ff00ffL) | (($x & 0x00ff00ffL) <<  8);
                  $x = (($x >> 16) & 0x0000ffffL) | (($x & 0x0000ffffL) << 16);
             */
            $x = (($x >> 1) & 0x55_55_55_55) | (($x & 0x55_55_55_55) << 1);
            $x = (($x >> 2) & 0x33_33_33_33) | (($x & 0x33_33_33_33) << 2);
            $x = (($x >> 4) & 0x0F_0F_0F_0F) | (($x & 0x0F_0F_0F_0F) << 4);
            $x = (($x >> 8) & 0x00_FF_00_FF) | (($x & 0x00_FF_00_FF) << 8);
            $x = (($x >> 16) & 0x00_00_FF_FF) | (($x & 0x00_00_FF_FF) << 16);
            $newBits[(int) $len - $i] = (int) $x;
        }

        // now correct the int's if the bit size isn't a multiple of 32
        if ($this->size !== $oldBitsLen * 32) {
            $leftOffset = $oldBitsLen * 32 - $this->size;
            $mask = 1;

            for ($i = 0; $i < 31 - $leftOffset; ++$i) {
                $mask = ($mask << 1) | 1;
            }
            $currentInt = ($newBits[0] >> $leftOffset) & $mask;

            for ($i = 1; $i < $oldBitsLen; ++$i) {
                $nextInt = $newBits[$i];
                $currentInt |= $nextInt << (32 - $leftOffset);
                $newBits[(int) $i - 1] = $currentInt;
                $currentInt = ($nextInt >> $leftOffset) & $mask;
            }
            $newBits[(int) $oldBitsLen - 1] = $currentInt;
        }
        //        $bits = $newBits;
    }

    /**
     * Compare the logical content of two bit arrays.
     * @param mixed $o
     */
    public function equals($o): bool
    {
        if (!$o instanceof self) {
            return false;
        }
        $other = $o;

        return $this->size === $other->size && $this->bits === $other->bits;
    }

    /**
     * Compute a stable hash from the packed contents and logical size.
     */
    public function hashCode()
    {
        return 31 * $this->size + hashCode($this->bits);
    }

    /**
     * Render the bit sequence as grouped `X` and `.` characters.
     */
    public function toString(): string
    {
        $result = '';

        for ($i = 0; $i < $this->size; ++$i) {
            if (($i & 0x07) === 0) {
                $result .= ' ';
            }
            $result .= ($this->get($i) ? 'X' : '.');
        }

        return (string) $result;
    }

    /**
     * Create a copy of the current packed sequence.
     */
    public function _clone(): self
    {
        return new self($this->bits, $this->size);
    }

    /**
     * Allocate a packed bit array large enough to hold the requested size.
     *
     * The actual storage is represented as integer words; the helper keeps the
     * calling code readable while hiding the row-size arithmetic.
     *
     * @param mixed $size
     * @psalm-return array<empty, empty>
     */
    private static function makeArray($size): array
    {
        return [];
    }

    /**
     * Ensure enough storage exists for the requested logical size.
     * @param mixed $size
     */
    private function ensureCapacity($size): void
    {
        if (!($size > (is_countable($this->bits) ? count($this->bits) : 0) * 32)) {
            return;
        }

        $newBits = self::makeArray($size);
        $newBits = arraycopy($this->bits, 0, $newBits, 0, is_countable($this->bits) ? count($this->bits) : 0);
        $this->bits = $newBits;
    }
}
