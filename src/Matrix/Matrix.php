<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Matrix;

use Cline\Qr\Exception\BlockSizeTooSmallException;
use Cline\Qr\RoundBlockSizeMode;

use function ceil;
use function count;
use function floor;
use function throw_if;

/**
 * Render-ready QR block matrix with derived sizing and margins.
 *
 * This value object adapts the raw block values produced by the generator to
 * the dimensions requested by the public API. It computes the final block size,
 * inner size, outer size, and left/right margins based on the selected block
 * rounding strategy.
 *
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class Matrix implements MatrixInterface
{
    private float $blockSize;

    private int $innerSize;

    private int $outerSize;

    private int $marginLeft;

    private int $marginRight;

    /**
     * @param array<array<int<0, 1>>> $blockValues
     */
    public function __construct(
        private array $blockValues,
        int $size,
        int $margin,
        RoundBlockSizeMode $roundBlockSizeMode,
    ) {
        $blockSize = $size / $this->getBlockCount();
        $innerSize = $size;
        $outerSize = $size + 2 * $margin;

        switch ($roundBlockSizeMode) {
            case RoundBlockSizeMode::Enlarge:
                $blockSize = (int) ceil($blockSize);
                $innerSize = $blockSize * $this->getBlockCount();
                $outerSize = $innerSize + 2 * $margin;

                break;

            case RoundBlockSizeMode::Shrink:
                $blockSize = (int) floor($blockSize);
                $innerSize = $blockSize * $this->getBlockCount();
                $outerSize = $innerSize + 2 * $margin;

                break;

            case RoundBlockSizeMode::Margin:
                $blockSize = (int) floor($blockSize);
                $innerSize = $blockSize * $this->getBlockCount();

                break;
        }

        throw_if($blockSize < 1, BlockSizeTooSmallException::dueToDataDensity());

        $this->blockSize = $blockSize;
        $this->innerSize = $innerSize;
        $this->outerSize = $outerSize;
        $this->marginLeft = (int) (($this->outerSize - $this->innerSize) / 2);
        $this->marginRight = $this->outerSize - $this->innerSize - $this->marginLeft;
    }

    /**
     * Return the stored value for one block cell.
     */
    public function getBlockValue(int $rowIndex, int $columnIndex): int
    {
        return $this->blockValues[$rowIndex][$columnIndex];
    }

    /**
     * Return the number of blocks on one side of the square matrix.
     */
    public function getBlockCount(): int
    {
        return count($this->blockValues[0]);
    }

    /**
     * Return the final rendered block size after rounding adjustments.
     */
    public function getBlockSize(): float
    {
        return $this->blockSize;
    }

    /**
     * Return the size of the QR content area without outer quiet-zone padding.
     */
    public function getInnerSize(): int
    {
        return $this->innerSize;
    }

    /**
     * Return the total output size including margins.
     */
    public function getOuterSize(): int
    {
        return $this->outerSize;
    }

    /**
     * Return the computed left margin.
     */
    public function getMarginLeft(): int
    {
        return $this->marginLeft;
    }

    /**
     * Return the computed right margin.
     */
    public function getMarginRight(): int
    {
        return $this->marginRight;
    }
}
