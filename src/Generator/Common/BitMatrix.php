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

use function array_fill;
use function count;
use function intdiv;

/**
 * Dense 2D bit storage for QR symbol construction and analysis.
 *
 * Coordinates are always expressed as `x, y`, where `x` is the column and
 * `y` is the row. The origin is the top-left corner, matching the QR
 * specification and the rest of the generator and decoder pipeline.
 * @author Brian Faust <brian@cline.sh>
 */
final class BitMatrix
{
    /**
     * Height of the matrix in modules.
     */
    private ?int $height;

    /**
     * Number of packed 32-bit words in each row.
     */
    private int $rowSize;

    /**
     * Bits representation.
     *
     * @var SplFixedArray<int>
     */
    private SplFixedArray $bits;

    /**
     * Create a square or rectangular bit matrix.
     *
     * @throws InvalidArgumentException if a dimension is smaller than zero
     */
    public function __construct(
        /**
         * Width of the matrix in modules.
         */
        private readonly int $width,
        ?int $height = null,
    ) {
        if (null === $height) {
            $height = $width;
        }

        if ($width < 1 || $height < 1) {
            throw InvalidArgumentException::withMessage('Both dimensions must be greater than zero');
        }

        $this->height = $height;
        $this->rowSize = ($width + 31) >> 5;
        $this->bits = SplFixedArray::fromArray(array_fill(0, $this->rowSize * $height, 0));
    }

    /**
     * Read the bit at the given coordinate, where `true` means black.
     */
    public function get(int $x, int $y): bool
    {
        $offset = $y * $this->rowSize + ($x >> 5);

        return 0 !== (BitUtils::unsignedRightShift($this->bits[$offset], $x & 0x1F) & 1);
    }

    /**
     * Set the bit at the given coordinate to black.
     */
    public function set(int $x, int $y): void
    {
        $offset = $y * $this->rowSize + ($x >> 5);
        $this->bits[$offset] = $this->bits[$offset] | (1 << ($x & 0x1F));
    }

    /**
     * Invert the bit at the given coordinate.
     */
    public function flip(int $x, int $y): void
    {
        $offset = $y * $this->rowSize + ($x >> 5);
        $this->bits[$offset] = $this->bits[$offset] ^ (1 << ($x & 0x1F));
    }

    /**
     * Reset every module in the matrix to white.
     */
    public function clear(): void
    {
        $max = count($this->bits);

        for ($i = 0; $i < $max; ++$i) {
            $this->bits[$i] = 0;
        }
    }

    /**
     * Set every module in the rectangular region to black.
     *
     * @throws InvalidArgumentException if left or top are negative
     * @throws InvalidArgumentException if region does not fit into the matix
     * @throws InvalidArgumentException if width or height are smaller than 1
     */
    public function setRegion(int $left, int $top, int $width, int $height): void
    {
        if ($top < 0 || $left < 0) {
            throw InvalidArgumentException::withMessage('Left and top must be non-negative');
        }

        if ($height < 1 || $width < 1) {
            throw InvalidArgumentException::withMessage('Width and height must be at least 1');
        }

        $right = $left + $width;
        $bottom = $top + $height;

        if ($bottom > $this->height || $right > $this->width) {
            throw InvalidArgumentException::withMessage('The region must fit inside the matrix');
        }

        for ($y = $top; $y < $bottom; ++$y) {
            $offset = $y * $this->rowSize;

            for ($x = $left; $x < $right; ++$x) {
                $index = $offset + ($x >> 5);
                $this->bits[$index] = $this->bits[$index] | (1 << ($x & 0x1F));
            }
        }
    }

    /**
     * Extract one row as a packed bit array.
     *
     * The optional buffer lets callers reuse an existing `BitArray` when they
     * are walking many rows in sequence.
     */
    public function getRow(int $y, ?BitArray $row = null): BitArray
    {
        if (null === $row || $row->getSize() < $this->width) {
            $row = new BitArray($this->width);
        }

        $offset = $y * $this->rowSize;

        for ($x = 0; $x < $this->rowSize; ++$x) {
            $row->setBulk($x << 5, $this->bits[$offset + $x]);
        }

        return $row;
    }

    /**
     * Replace one matrix row with packed bit data from a `BitArray`.
     */
    public function setRow(int $y, BitArray $row): void
    {
        $bits = $row->getBitArray();

        for ($i = 0; $i < $this->rowSize; ++$i) {
            $this->bits[$y * $this->rowSize + $i] = $bits[$i];
        }
    }

    /**
     * Return the bounding box of the black modules, if one exists.
     *
     * @return null|array<int>
     */
    public function getEnclosingRectangle(): ?array
    {
        $left = $this->width;
        $top = $this->height;
        $right = -1;
        $bottom = -1;

        for ($y = 0; $y < $this->height; ++$y) {
            for ($x32 = 0; $x32 < $this->rowSize; ++$x32) {
                $bits = $this->bits[$y * $this->rowSize + $x32];

                if (0 !== $bits) {
                    if ($y < $top) {
                        $top = $y;
                    }

                    if ($y > $bottom) {
                        $bottom = $y;
                    }

                    if ($x32 * 32 < $left) {
                        $bit = 0;

                        while (($bits << (31 - $bit)) === 0) {
                            ++$bit;
                        }

                        if (($x32 * 32 + $bit) < $left) {
                            $left = $x32 * 32 + $bit;
                        }
                    }
                }

                if ($x32 * 32 + 31 <= $right) {
                    continue;
                }

                $bit = 31;

                while (0 === BitUtils::unsignedRightShift($bits, $bit)) {
                    --$bit;
                }

                if (($x32 * 32 + $bit) <= $right) {
                    continue;
                }

                $right = $x32 * 32 + $bit;
            }
        }

        $width = $right - $left;
        $height = $bottom - $top;

        if ($width < 0 || $height < 0) {
            return null;
        }

        return [$left, $top, $width, $height];
    }

    /**
     * Return the top-left black module, if one exists.
     *
     * @return null|array<int>
     */
    public function getTopLeftOnBit(): ?array
    {
        $bitsOffset = 0;

        while ($bitsOffset < count($this->bits) && 0 === $this->bits[$bitsOffset]) {
            ++$bitsOffset;
        }

        if (count($this->bits) === $bitsOffset) {
            return null;
        }

        $x = intdiv($bitsOffset, $this->rowSize);
        $y = ($bitsOffset % $this->rowSize) << 5;

        $bits = $this->bits[$bitsOffset];
        $bit = 0;

        while (0 === ($bits << (31 - $bit))) {
            ++$bit;
        }

        $x += $bit;

        return [$x, $y];
    }

    /**
     * Return the bottom-right black module, if one exists.
     *
     * @return null|array<int>
     */
    public function getBottomRightOnBit(): ?array
    {
        $bitsOffset = count($this->bits) - 1;

        while ($bitsOffset >= 0 && 0 === $this->bits[$bitsOffset]) {
            --$bitsOffset;
        }

        if ($bitsOffset < 0) {
            return null;
        }

        $x = intdiv($bitsOffset, $this->rowSize);
        $y = ($bitsOffset % $this->rowSize) << 5;

        $bits = $this->bits[$bitsOffset];
        $bit = 0;

        while (0 === BitUtils::unsignedRightShift($bits, $bit)) {
            --$bit;
        }

        $x += $bit;

        return [$x, $y];
    }

    /**
     * Return the matrix width in modules.
     */
    public function getWidth(): int
    {
        return $this->width;
    }

    /**
     * Return the matrix height in modules.
     */
    public function getHeight(): int
    {
        return $this->height;
    }
}
