<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Generator\Encoder;

use Cline\Qr\Generator\Common\BitArray;
use Cline\Qr\Generator\Common\ErrorCorrectionLevel;
use Cline\Qr\Generator\Common\Version;
use Cline\Qr\Generator\Internal\Exception\RuntimeException;
use Cline\Qr\Generator\Internal\Exception\WriterException;

use function count;

/**
 * Lay out the final QR matrix from payload bits and symbol metadata.
 *
 * This class reserves finder, timing, alignment, version, and format areas
 * before writing data bits into the remaining empty cells. It is intentionally
 * procedural because the encoder needs a single, predictable layout pass.
 * @author Brian Faust <brian@cline.sh>
 */
final class MatrixUtil
{
    /**
     * 7x7 finder pattern used in the corners of every QR symbol.
     */
    private const array POSITION_DETECTION_PATTERN = [
        [1, 1, 1, 1, 1, 1, 1],
        [1, 0, 0, 0, 0, 0, 1],
        [1, 0, 1, 1, 1, 0, 1],
        [1, 0, 1, 1, 1, 0, 1],
        [1, 0, 1, 1, 1, 0, 1],
        [1, 0, 0, 0, 0, 0, 1],
        [1, 1, 1, 1, 1, 1, 1],
    ];

    /**
     * 5x5 alignment pattern used by versions that need internal anchors.
     */
    private const array POSITION_ADJUSTMENT_PATTERN = [
        [1, 1, 1, 1, 1],
        [1, 0, 0, 0, 1],
        [1, 0, 1, 0, 1],
        [1, 0, 0, 0, 1],
        [1, 1, 1, 1, 1],
    ];

    /**
     * Alignment-pattern coordinates for each QR version.
     */
    private const array POSITION_ADJUSTMENT_PATTERN_COORDINATE_TABLE = [
        [null, null, null, null, null, null, null], // Version 1
        [6,   18, null, null, null, null, null], // Version 2
        [6,   22, null, null, null, null, null], // Version 3
        [6,   26, null, null, null, null, null], // Version 4
        [6,   30, null, null, null, null, null], // Version 5
        [6,   34, null, null, null, null, null], // Version 6
        [6,   22,   38, null, null, null, null], // Version 7
        [6,   24,   42, null, null, null, null], // Version 8
        [6,   26,   46, null, null, null, null], // Version 9
        [6,   28,   50, null, null, null, null], // Version 10
        [6,   30,   54, null, null, null, null], // Version 11
        [6,   32,   58, null, null, null, null], // Version 12
        [6,   34,   62, null, null, null, null], // Version 13
        [6,   26,   46,   66, null, null, null], // Version 14
        [6,   26,   48,   70, null, null, null], // Version 15
        [6,   26,   50,   74, null, null, null], // Version 16
        [6,   30,   54,   78, null, null, null], // Version 17
        [6,   30,   56,   82, null, null, null], // Version 18
        [6,   30,   58,   86, null, null, null], // Version 19
        [6,   34,   62,   90, null, null, null], // Version 20
        [6,   28,   50,   72,   94, null, null], // Version 21
        [6,   26,   50,   74,   98, null, null], // Version 22
        [6,   30,   54,   78,  102, null, null], // Version 23
        [6,   28,   54,   80,  106, null, null], // Version 24
        [6,   32,   58,   84,  110, null, null], // Version 25
        [6,   30,   58,   86,  114, null, null], // Version 26
        [6,   34,   62,   90,  118, null, null], // Version 27
        [6,   26,   50,   74,   98,  122, null], // Version 28
        [6,   30,   54,   78,  102,  126, null], // Version 29
        [6,   26,   52,   78,  104,  130, null], // Version 30
        [6,   30,   56,   82,  108,  134, null], // Version 31
        [6,   34,   60,   86,  112,  138, null], // Version 32
        [6,   30,   58,   86,  114,  142, null], // Version 33
        [6,   34,   62,   90,  118,  146, null], // Version 34
        [6,   30,   54,   78,  102,  126,  150], // Version 35
        [6,   24,   50,   76,  102,  128,  154], // Version 36
        [6,   28,   54,   80,  106,  132,  158], // Version 37
        [6,   32,   58,   84,  110,  136,  162], // Version 38
        [6,   26,   54,   82,  110,  138,  166], // Version 39
        [6,   30,   58,   86,  114,  142,  170], // Version 40
    ];

