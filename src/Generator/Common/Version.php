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

use const PHP_INT_MAX;

use function count;
use function intdiv;

/**
 * Immutable metadata for one QR version.
 *
 * A version captures the symbol dimension, alignment-pattern layout, and the
 * error-correction block structure for each correction level. The class also
 * caches the 40 legal instances and exposes the reserved function pattern used
 * while building or decoding a matrix.
 * @author Brian Faust <brian@cline.sh>
 */
final class Version implements Stringable
{
    private const array VERSION_DECODE_INFO = [
        0x0_7C_94,
        0x0_85_BC,
        0x0_9A_99,
        0x0_A4_D3,
        0x0_BB_F6,
        0x0_C7_62,
        0x0_D8_47,
        0x0_E6_0D,
        0x0_F9_28,
        0x1_0B_78,
        0x1_14_5D,
        0x1_2A_17,
        0x1_35_32,
        0x1_49_A6,
        0x1_56_83,
        0x1_68_C9,
        0x1_77_EC,
        0x1_8E_C4,
        0x1_91_E1,
        0x1_AF_AB,
        0x1_B0_8E,
        0x1_CC_1A,
        0x1_D3_3F,
        0x1_ED_75,
        0x1_F2_50,
        0x2_09_D5,
        0x2_16_F0,
        0x2_28_BA,
        0x2_37_9F,
        0x2_4B_0B,
        0x2_54_2E,
        0x2_6A_64,
        0x2_75_41,
        0x2_8C_69,
    ];

    /**
     * Cached version instances.
     *
     * @var null|array<int, self>
     */
    private static ?array $versions = null;

    /**
     * Alignment pattern centers.
     */
    private SplFixedArray|array $alignmentPatternCenters;

    /**
     * Error correction blocks.
     *
     * @var array<EcBlocks>
     */
    private array $ecBlocks;

    /**
     * Total number of codewords.
     */
    private null|int|float $totalCodewords;

    /**
     * @param array<int> $alignmentPatternCenters
     */
    private function __construct(
        /**
         * Version number of this version.
         */
        private readonly int $versionNumber,
        array $alignmentPatternCenters,
        EcBlocks ...$ecBlocks,
    ) {
        $this->alignmentPatternCenters = $alignmentPatternCenters;
        $this->ecBlocks = $ecBlocks;

        $totalCodewords = 0;
        $ecCodewords = $ecBlocks[0]->getEcCodewordsPerBlock();

        foreach ($ecBlocks[0]->getEcBlocks() as $ecBlock) {
            $totalCodewords += $ecBlock->getCount() * ($ecBlock->getDataCodewords() + $ecCodewords);
        }

        $this->totalCodewords = $totalCodewords;
    }

    /**
     * Return the numeric version as a string for debugging output.
     */
    public function __toString(): string
    {
        return (string) $this->versionNumber;
    }

    /**
     * Infer a version from a matrix dimension before full decode finishes.
     *
     * @throws InvalidArgumentException if dimension is not 1 mod 4
     */
    public static function getProvisionalVersionForDimension(int $dimension): self
    {
        if (1 !== $dimension % 4) {
            throw InvalidArgumentException::withMessage('Dimension is not 1 mod 4');
        }

        return self::getVersionForNumber(intdiv($dimension - 17, 4));
    }

    /**
     * Resolve a cached version instance by version number.
     *
     * @throws InvalidArgumentException if version number is out of range
     */
    public static function getVersionForNumber(int $versionNumber): self
    {
        if ($versionNumber < 1 || $versionNumber > 40) {
            throw InvalidArgumentException::withMessage('Version number must be between 1 and 40');
        }

        return self::versions()[$versionNumber - 1];
    }

    /**
     * Decode the compact version information payload embedded in symbols.
     *
     * The lookup tolerates up to three bit errors so mildly damaged symbols can
     * still resolve to a usable version.
     */
    public static function decodeVersionInformation(int $versionBits): ?self
    {
        $bestDifference = PHP_INT_MAX;
        $bestVersion = 0;

        foreach (self::VERSION_DECODE_INFO as $i => $targetVersion) {
            if ($targetVersion === $versionBits) {
                return self::getVersionForNumber($i + 7);
            }

            $bitsDifference = FormatInformation::numBitsDiffering($versionBits, $targetVersion);

            if ($bitsDifference >= $bestDifference) {
                continue;
            }

            $bestVersion = $i + 7;
            $bestDifference = $bitsDifference;
        }

        if ($bestDifference <= 3) {
            return self::getVersionForNumber($bestVersion);
        }

        return null;
    }

