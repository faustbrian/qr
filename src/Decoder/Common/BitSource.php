<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Decoder\Common;

use InvalidArgumentException;

use function count;
use function is_countable;

/**
 * Stateful bit reader over a byte sequence.
 *
 * The decoder uses this abstraction when payload fields are not byte-aligned.
 * It tracks both the current byte and bit offset so callers can consume a
 * stream of variable-width values without manually shifting and masking.
 *
 * @author Sean Owen
 */
final class BitSource
{
    private int $byteOffset = 0;

    private int $bitOffset = 0;

    /**
     * Create a new reader for the supplied byte array.
     *
     * Bits are read from the first byte first and within each byte from
     * most-significant to least-significant bit.
     *
     * @param array $bytes source bytes to read from
     */
    public function __construct(
        private readonly array $bytes,
    ) {}

    /**
     * Return the current bit offset inside the active byte.
     */
    public function getBitOffset(): int
    {
        return $this->bitOffset;
    }

    /**
     * Return the index of the next byte that will be consumed.
     */
    public function getByteOffset(): int
    {
        return $this->byteOffset;
    }

    /**
     * Consume the requested number of bits from the stream.
     *
     * The returned value is left-aligned in the least-significant bits of the
     * integer, matching the decoder's decoder expectations.
     *
     * @param int $numBits number of bits to read
     *
     * @throws InvalidArgumentException if the request is outside the supported
     *                                  range or exceeds the remaining input
     * @return int                      extracted bit value
     */
    public function readBits($numBits)
    {
        if ($numBits < 1 || $numBits > 32 || $numBits > $this->available()) {
            throw \Cline\Qr\Decoder\InvalidArgumentException::withMessage((string) $numBits);
        }

        $result = 0;

        // First, read remainder from current byte
        if ($this->bitOffset > 0) {
            $bitsLeft = 8 - $this->bitOffset;
            $toRead = $numBits < $bitsLeft ? $numBits : $bitsLeft;
            $bitsToNotRead = $bitsLeft - $toRead;
            $mask = (0xFF >> (8 - $toRead)) << $bitsToNotRead;
            $result = ($this->bytes[$this->byteOffset] & $mask) >> $bitsToNotRead;
            $numBits -= $toRead;
            $this->bitOffset += $toRead;

            if ($this->bitOffset === 8) {
                $this->bitOffset = 0;
                ++$this->byteOffset;
            }
        }

        // Next read whole bytes
        if ($numBits > 0) {
            while ($numBits >= 8) {
                $result = ($result << 8) | ($this->bytes[$this->byteOffset] & 0xFF);
                ++$this->byteOffset;
                $numBits -= 8;
            }

            // Finally read a partial byte
            if ($numBits > 0) {
                $bitsToNotRead = 8 - $numBits;
                $mask = (0xFF >> $bitsToNotRead) << $bitsToNotRead;
                $result = ($result << $numBits) | (($this->bytes[$this->byteOffset] & $mask) >> $bitsToNotRead);
                $this->bitOffset += $numBits;
            }
        }

        return $result;
    }

    /**
     * Return how many unread bits remain in the source.
     */
    public function available(): int
    {
        return 8 * ((is_countable($this->bytes) ? count($this->bytes) : 0) - $this->byteOffset) - $this->bitOffset;
    }
}