    /**
     * Coordinates used to write the 15-bit format information field.
     */
    private const array TYPE_INFO_COORDINATES = [
        [8, 0],
        [8, 1],
        [8, 2],
        [8, 3],
        [8, 4],
        [8, 5],
        [8, 7],
        [8, 8],
        [7, 8],
        [5, 8],
        [4, 8],
        [3, 8],
        [2, 8],
        [1, 8],
        [0, 8],
    ];

    /**
     * BCH polynomial for version information.
     */
    private const int VERSION_INFO_POLY = 0x1F_25;

    /**
     * BCH polynomial for format information.
     */
    private const int TYPE_INFO_POLY = 0x5_37;

    /**
     * Mask applied to format information bits after BCH encoding.
     */
    private const int TYPE_INFO_MASK_PATTERN = 0x54_12;

    /**
     * Reset the matrix to the unset sentinel used during layout.
     */
    public static function clearMatrix(ByteMatrix $matrix): void
    {
        $matrix->clear(-1);
    }

    /**
     * Build the full QR matrix in place.
     */
    public static function buildMatrix(
        BitArray $dataBits,
        ErrorCorrectionLevel $level,
        Version $version,
        int $maskPattern,
        ByteMatrix $matrix,
    ): void {
        self::clearMatrix($matrix);
        self::embedBasicPatterns($version, $matrix);
        self::embedTypeInfo($level, $maskPattern, $matrix);
        self::maybeEmbedVersionInfo($version, $matrix);
        self::embedDataBits($dataBits, $maskPattern, $matrix);
    }

    /**
     * Clear the finder-pattern regions back to the unset sentinel.
     *
     * This is mainly useful when a caller wants to render the timing or data
     * layers separately from the position-detection artwork.
     */
    public static function removePositionDetectionPatterns(ByteMatrix $matrix): void
    {
        $pdpWidth = count(self::POSITION_DETECTION_PATTERN[0]);

        self::removePositionDetectionPattern(0, 0, $matrix);
        self::removePositionDetectionPattern($matrix->getWidth() - $pdpWidth, 0, $matrix);
        self::removePositionDetectionPattern(0, $matrix->getWidth() - $pdpWidth, $matrix);
    }

    /**
     * Write the format information bits to both mirrored locations.
     */
    private static function embedTypeInfo(ErrorCorrectionLevel $level, int $maskPattern, ByteMatrix $matrix): void
    {
        $typeInfoBits = new BitArray();
        self::makeTypeInfoBits($level, $maskPattern, $typeInfoBits);

        $typeInfoBitsSize = $typeInfoBits->getSize();

        for ($i = 0; $i < $typeInfoBitsSize; ++$i) {
            $bit = $typeInfoBits->get($typeInfoBitsSize - 1 - $i);

            $x1 = self::TYPE_INFO_COORDINATES[$i][0];
            $y1 = self::TYPE_INFO_COORDINATES[$i][1];

            $matrix->set($x1, $y1, (int) $bit);

            if ($i < 8) {
                $x2 = $matrix->getWidth() - $i - 1;
                $y2 = 8;
            } else {
                $x2 = 8;
                $y2 = $matrix->getHeight() - 7 + ($i - 8);
            }

            $matrix->set($x2, $y2, (int) $bit);
        }
    }

    /**
     * Build the 15-bit format information payload.
     *
     * @throws RuntimeException if bit array resulted in invalid size
     */
    private static function makeTypeInfoBits(ErrorCorrectionLevel $level, int $maskPattern, BitArray $bits): void
    {
        $typeInfo = ($level->getBits() << 3) | $maskPattern;
        $bits->appendBits($typeInfo, 5);

        $bchCode = self::calculateBchCode($typeInfo, self::TYPE_INFO_POLY);
        $bits->appendBits($bchCode, 10);

        $maskBits = new BitArray();
        $maskBits->appendBits(self::TYPE_INFO_MASK_PATTERN, 15);
        $bits->xorBits($maskBits);

        if (15 !== $bits->getSize()) {
            throw RuntimeException::withMessage('Bit array resulted in invalid size: '.$bits->getSize());
        }
    }

    /**
     * Write version information when the symbol is large enough to require it.
     */
    private static function maybeEmbedVersionInfo(Version $version, ByteMatrix $matrix): void
    {
        if ($version->getVersionNumber() < 7) {
            return;
        }

        $versionInfoBits = new BitArray();
        self::makeVersionInfoBits($version, $versionInfoBits);

        $bitIndex = 6 * 3 - 1;

        for ($i = 0; $i < 6; ++$i) {
            for ($j = 0; $j < 3; ++$j) {
                $bit = $versionInfoBits->get($bitIndex);
                --$bitIndex;

                $matrix->set($i, $matrix->getHeight() - 11 + $j, (int) $bit);
                $matrix->set($matrix->getHeight() - 11 + $j, $i, (int) $bit);
            }
        }
    }

