<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Writer\Result;

/**
 * Result wrapper for newline-delimited matrix dumps.
 *
 * This format is mainly intended for debugging or for consumers that want a
 * simple textual representation of the final matrix.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class BinaryResult extends AbstractResult
{
    /**
     * Serialize the matrix as lines of `0` and `1` characters.
     */
    public function getString(): string
    {
        $matrix = $this->getMatrix();

        $binaryString = '';

        for ($rowIndex = 0; $rowIndex < $matrix->getBlockCount(); ++$rowIndex) {
            for ($columnIndex = 0; $columnIndex < $matrix->getBlockCount(); ++$columnIndex) {
                $binaryString .= $matrix->getBlockValue($rowIndex, $columnIndex);
            }

            $binaryString .= "\n";
        }

        return $binaryString;
    }

    /**
     * Return the mime type for the textual matrix dump.
     */
    public function getMimeType(): string
    {
        return 'text/plain';
    }
}
