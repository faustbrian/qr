<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Generator\Common;

use Cline\Qr\Generator\Internal\Exception\InvalidArgumentException;
use SplFixedArray;
use Stringable;

use function array_fill;
use function count;
use function min;

/**
 * Dense, mutable bit storage used by the encoder and decoder pipelines.
 *
 * Bits are packed into 32-bit words so the package can manipulate symbol data
 * efficiently without allocating a separate boolean per position. The class is
 * intentionally stateful: most callers reuse a single instance while building
 * or decoding a symbol and rely on methods such as `clear()`, `appendBit()`,
 * and `reverse()` to mutate the same buffer in place.
 * @author Brian Faust <brian@cline.sh>
 */
final class BitArray implements Stringable
{
    /**
     * Bits represented as an array of integers.
     *
     * @var SplFixedArray<int>
     */
    private SplFixedArray $bits;

    /**
     * Create a bit array with the requested logical size.
     *
     * The backing storage is sized from the bit count immediately so the array
     * can be used without a separate allocation step during tight encode loops.
     */
    public function __construct(
        private int $size = 0,
    ) {
        $this->bits = SplFixedArray::fromArray(array_fill(0, ($this->size + 31) >> 3, 0));
    }

    /**
     * Render the logical bits as a debugging string.
     */
    public function __toString(): string
    {
        $result = '';

        for ($i = 0; $i < $this->size; ++$i) {
            if (0 === ($i & 0x07)) {
                $result .= ' ';
            }

            $result .= $this->get($i) ? 'X' : '.';
        }

        return $result;
    }

    /**
     * Return the logical length of the array in bits.
     */
    public function getSize(): int
    {
        return $this->size;
    }

    /**
     * Return the number of bytes required to represent the current bits.
     */
    public function getSizeInBytes(): int
    {
        return ($this->size + 7) >> 3;
    }

    /**
     * Grow the backing storage if the requested bit count exceeds capacity.
     *
     * The method only expands storage; it never shrinks the current buffer.
     */
    public function ensureCapacity(int $size): void
    {
        if ($size <= count($this->bits) << 5) {
            return;
        }

        $this->bits->setSize(($size + 31) >> 5);
    }

    /**
     * Read the bit at the requested logical position.
     */
    public function get(int $i): bool
    {
        return 0 !== ($this->bits[$i >> 5] & (1 << ($i & 0x1F)));
    }

    /**
     * Set the bit at the requested logical position to true.
     */
    public function set(int $i): void
    {
        $this->bits[$i >> 5] = $this->bits[$i >> 5] | 1 << ($i & 0x1F);
    }

    /**
     * Invert the bit at the requested logical position.
     */
    public function flip(int $i): void
    {
        $this->bits[$i >> 5] ^= 1 << ($i & 0x1F);
    }

    /**
     * Find the next set bit at or after the supplied offset.
     *
     * The search stops at the logical size even if the backing storage contains
     * extra capacity.
     */
    public function getNextSet(int $from): int
    {
        if ($from >= $this->size) {
            return $this->size;
        }

        $bitsOffset = $from >> 5;
        $currentBits = $this->bits[$bitsOffset];
        $bitsLength = count($this->bits);
        $currentBits &= ~((1 << ($from & 0x1F)) - 1);

        while (0 === $currentBits) {
            if (++$bitsOffset === $bitsLength) {
                return $this->size;
            }

            $currentBits = $this->bits[$bitsOffset];
        }

        $result = ($bitsOffset << 5) + BitUtils::numberOfTrailingZeros($currentBits);

        return min($result, $this->size);
    }

    /**
     * Find the next unset bit at or after the supplied offset.
     */
    public function getNextUnset(int $from): int
    {
        if ($from >= $this->size) {
            return $this->size;
        }

        $bitsOffset = $from >> 5;
        $currentBits = ~$this->bits[$bitsOffset];
        $bitsLength = count($this->bits);
        $currentBits &= ~((1 << ($from & 0x1F)) - 1);

        while (0 === $currentBits) {
            if (++$bitsOffset === $bitsLength) {
                return $this->size;
            }

            $currentBits = ~$this->bits[$bitsOffset];
        }

        $result = ($bitsOffset << 5) + BitUtils::numberOfTrailingZeros($currentBits);

        return min($result, $this->size);
    }

    /**
     * Overwrite one 32-bit storage word with the supplied value.
     *
     * This is a low-level helper used by row and matrix operations that already
     * manage bit packing externally.
     */
    public function setBulk(int $i, int $newBits): void
    {
        $this->bits[$i >> 5] = $newBits;
    }