    /**
     * Build the 18-bit version information payload.
     *
     * @throws RuntimeException if bit array resulted in invalid size
     */
    private static function makeVersionInfoBits(Version $version, BitArray $bits): void
    {
        $bits->appendBits($version->getVersionNumber(), 6);

        $bchCode = self::calculateBchCode($version->getVersionNumber(), self::VERSION_INFO_POLY);
        $bits->appendBits($bchCode, 12);

        if (18 !== $bits->getSize()) {
            throw RuntimeException::withMessage('Bit array resulted in invalid size: '.$bits->getSize());
        }
    }

    /**
     * Calculate the BCH remainder for the supplied value and polynomial.
     */
    private static function calculateBchCode(int $value, int $poly): int
    {
        $msbSetInPoly = self::findMsbSet($poly);
        $value <<= $msbSetInPoly - 1;

        while (self::findMsbSet($value) >= $msbSetInPoly) {
            $value ^= $poly << (self::findMsbSet($value) - $msbSetInPoly);
        }

        return $value;
    }

    /**
     * Return the position of the most significant set bit.
     */
    private static function findMsbSet(int $value): int
    {
        $numDigits = 0;

        while (0 !== $value) {
            $value >>= 1;
            ++$numDigits;
        }

        return $numDigits;
    }

    /**
     * Lay out the fixed function patterns that every symbol needs.
     */
    private static function embedBasicPatterns(Version $version, ByteMatrix $matrix): void
    {
        self::embedPositionDetectionPatternsAndSeparators($matrix);
        self::embedDarkDotAtLeftBottomCorner($matrix);
        self::maybeEmbedPositionAdjustmentPatterns($version, $matrix);
        self::embedTimingPatterns($matrix);
    }

    /**
     * Write finder patterns and their separators into the matrix.
     */
    private static function embedPositionDetectionPatternsAndSeparators(ByteMatrix $matrix): void
    {
        $pdpWidth = count(self::POSITION_DETECTION_PATTERN[0]);

        self::embedPositionDetectionPattern(0, 0, $matrix);
        self::embedPositionDetectionPattern($matrix->getWidth() - $pdpWidth, 0, $matrix);
        self::embedPositionDetectionPattern(0, $matrix->getWidth() - $pdpWidth, $matrix);

        $hspWidth = 8;

        self::embedHorizontalSeparationPattern(0, $hspWidth - 1, $matrix);
        self::embedHorizontalSeparationPattern($matrix->getWidth() - $hspWidth, $hspWidth - 1, $matrix);
        self::embedHorizontalSeparationPattern(0, $matrix->getWidth() - $hspWidth, $matrix);

        $vspSize = 7;

        self::embedVerticalSeparationPattern($vspSize, 0, $matrix);
        self::embedVerticalSeparationPattern($matrix->getHeight() - $vspSize - 1, 0, $matrix);
        self::embedVerticalSeparationPattern($vspSize, $matrix->getHeight() - $vspSize, $matrix);
    }

    /**
     * Write one finder pattern into the matrix.
     */
    private static function embedPositionDetectionPattern(int $xStart, int $yStart, ByteMatrix $matrix): void
    {
        for ($y = 0; $y < 7; ++$y) {
            for ($x = 0; $x < 7; ++$x) {
                $matrix->set($xStart + $x, $yStart + $y, self::POSITION_DETECTION_PATTERN[$y][$x]);
            }
        }
    }

    /**
     * Clear one finder pattern region back to the unset sentinel.
     */
    private static function removePositionDetectionPattern(int $xStart, int $yStart, ByteMatrix $matrix): void
    {
        for ($y = 0; $y < 7; ++$y) {
            for ($x = 0; $x < 7; ++$x) {
                $matrix->set($xStart + $x, $yStart + $y, 0);
            }
        }
    }

    /**
     * Write one horizontal separator row.
     *
     * @throws RuntimeException if a byte was already set
     */
    private static function embedHorizontalSeparationPattern(int $xStart, int $yStart, ByteMatrix $matrix): void
    {
        for ($x = 0; $x < 8; ++$x) {
            if (-1 !== $matrix->get($xStart + $x, $yStart)) {
                throw RuntimeException::withMessage('Byte already set');
            }

            $matrix->set($xStart + $x, $yStart, 0);
        }
    }