    /**
     * Return the numeric QR version, from 1 through 40.
     */
    public function getVersionNumber(): int
    {
        return $this->versionNumber;
    }

    /**
     * Return the alignment pattern centers for this version.
     *
     * @return array<int>
     */
    public function getAlignmentPatternCenters(): array
    {
        return $this->alignmentPatternCenters;
    }

    /**
     * Return the total number of codewords in the full version payload.
     */
    public function getTotalCodewords(): int
    {
        return $this->totalCodewords;
    }

    /**
     * Return the side length of the square QR matrix for this version.
     */
    public function getDimensionForVersion(): int
    {
        return 17 + 4 * $this->versionNumber;
    }

    /**
     * Return the error-correction layout for the supplied level.
     *
     * The version stores one block definition per correction level in the
     * ISO-defined `L`, `M`, `Q`, `H` order.
     */
    public function getEcBlocksForLevel(ErrorCorrectionLevel $ecLevel): EcBlocks
    {
        return $this->ecBlocks[match ($ecLevel) {
            ErrorCorrectionLevel::L => 0,
            ErrorCorrectionLevel::M => 1,
            ErrorCorrectionLevel::Q => 2,
            ErrorCorrectionLevel::H => 3,
        }];
    }

    /**
     * Build the reserved function pattern for this version.
     *
     * The returned matrix marks finder patterns, timing patterns, alignment
     * patterns, and version-information areas so the encoder can avoid placing
     * payload bits there.
     */
    public function buildFunctionPattern(): BitMatrix
    {
        $dimension = $this->getDimensionForVersion();
        $bitMatrix = new BitMatrix($dimension);

        // Top left finder pattern + separator + format
        $bitMatrix->setRegion(0, 0, 9, 9);
        // Top right finder pattern + separator + format
        $bitMatrix->setRegion($dimension - 8, 0, 8, 9);
        // Bottom left finder pattern + separator + format
        $bitMatrix->setRegion(0, $dimension - 8, 9, 8);

        // Alignment patterns
        $max = count($this->alignmentPatternCenters);

        for ($x = 0; $x < $max; ++$x) {
            $i = $this->alignmentPatternCenters[$x] - 2;

            for ($y = 0; $y < $max; ++$y) {
                if (($x === 0 && ($y === 0 || $y === $max - 1)) || ($x === $max - 1 && $y === 0)) {
                    // No alignment patterns near the three finder paterns
                    continue;
                }

                $bitMatrix->setRegion($this->alignmentPatternCenters[$y] - 2, $i, 5, 5);
            }
        }

        // Vertical timing pattern
        $bitMatrix->setRegion(6, 9, 1, $dimension - 17);
        // Horizontal timing pattern
        $bitMatrix->setRegion(9, 6, $dimension - 17, 1);

        if ($this->versionNumber > 6) {
            // Version info, top right
            $bitMatrix->setRegion($dimension - 11, 0, 3, 6);
            // Version info, bottom left
            $bitMatrix->setRegion(0, $dimension - 11, 6, 3);
        }

        return $bitMatrix;
    }

