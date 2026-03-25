<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Decoder\Qrcode\Decoder;

use Cline\Qr\Decoder\InvalidArgumentException;

use function count;
use function fill_array;
use function is_countable;

/**
 * Represents one de-interleaved QR data block.
 *
 * QR symbols distribute their payload across multiple blocks so error
 * correction can recover from localized damage. Each instance stores the data
 * codeword count for that block and the full block contents, including error
 * correction bytes.
 *
 * @author Sean Owen
 */
final class DataBlock
{
    // byte[]

    private function __construct(
        private $numDataCodewords,
        private $codewords,
    ) {}

    /**
     * Split interleaved codewords back into their original data blocks.
     *
     * The QR encoding order writes one byte from each block in turn, then loops
     * over the second byte, and so on. This method restores the original block
     * boundaries so Reed-Solomon correction can run on each block independently.
     *
     * @param  array<int, int>      $rawCodewords Raw bytes read directly from the symbol.
     * @param  Version              $version      QR version metadata.
     * @param  ErrorCorrectionLevel $ecLevel      Selected error-correction level.
     * @return array<int, self>     The restored blocks.
     */
    public static function getDataBlocks(
        $rawCodewords,
        Version $version,
        ErrorCorrectionLevel $ecLevel,
    ): array {
        if ((is_countable($rawCodewords) ? count($rawCodewords) : 0) !== $version->getTotalCodewords()) {
            throw InvalidArgumentException::withMessage();
        }

        // Figure out the number and size of data blocks used by this version and
        // error correction level
        $ecBlocks = $version->getECBlocksForLevel($ecLevel);

        // First count the total number of data blocks
        $totalBlocks = 0;
        $ecBlockArray = $ecBlocks->getECBlocks();

        foreach ($ecBlockArray as $ecBlock) {
            $totalBlocks += $ecBlock->getCount();
        }

        // Now establish DataBlocks of the appropriate size and number of data codewords
        $result = []; // new DataBlock[$totalBlocks];
        $numResultBlocks = 0;

        foreach ($ecBlockArray as $ecBlock) {
            $ecBlockCount = $ecBlock->getCount();

            for ($i = 0; $i < $ecBlockCount; ++$i) {
                $numDataCodewords = $ecBlock->getDataCodewords();
                $numBlockCodewords = $ecBlocks->getECCodewordsPerBlock() + $numDataCodewords;
                $result[$numResultBlocks++] = new self($numDataCodewords, fill_array(0, $numBlockCodewords, 0));
            }
        }

        // All blocks have the same amount of data, except that the last n
        // (where n may be 0) have 1 more byte. Figure out where these start.
        $shorterBlocksTotalCodewords = is_countable($result[0]->codewords) ? count($result[0]->codewords) : 0;
        $longerBlocksStartAt = count($result) - 1;

        while ($longerBlocksStartAt >= 0) {
            $numCodewords = is_countable($result[$longerBlocksStartAt]->codewords) ? count($result[$longerBlocksStartAt]->codewords) : 0;

            if ($numCodewords === $shorterBlocksTotalCodewords) {
                break;
            }
            --$longerBlocksStartAt;
        }
        ++$longerBlocksStartAt;

        $shorterBlocksNumDataCodewords = $shorterBlocksTotalCodewords - $ecBlocks->getECCodewordsPerBlock();
        // The last elements of result may be 1 element longer;
        // first fill out as many elements as all of them have
        $rawCodewordsOffset = 0;

        for ($i = 0; $i < $shorterBlocksNumDataCodewords; ++$i) {
            for ($j = 0; $j < $numResultBlocks; ++$j) {
                $result[$j]->codewords[$i] = $rawCodewords[$rawCodewordsOffset++];
            }
        }

        // Fill out the last data block in the longer ones
        for ($j = $longerBlocksStartAt; $j < $numResultBlocks; ++$j) {
            $result[$j]->codewords[$shorterBlocksNumDataCodewords] = $rawCodewords[$rawCodewordsOffset++];
        }
        // Now add in error correction blocks
        $max = is_countable($result[0]->codewords) ? count($result[0]->codewords) : 0;

        for ($i = $shorterBlocksNumDataCodewords; $i < $max; ++$i) {
            for ($j = 0; $j < $numResultBlocks; ++$j) {
                $iOffset = $j < $longerBlocksStartAt ? $i : $i + 1;
                $result[$j]->codewords[$iOffset] = $rawCodewords[$rawCodewordsOffset++];
            }
        }

        return $result;
    }

    /**
     * @return int Number of data codewords in this block.
     */
    public function getNumDataCodewords()
    {
        return $this->numDataCodewords;
    }

    /**
     * @return array<int, int> Full block contents, including error-correction bytes.
     */
    public function getCodewords()
    {
        return $this->codewords;
    }
}