    /**
     * Write one vertical separator column.
     *
     * @throws RuntimeException if a byte was already set
     */
    private static function embedVerticalSeparationPattern(int $xStart, int $yStart, ByteMatrix $matrix): void
    {
        for ($y = 0; $y < 7; ++$y) {
            if (-1 !== $matrix->get($xStart, $yStart + $y)) {
                throw RuntimeException::withMessage('Byte already set');
            }

            $matrix->set($xStart, $yStart + $y, 0);
        }
    }

    /**
     * Set the fixed dark module in the lower-left corner.
     *
     * @throws RuntimeException if a byte was already set to 0
     */
    private static function embedDarkDotAtLeftBottomCorner(ByteMatrix $matrix): void
    {
        if (0 === $matrix->get(8, $matrix->getHeight() - 8)) {
            throw RuntimeException::withMessage('Byte already set to 0');
        }

        $matrix->set(8, $matrix->getHeight() - 8, 1);
    }

    /**
     * Write alignment patterns when the version requires them.
     */
    private static function maybeEmbedPositionAdjustmentPatterns(Version $version, ByteMatrix $matrix): void
    {
        if ($version->getVersionNumber() < 2) {
            return;
        }

        $index = $version->getVersionNumber() - 1;

        $coordinates = self::POSITION_ADJUSTMENT_PATTERN_COORDINATE_TABLE[$index];
        $numCoordinates = count($coordinates);

        for ($i = 0; $i < $numCoordinates; ++$i) {
            for ($j = 0; $j < $numCoordinates; ++$j) {
                $y = $coordinates[$i];
                $x = $coordinates[$j];

                if (null === $x || null === $y) {
                    continue;
                }

                if (-1 !== $matrix->get($x, $y)) {
                    continue;
                }

                self::embedPositionAdjustmentPattern($x - 2, $y - 2, $matrix);
            }
        }
    }

    /**
     * Write one alignment pattern into the matrix.
     */
    private static function embedPositionAdjustmentPattern(int $xStart, int $yStart, ByteMatrix $matrix): void
    {
        for ($y = 0; $y < 5; ++$y) {
            for ($x = 0; $x < 5; ++$x) {
                $matrix->set($xStart + $x, $yStart + $y, self::POSITION_ADJUSTMENT_PATTERN[$y][$x]);
            }
        }
    }

    /**
     * Write the alternating timing rows and columns.
     */
    private static function embedTimingPatterns(ByteMatrix $matrix): void
    {
        $matrixWidth = $matrix->getWidth();

        for ($i = 8; $i < $matrixWidth - 8; ++$i) {
            $bit = ($i + 1) % 2;

            if (-1 === $matrix->get($i, 6)) {
                $matrix->set($i, 6, $bit);
            }

            if (-1 !== $matrix->get(6, $i)) {
                continue;
            }

            $matrix->set(6, $i, $bit);
        }
    }

    /**
     * Stream payload bits into the remaining unset matrix cells.
     *
     * When the mask pattern is `-1`, the method leaves the data unmasked so the
     * matrix can be inspected or debugged before final scoring.
     *
     * @throws WriterException if not all bits could be consumed
     */
    private static function embedDataBits(BitArray $dataBits, int $maskPattern, ByteMatrix $matrix): void
    {
        $bitIndex = 0;
        $direction = -1;

        // Start from the right bottom cell.
        $x = $matrix->getWidth() - 1;
        $y = $matrix->getHeight() - 1;

        while ($x > 0) {
            // Skip vertical timing pattern.
            if (6 === $x) {
                --$x;
            }

            while ($y >= 0 && $y < $matrix->getHeight()) {
                for ($i = 0; $i < 2; ++$i) {
                    $xx = $x - $i;

                    // Skip the cell if it's not empty.
                    if (-1 !== $matrix->get($xx, $y)) {
                        continue;
                    }

                    if ($bitIndex < $dataBits->getSize()) {
                        $bit = $dataBits->get($bitIndex);
                        ++$bitIndex;
                    } else {
                        // Padding bit. If there is no bit left, we'll fill the
                        // left cells with 0, as described in 8.4.9 of
                        // JISX0510:2004 (p. 24).
                        $bit = false;
                    }

                    // Skip masking if maskPattern is -1.
                    if (-1 !== $maskPattern && MaskUtil::getDataMaskBit($maskPattern, $xx, $y)) {
                        $bit = !$bit;
                    }

                    $matrix->set($xx, $y, (int) $bit);
                }

                $y += $direction;
            }

            $direction = -$direction;
            $y += $direction;
            $x -= 2;
        }

        // All bits should be consumed
        if ($dataBits->getSize() !== $bitIndex) {
            throw WriterException::withMessage('Not all bits consumed ('.$bitIndex.' out of '.$dataBits->getSize().')');
        }
    }
}