    /**
     * Return the cached table of all 40 version instances.
     *
     * The table is built lazily once and reused so callers do not pay the cost
     * of reconstructing the version metadata on every lookup.
     *
     * @return array<int, self>
     */
    private static function versions(): array
    {
        if (null !== self::$versions) {
            return self::$versions;
        }

        return self::$versions = [
            new self(
                1,
                [],
                new EcBlocks(7, new EcBlock(1, 19)),
                new EcBlocks(10, new EcBlock(1, 16)),
                new EcBlocks(13, new EcBlock(1, 13)),
                new EcBlocks(17, new EcBlock(1, 9)),
            ),
            new self(
                2,
                [6, 18],
                new EcBlocks(10, new EcBlock(1, 34)),
                new EcBlocks(16, new EcBlock(1, 28)),
                new EcBlocks(22, new EcBlock(1, 22)),
                new EcBlocks(28, new EcBlock(1, 16)),
            ),
            new self(
                3,
                [6, 22],
                new EcBlocks(15, new EcBlock(1, 55)),
                new EcBlocks(26, new EcBlock(1, 44)),
                new EcBlocks(18, new EcBlock(2, 17)),
                new EcBlocks(22, new EcBlock(2, 13)),
            ),
            new self(
                4,
                [6, 26],
                new EcBlocks(20, new EcBlock(1, 80)),
                new EcBlocks(18, new EcBlock(2, 32)),
                new EcBlocks(26, new EcBlock(2, 24)),
                new EcBlocks(16, new EcBlock(4, 9)),
            ),
            new self(
                5,
                [6, 30],
                new EcBlocks(26, new EcBlock(1, 108)),
                new EcBlocks(24, new EcBlock(2, 43)),
                new EcBlocks(18, new EcBlock(2, 15), new EcBlock(2, 16)),
                new EcBlocks(22, new EcBlock(2, 11), new EcBlock(2, 12)),
            ),
            new self(
                6,
                [6, 34],
                new EcBlocks(18, new EcBlock(2, 68)),
                new EcBlocks(16, new EcBlock(4, 27)),
                new EcBlocks(24, new EcBlock(4, 19)),
                new EcBlocks(28, new EcBlock(4, 15)),
            ),
            new self(
                7,
                [6, 22, 38],
                new EcBlocks(20, new EcBlock(2, 78)),
                new EcBlocks(18, new EcBlock(4, 31)),
                new EcBlocks(18, new EcBlock(2, 14), new EcBlock(4, 15)),
                new EcBlocks(26, new EcBlock(4, 13), new EcBlock(1, 14)),
            ),
            new self(
                8,
                [6, 24, 42],
                new EcBlocks(24, new EcBlock(2, 97)),
                new EcBlocks(22, new EcBlock(2, 38), new EcBlock(2, 39)),
                new EcBlocks(22, new EcBlock(4, 18), new EcBlock(2, 19)),
                new EcBlocks(26, new EcBlock(4, 14), new EcBlock(2, 15)),
            ),
            new self(
                9,
                [6, 26, 46],
                new EcBlocks(30, new EcBlock(2, 116)),
                new EcBlocks(22, new EcBlock(3, 36), new EcBlock(2, 37)),
                new EcBlocks(20, new EcBlock(4, 16), new EcBlock(4, 17)),
                new EcBlocks(24, new EcBlock(4, 12), new EcBlock(4, 13)),
            ),
            new self(
                10,
                [6, 28, 50],
                new EcBlocks(18, new EcBlock(2, 68), new EcBlock(2, 69)),
                new EcBlocks(26, new EcBlock(4, 43), new EcBlock(1, 44)),
                new EcBlocks(24, new EcBlock(6, 19), new EcBlock(2, 20)),
                new EcBlocks(28, new EcBlock(6, 15), new EcBlock(2, 16)),
            ),
            new self(
                11,
                [6, 30, 54],
                new EcBlocks(20, new EcBlock(4, 81)),
                new EcBlocks(30, new EcBlock(1, 50), new EcBlock(4, 51)),
                new EcBlocks(28, new EcBlock(4, 22), new EcBlock(4, 23)),
                new EcBlocks(24, new EcBlock(3, 12), new EcBlock(8, 13)),
            ),
            new self(
                12,
                [6, 32, 58],
                new EcBlocks(24, new EcBlock(2, 92), new EcBlock(2, 93)),
                new EcBlocks(22, new EcBlock(6, 36), new EcBlock(2, 37)),
                new EcBlocks(26, new EcBlock(4, 20), new EcBlock(6, 21)),
                new EcBlocks(28, new EcBlock(7, 14), new EcBlock(4, 15)),
            ),
            new self(
                13,
                [6, 34, 62],
                new EcBlocks(26, new EcBlock(4, 107)),
                new EcBlocks(22, new EcBlock(8, 37), new EcBlock(1, 38)),
                new EcBlocks(24, new EcBlock(8, 20), new EcBlock(4, 21)),
                new EcBlocks(22, new EcBlock(12, 11), new EcBlock(4, 12)),
            ),
            new self(
                14,
                [6, 26, 46, 66],
                new EcBlocks(30, new EcBlock(3, 115), new EcBlock(1, 116)),
                new EcBlocks(24, new EcBlock(4, 40), new EcBlock(5, 41)),
                new EcBlocks(20, new EcBlock(11, 16), new EcBlock(5, 17)),
                new EcBlocks(24, new EcBlock(11, 12), new EcBlock(5, 13)),
            ),
            new self(
                15,
                [6, 26, 48, 70],
                new EcBlocks(22, new EcBlock(5, 87), new EcBlock(1, 88)),
                new EcBlocks(24, new EcBlock(5, 41), new EcBlock(5, 42)),
                new EcBlocks(30, new EcBlock(5, 24), new EcBlock(7, 25)),
                new EcBlocks(24, new EcBlock(11, 12), new EcBlock(7, 13)),
            ),
            new self(
                16,
                [6, 26, 50, 74],
                new EcBlocks(24, new EcBlock(5, 98), new EcBlock(1, 99)),
                new EcBlocks(28, new EcBlock(7, 45), new EcBlock(3, 46)),
                new EcBlocks(24, new EcBlock(15, 19), new EcBlock(2, 20)),
                new EcBlocks(30, new EcBlock(3, 15), new EcBlock(13, 16)),
            ),
            new self(
                17,
                [6, 30, 54, 78],
                new EcBlocks(28, new EcBlock(1, 107), new EcBlock(5, 108)),
                new EcBlocks(28, new EcBlock(10, 46), new EcBlock(1, 47)),
                new EcBlocks(28, new EcBlock(1, 22), new EcBlock(15, 23)),
                new EcBlocks(28, new EcBlock(2, 14), new EcBlock(17, 15)),
            ),
            new self(
                18,
                [6, 30, 56, 82],
                new EcBlocks(30, new EcBlock(5, 120), new EcBlock(1, 121)),
                new EcBlocks(26, new EcBlock(9, 43), new EcBlock(4, 44)),
                new EcBlocks(28, new EcBlock(17, 22), new EcBlock(1, 23)),
                new EcBlocks(28, new EcBlock(2, 14), new EcBlock(19, 15)),
            ),
            new self(
                19,
                [6, 30, 58, 86],
                new EcBlocks(28, new EcBlock(3, 113), new EcBlock(4, 114)),
                new EcBlocks(26, new EcBlock(3, 44), new EcBlock(11, 45)),
                new EcBlocks(26, new EcBlock(17, 21), new EcBlock(4, 22)),
                new EcBlocks(26, new EcBlock(9, 13), new EcBlock(16, 14)),
            ),
            new self(
                20,
                [6, 34, 62, 90],
                new EcBlocks(28, new EcBlock(3, 107), new EcBlock(5, 108)),
                new EcBlocks(26, new EcBlock(3, 41), new EcBlock(13, 42)),
                new EcBlocks(30, new EcBlock(15, 24), new EcBlock(5, 25)),
                new EcBlocks(28, new EcBlock(15, 15), new EcBlock(10, 16)),
            ),
            new self(
                21,
                [6, 28, 50, 72, 94],
                new EcBlocks(28, new EcBlock(4, 116), new EcBlock(4, 117)),
                new EcBlocks(26, new EcBlock(17, 42)),
                new EcBlocks(28, new EcBlock(17, 22), new EcBlock(6, 23)),
                new EcBlocks(30, new EcBlock(19, 16), new EcBlock(6, 17)),
            ),
            new self(
                22,
                [6, 26, 50, 74, 98],
                new EcBlocks(28, new EcBlock(2, 111), new EcBlock(7, 112)),
                new EcBlocks(28, new EcBlock(17, 46)),
                new EcBlocks(30, new EcBlock(7, 24), new EcBlock(16, 25)),
                new EcBlocks(24, new EcBlock(34, 13)),
            ),
            new self(
                23,
                [6, 30, 54, 78, 102],
                new EcBlocks(30, new EcBlock(4, 121), new EcBlock(5, 122)),
                new EcBlocks(28, new EcBlock(4, 47), new EcBlock(14, 48)),
                new EcBlocks(30, new EcBlock(11, 24), new EcBlock(14, 25)),
                new EcBlocks(30, new EcBlock(16, 15), new EcBlock(14, 16)),
            ),
            new self(
                24,
                [6, 28, 54, 80, 106],
                new EcBlocks(30, new EcBlock(6, 117), new EcBlock(4, 118)),
                new EcBlocks(28, new EcBlock(6, 45), new EcBlock(14, 46)),
                new EcBlocks(30, new EcBlock(11, 24), new EcBlock(16, 25)),
                new EcBlocks(30, new EcBlock(30, 16), new EcBlock(2, 17)),
            ),
            new self(
                25,
                [6, 32, 58, 84, 110],
                new EcBlocks(26, new EcBlock(8, 106), new EcBlock(4, 107)),
                new EcBlocks(28, new EcBlock(8, 47), new EcBlock(13, 48)),
                new EcBlocks(30, new EcBlock(7, 24), new EcBlock(22, 25)),
                new EcBlocks(30, new EcBlock(22, 15), new EcBlock(13, 16)),
            ),
            new self(
                26,
                [6, 30, 58, 86, 114],
                new EcBlocks(28, new EcBlock(10, 114), new EcBlock(2, 115)),
                new EcBlocks(28, new EcBlock(19, 46), new EcBlock(4, 47)),
                new EcBlocks(28, new EcBlock(28, 22), new EcBlock(6, 23)),
                new EcBlocks(30, new EcBlock(33, 16), new EcBlock(4, 17)),
            ),
            new self(
                27,
                [6, 34, 62, 90, 118],
                new EcBlocks(30, new EcBlock(8, 122), new EcBlock(4, 123)),
                new EcBlocks(28, new EcBlock(22, 45), new EcBlock(3, 46)),
                new EcBlocks(30, new EcBlock(8, 23), new EcBlock(26, 24)),
                new EcBlocks(30, new EcBlock(12, 15), new EcBlock(28, 16)),
            ),
            new self(
                28,
                [6, 26, 50, 74, 98, 122],
                new EcBlocks(30, new EcBlock(3, 117), new EcBlock(10, 118)),
                new EcBlocks(28, new EcBlock(3, 45), new EcBlock(23, 46)),
                new EcBlocks(30, new EcBlock(4, 24), new EcBlock(31, 25)),
                new EcBlocks(30, new EcBlock(11, 15), new EcBlock(31, 16)),
            ),
            new self(
                29,
                [6, 30, 54, 78, 102, 126],
                new EcBlocks(30, new EcBlock(7, 116), new EcBlock(7, 117)),
                new EcBlocks(28, new EcBlock(21, 45), new EcBlock(7, 46)),
                new EcBlocks(30, new EcBlock(1, 23), new EcBlock(37, 24)),
                new EcBlocks(30, new EcBlock(19, 15), new EcBlock(26, 16)),
            ),
            new self(
                30,
                [6, 26, 52, 78, 104, 130],
                new EcBlocks(30, new EcBlock(5, 115), new EcBlock(10, 116)),
                new EcBlocks(28, new EcBlock(19, 47), new EcBlock(10, 48)),
                new EcBlocks(30, new EcBlock(15, 24), new EcBlock(25, 25)),
                new EcBlocks(30, new EcBlock(23, 15), new EcBlock(25, 16)),
            ),
            new self(
                31,
                [6, 30, 56, 82, 108, 134],
                new EcBlocks(30, new EcBlock(13, 115), new EcBlock(3, 116)),
                new EcBlocks(28, new EcBlock(2, 46), new EcBlock(29, 47)),
                new EcBlocks(30, new EcBlock(42, 24), new EcBlock(1, 25)),
                new EcBlocks(30, new EcBlock(23, 15), new EcBlock(28, 16)),
            ),
            new self(
                32,
                [6, 34, 60, 86, 112, 138],
                new EcBlocks(30, new EcBlock(17, 115)),
                new EcBlocks(28, new EcBlock(10, 46), new EcBlock(23, 47)),
                new EcBlocks(30, new EcBlock(10, 24), new EcBlock(35, 25)),
                new EcBlocks(30, new EcBlock(19, 15), new EcBlock(35, 16)),
            ),
            new self(
                33,
                [6, 30, 58, 86, 114, 142],
                new EcBlocks(30, new EcBlock(17, 115), new EcBlock(1, 116)),
                new EcBlocks(28, new EcBlock(14, 46), new EcBlock(21, 47)),
                new EcBlocks(30, new EcBlock(29, 24), new EcBlock(19, 25)),
                new EcBlocks(30, new EcBlock(11, 15), new EcBlock(46, 16)),
            ),
            new self(
                34,
                [6, 34, 62, 90, 118, 146],
                new EcBlocks(30, new EcBlock(13, 115), new EcBlock(6, 116)),
                new EcBlocks(28, new EcBlock(14, 46), new EcBlock(23, 47)),
                new EcBlocks(30, new EcBlock(44, 24), new EcBlock(7, 25)),
                new EcBlocks(30, new EcBlock(59, 16), new EcBlock(1, 17)),
            ),
            new self(
                35,
                [6, 30, 54, 78, 102, 126, 150],
                new EcBlocks(30, new EcBlock(12, 121), new EcBlock(7, 122)),
                new EcBlocks(28, new EcBlock(12, 47), new EcBlock(26, 48)),
                new EcBlocks(30, new EcBlock(39, 24), new EcBlock(14, 25)),
                new EcBlocks(30, new EcBlock(22, 15), new EcBlock(41, 16)),
            ),
            new self(
                36,
                [6, 24, 50, 76, 102, 128, 154],
                new EcBlocks(30, new EcBlock(6, 121), new EcBlock(14, 122)),
                new EcBlocks(28, new EcBlock(6, 47), new EcBlock(34, 48)),
                new EcBlocks(30, new EcBlock(46, 24), new EcBlock(10, 25)),
                new EcBlocks(30, new EcBlock(2, 15), new EcBlock(64, 16)),
            ),
            new self(
                37,
                [6, 28, 54, 80, 106, 132, 158],
                new EcBlocks(30, new EcBlock(17, 122), new EcBlock(4, 123)),
                new EcBlocks(28, new EcBlock(29, 46), new EcBlock(14, 47)),
                new EcBlocks(30, new EcBlock(49, 24), new EcBlock(10, 25)),
                new EcBlocks(30, new EcBlock(24, 15), new EcBlock(46, 16)),
            ),
            new self(
                38,
                [6, 32, 58, 84, 110, 136, 162],
                new EcBlocks(30, new EcBlock(4, 122), new EcBlock(18, 123)),
                new EcBlocks(28, new EcBlock(13, 46), new EcBlock(32, 47)),
                new EcBlocks(30, new EcBlock(48, 24), new EcBlock(14, 25)),
                new EcBlocks(30, new EcBlock(42, 15), new EcBlock(32, 16)),
            ),
            new self(
                39,
                [6, 26, 54, 82, 110, 138, 166],
                new EcBlocks(30, new EcBlock(20, 117), new EcBlock(4, 118)),
                new EcBlocks(28, new EcBlock(40, 47), new EcBlock(7, 48)),
                new EcBlocks(30, new EcBlock(43, 24), new EcBlock(22, 25)),
                new EcBlocks(30, new EcBlock(10, 15), new EcBlock(67, 16)),
            ),
            new self(
                40,
                [6, 30, 58, 86, 114, 142, 170],
                new EcBlocks(30, new EcBlock(19, 118), new EcBlock(6, 119)),
                new EcBlocks(28, new EcBlock(18, 47), new EcBlock(31, 48)),
                new EcBlocks(30, new EcBlock(34, 24), new EcBlock(34, 25)),
                new EcBlocks(30, new EcBlock(20, 15), new EcBlock(61, 16)),
            ),
        ];
    }
}