    /**
     * Set every bit in the half-open range `[start, end)`.
     *
     * @throws InvalidArgumentException if end is smaller than start
     *
     * The method is used for finder patterns and other run-length style regions
     * where a contiguous set of modules must be marked in one operation.
     */
    public function setRange(int $start, int $end): void
    {
        if ($end < $start) {
            throw InvalidArgumentException::withMessage('End must be greater or equal to start');
        }

        if ($end === $start) {
            return;
        }

        --$end;

        $firstInt = $start >> 5;
        $lastInt = $end >> 5;

        for ($i = $firstInt; $i <= $lastInt; ++$i) {
            $firstBit = $i > $firstInt ? 0 : $start & 0x1F;
            $lastBit = $i < $lastInt ? 31 : $end & 0x1F;

            if (0 === $firstBit && 31 === $lastBit) {
                $mask = 0x7F_FF_FF_FF;
            } else {
                $mask = 0;

                for ($j = $firstBit; $j < $lastBit; ++$j) {
                    $mask |= 1 << $j;
                }
            }

            $this->bits[$i] = $this->bits[$i] | $mask;
        }
    }

    /**
     * Reset the array so every stored bit becomes false.
     */
    public function clear(): void
    {
        $bitsLength = count($this->bits);

        for ($i = 0; $i < $bitsLength; ++$i) {
            $this->bits[$i] = 0;
        }
    }

    /**
     * Verify that every bit in the half-open range `[start, end)` matches `value`.
     *
     * @throws InvalidArgumentException if end is smaller than start
     */
    public function isRange(int $start, int $end, bool $value): bool
    {
        if ($end < $start) {
            throw InvalidArgumentException::withMessage('End must be greater or equal to start');
        }

        if ($end === $start) {
            return true;
        }

        --$end;

        $firstInt = $start >> 5;
        $lastInt = $end >> 5;

        for ($i = $firstInt; $i <= $lastInt; ++$i) {
            $firstBit = $i > $firstInt ? 0 : $start & 0x1F;
            $lastBit = $i < $lastInt ? 31 : $end & 0x1F;

            if (0 === $firstBit && 31 === $lastBit) {
                $mask = 0x7F_FF_FF_FF;
            } else {
                $mask = 0;

                for ($j = $firstBit; $j <= $lastBit; ++$j) {
                    $mask |= 1 << $j;
                }
            }

            if (($this->bits[$i] & $mask) !== ($value ? $mask : 0)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Append a single bit to the logical end of the array.
     */
    public function appendBit(bool $bit): void
    {
        $this->ensureCapacity($this->size + 1);

        if ($bit) {
            $this->bits[$this->size >> 5] = $this->bits[$this->size >> 5] | (1 << ($this->size & 0x1F));
        }

        ++$this->size;
    }

    /**
     * Append an integer value using the requested number of high-order bits.
     *
     * @throws InvalidArgumentException if num bits is not between 0 and 32
     */
    public function appendBits(int $value, int $numBits): void
    {
        if ($numBits < 0 || $numBits > 32) {
            throw InvalidArgumentException::withMessage('Num bits must be between 0 and 32');
        }

        $this->ensureCapacity($this->size + $numBits);

        for ($numBitsLeft = $numBits; $numBitsLeft > 0; --$numBitsLeft) {
            $this->appendBit((($value >> ($numBitsLeft - 1)) & 0x01) === 1);
        }
    }

    /**
     * Append the contents of another bit array to this one.
     */
    public function appendBitArray(self $other): void
    {
        $otherSize = $other->getSize();
        $this->ensureCapacity($this->size + $other->getSize());

        for ($i = 0; $i < $otherSize; ++$i) {
            $this->appendBit($other->get($i));
        }
    }

    /**
     * XOR this array with another array of equal storage width.
     *
     * @throws InvalidArgumentException if sizes don't match
     */
    public function xorBits(self $other): void
    {
        $bitsLength = count($this->bits);
        $otherBits = $other->getBitArray();

        if ($bitsLength !== count($otherBits)) {
            throw InvalidArgumentException::withMessage('Sizes don\'t match');
        }

        for ($i = 0; $i < $bitsLength; ++$i) {
            $this->bits[$i] = $this->bits[$i] ^ $otherBits[$i];
        }
    }

    /**
     * Convert a slice of the bit array into big-endian bytes.
     *
     * @return SplFixedArray<int>
     */
    public function toBytes(int $bitOffset, int $numBytes): SplFixedArray
    {
        $bytes = new SplFixedArray($numBytes);

        for ($i = 0; $i < $numBytes; ++$i) {
            $byte = 0;

            for ($j = 0; $j < 8; ++$j) {
                if ($this->get($bitOffset)) {
                    $byte |= 1 << (7 - $j);
                }

                ++$bitOffset;
            }

            $bytes[$i] = $byte;
        }

        return $bytes;
    }

    /**
     * Expose the raw packed bit storage.
     *
     * Callers use this only when they need low-level access to the packed words
     * or when another helper must operate on the same storage layout.
     *
     * @return SplFixedArray<int>
     */
    public function getBitArray(): SplFixedArray
    {
        return $this->bits;
    }

    /**
     * Reverse the logical bit order in place.
     */
    public function reverse(): void
    {
        $newBits = new SplFixedArray(count($this->bits));

        for ($i = 0; $i < $this->size; ++$i) {
            if (!$this->get($this->size - $i - 1)) {
                continue;
            }

            $newBits[$i >> 5] = $newBits[$i >> 5] | (1 << ($i & 0x1F));
        }

        $this->bits = $newBits;
    }
}
