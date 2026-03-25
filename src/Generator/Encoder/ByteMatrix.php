<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Generator\Encoder;

use SplFixedArray;
use Stringable;
use Traversable;

use function array_fill;

/**
 * Mutable rectangular byte grid used while assembling the final symbol.
 *
 * The matrix stores raw module values and sentinel `-1` cells while the
 * encoder is laying out finder patterns, timing rows, and data bits. It is a
 * low-level staging structure rather than a public presentation type.
 * @author Brian Faust <brian@cline.sh>
 */
final class ByteMatrix implements Stringable
{
    /**
     * Bytes in the matrix, represented as array.
     *
     * @var SplFixedArray<SplFixedArray<int>>
     */
    private SplFixedArray $bytes;

    public function __construct(
        private readonly int $width,
        private readonly int $height,
    ) {
        $this->bytes = new SplFixedArray($height);

        for ($y = 0; $y < $height; ++$y) {
            $this->bytes[$y] = SplFixedArray::fromArray(array_fill(0, $width, 0));
        }
    }

    public function __clone()
    {
        $this->bytes = clone $this->bytes;

        foreach ($this->bytes as $index => $row) {
            $this->bytes[$index] = clone $row;
        }
    }

    /**
     * Render the matrix as a debugging string.
     */
    public function __toString(): string
    {
        $result = '';

        for ($y = 0; $y < $this->height; ++$y) {
            for ($x = 0; $x < $this->width; ++$x) {
                switch ($this->bytes[$y][$x]) {
                    case 0:
                        $result .= ' 0';

                        break;

                    case 1:
                        $result .= ' 1';

                        break;

                    default:
                        $result .= '  ';

                        break;
                }
            }

            $result .= "\n";
        }

        return $result;
    }

    /**
     * Return the matrix width in cells.
     */
    public function getWidth(): int
    {
        return $this->width;
    }

    /**
     * Return the matrix height in cells.
     */
    public function getHeight(): int
    {
        return $this->height;
    }

    /**
     * Return the internal row storage.
     *
     * @return SplFixedArray<SplFixedArray<int>>
     */
    public function getArray(): SplFixedArray
    {
        return $this->bytes;
    }

    /**
     * Iterate over every stored byte in row-major order.
     *
     * @return Traversable<int>
     */
    public function getBytes(): Traversable
    {
        foreach ($this->bytes as $row) {
            foreach ($row as $byte) {
                yield $byte;
            }
        }
    }

    /**
     * Read the byte at the supplied coordinate.
     */
    public function get(int $x, int $y): int
    {
        return $this->bytes[$y][$x];
    }

    /**
     * Write the byte at the supplied coordinate.
     */
    public function set(int $x, int $y, int $value): void
    {
        $this->bytes[$y][$x] = $value;
    }

    /**
     * Fill every cell in the matrix with the same byte value.
     */
    public function clear(int $value): void
    {
        for ($y = 0; $y < $this->height; ++$y) {
            for ($x = 0; $x < $this->width; ++$x) {
                $this->bytes[$y][$x] = $value;
            }
        }
    }
}
