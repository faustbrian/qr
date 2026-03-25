<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Matrix;

/**
 * Contract for render-ready QR matrices.
 *
 * Writers consume this interface after the encoder has already produced the raw
 * block values and the package has derived final sizing and margin geometry.
 *
 * @author Brian Faust <brian@cline.sh>
 */
interface MatrixInterface
{
    /**
     * Return the value of one block cell.
     */
    public function getBlockValue(int $rowIndex, int $columnIndex): int;

    /**
     * Return the number of blocks on one side of the matrix.
     */
    public function getBlockCount(): int;

    /**
     * Return the final rendered block size.
     */
    public function getBlockSize(): float;

    /**
     * Return the size of the QR content area without outer margin.
     */
    public function getInnerSize(): int;

    /**
     * Return the total output size including margin.
     */
    public function getOuterSize(): int;

    /**
     * Return the computed left margin.
     */
    public function getMarginLeft(): int;

    /**
     * Return the computed right margin.
     */
    public function getMarginRight(): int;
}
