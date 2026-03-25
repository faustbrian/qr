<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Decoder\Qrcode\Decoder;

use Cline\Qr\Decoder\Common\BitMatrix;
use Cline\Qr\Decoder\FormatException;
use InvalidArgumentException;

use const PHP_INT_MAX;

use function count;
use function is_array;
use function is_countable;

/**
 * QR Code version metadata and derived layout tables.
 *
 * A version defines the symbol dimension, alignment-pattern centers, and the
 * error-correction block layout for each correction level. The detector uses
 * this type as the bridge between measured symbol geometry and the fixed QR
 * Code specification tables.
 *
 * @author Sean Owen
 */
final class Version
{
    /**
     * Raw version-codewords from ISO 18004:2006 Annex D.
     *
     * Element `i` represents the 18-bit value that specifies version `i + 7`.
     */
    private static array $VERSION_DECODE_INFO = [
        0x0_7C_94, 0x0_85_BC, 0x0_9A_99, 0x0_A4_D3, 0x0_BB_F6,
        0x0_C7_62, 0x0_D8_47, 0x0_E6_0D, 0x0_F9_28, 0x1_0B_78,
        0x1_14_5D, 0x1_2A_17, 0x1_35_32, 0x1_49_A6, 0x1_56_83,
        0x1_68_C9, 0x1_77_EC, 0x1_8E_C4, 0x1_91_E1, 0x1_AF_AB,
        0x1_B0_8E, 0x1_CC_1A, 0x1_D3_3F, 0x1_ED_75, 0x1_F2_50,
        0x2_09_D5, 0x2_16_F0, 0x2_28_BA, 0x2_37_9F, 0x2_4B_0B,
        0x2_54_2E, 0x2_6A_64, 0x2_75_41, 0x2_8C_69,
    ];

    /** @var null|array<self> */
    private static $VERSIONS;

    private readonly float|int $totalCodewords;

    public function __construct(
        private $versionNumber,
        private $alignmentPatternCenters,
        private $ecBlocks,
    ) {
        $total = 0;

        if (is_array($ecBlocks)) {
            $ecCodewords = $ecBlocks[0]->getECCodewordsPerBlock();
            $ecbArray = $ecBlocks[0]->getECBlocks();
        } else {
            $ecCodewords = $ecBlocks->getECCodewordsPerBlock();
            $ecbArray = $ecBlocks->getECBlocks();
        }

        foreach ($ecbArray as $ecBlock) {
            $total += $ecBlock->getCount() * ($ecBlock->getDataCodewords() + $ecCodewords);
        }
        $this->totalCodewords = $total;
    }

    /**
     * Derive a provisional version from the observed module dimension.
     *
     * The version number is reconstructed from the QR symbol width before the
     * detector has read the version information bits. This is the fast path used
     * during geometric detection.
     *
     * @param int $dimension Symbol dimension in modules.
     *
     * @throws FormatException When the dimension is not valid for a QR Code.
     * @return self            Version for a QR Code of that dimension.
     */
    public static function getProvisionalVersionForDimension($dimension)
    {
        if ($dimension % 4 !== 1) {
            throw FormatException::getFormatInstance();
        }

        try {
            return self::getVersionForNumber(($dimension - 17) / 4);
        } catch (InvalidArgumentException) {
            throw FormatException::getFormatInstance();
        }
    }

    /**
     * Resolve the canonical metadata object for a concrete version number.
     *
     * @param float|int $versionNumber Version number in the range 1..40.
     *
     * @throws InvalidArgumentException When the supplied version is outside the
     *                                  QR Code specification range.
     * @return self                     Cached version metadata instance.
     */
    public static function getVersionForNumber(int|float $versionNumber)
    {
        if ($versionNumber < 1 || $versionNumber > 40) {
            throw \Cline\Qr\Decoder\InvalidArgumentException::withMessage();
        }

        if (!self::$VERSIONS) {
            self::$VERSIONS = self::buildVersions();
        }

        return self::$VERSIONS[$versionNumber - 1];
    }

    /**
     * Decode the 18-bit version information field, allowing limited damage.
     *
     * @param int $versionBits Raw version bits read from the symbol.
     *
     * @return null|self Matching version metadata, or `null` when the observed
     *                   bits are too far from any valid version codeword.
     */
    public static function decodeVersionInformation(int $versionBits)
    {
        $bestDifference = PHP_INT_MAX;
        $bestVersion = 0;

        for ($i = 0; $i < count(self::$VERSION_DECODE_INFO); ++$i) {
            $targetVersion = self::$VERSION_DECODE_INFO[$i];

            // Do the version info bits match exactly? done.
            if ($targetVersion === $versionBits) {
                return self::getVersionForNumber($i + 7);
            }
            // Otherwise see if this is the closest to a real version info bit string
            // we have seen so far
            $bitsDifference = FormatInformation::numBitsDiffering($versionBits, $targetVersion);

            if ($bitsDifference >= $bestDifference) {
                continue;
            }

            $bestVersion = $i + 7;
            $bestDifference = $bitsDifference;
        }

        // We can tolerate up to 3 bits of error since no two version info codewords will
        // differ in less than 8 bits.
        if ($bestDifference <= 3) {
            return self::getVersionForNumber($bestVersion);
        }

        // If we didn't find a close enough match, fail
        return null;
    }

    /**
     * Return the QR Code version number, from 1 through 40.
     */
    public function getVersionNumber()
    {
        return $this->versionNumber;
    }

    /**
     * Return the alignment-pattern centers defined by this version.
     */
    public function getAlignmentPatternCenters()
    {
        return $this->alignmentPatternCenters;
    }

    /**
     * Return the total number of codewords in the symbol.
     */
    public function getTotalCodewords(): int|float
    {
        return $this->totalCodewords;
    }

    /**
     * Return the matrix dimension, in modules, for this version.
     */
    public function getDimensionForVersion()
    {
        return 17 + 4 * $this->versionNumber;
    }

    /**
     * Return the error-correction block structure for the supplied level.
     */
    public function getECBlocksForLevel(ErrorCorrectionLevel $ecLevel)
    {
        return $this->ecBlocks[$ecLevel->getOrdinal()];
    }

    /**
     * Build the fixed function-pattern mask for this version.
     *
     * The result marks finder patterns, timing patterns, alignment patterns, and
     * version-information regions so later stages know which modules must not be
     * sampled as payload.
     */
    public function buildFunctionPattern(): BitMatrix
    {
        $dimension = self::getDimensionForVersion();
        $bitMatrix = new BitMatrix($dimension);

        // Top left finder pattern + separator + format
        $bitMatrix->setRegion(0, 0, 9, 9);
        // Top right finder pattern + separator + format
        $bitMatrix->setRegion($dimension - 8, 0, 8, 9);
        // Bottom left finder pattern + separator + format
        $bitMatrix->setRegion(0, $dimension - 8, 9, 8);

        // Alignment patterns
        $max = is_countable($this->alignmentPatternCenters) ? count($this->alignmentPatternCenters) : 0;

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
     * Build the static version table defined by the QR Code specification.
     *
     * @return array<self> Ordered list of all 40 QR Code versions.
     *
     * @psalm-return array{0: self, 1: self, 2: self, 3: self, 4: self, 5: self, 6: self, 7: self, 8: self, 9: self, 10: self, 11: self, 12: self, 13: self, 14: self, 15: self, 16: self, 17: self, 18: self, 19: self, 20: self, 21: self, 22: self, 23: self, 24: self, 25: self, 26: self, 27: self, 28: self, 29: self, 30: self, 31: self, 32: self, 33: self, 34: self, 35: self, 36: self, 37: self, 38: self, 39: self}
     */
    private static function buildVersions(): array
    {
        return [
            new self(
                1,
                [],
                [new ECBlocks(7, [new ECB(1, 19)]),
                    new ECBlocks(10, [new ECB(1, 16)]),
                    new ECBlocks(13, [new ECB(1, 13)]),
                    new ECBlocks(17, [new ECB(1, 9)])],
            ),
            new self(
                2,
                [6, 18],
                [new ECBlocks(10, [new ECB(1, 34)]),
                    new ECBlocks(16, [new ECB(1, 28)]),
                    new ECBlocks(22, [new ECB(1, 22)]),
                    new ECBlocks(28, [new ECB(1, 16)])],
            ),
            new self(
                3,
                [6, 22],
                [new ECBlocks(15, [new ECB(1, 55)]),
                    new ECBlocks(26, [new ECB(1, 44)]),
                    new ECBlocks(18, [new ECB(2, 17)]),
                    new ECBlocks(22, [new ECB(2, 13)])],
            ),
            new self(
                4,
                [6, 26],
                [new ECBlocks(20, [new ECB(1, 80)]),
                    new ECBlocks(18, [new ECB(2, 32)]),
                    new ECBlocks(26, [new ECB(2, 24)]),
                    new ECBlocks(16, [new ECB(4, 9)])],
            ),
            new self(
                5,
                [6, 30],
                [new ECBlocks(26, [new ECB(1, 108)]),
                    new ECBlocks(24, [new ECB(2, 43)]),
                    new ECBlocks(18, [new ECB(2, 15),
                        new ECB(2, 16)]),
                    new ECBlocks(22, [new ECB(2, 11),
                        new ECB(2, 12)])],
            ),
            new self(
                6,
                [6, 34],
                [new ECBlocks(18, [new ECB(2, 68)]),
                    new ECBlocks(16, [new ECB(4, 27)]),
                    new ECBlocks(24, [new ECB(4, 19)]),
                    new ECBlocks(28, [new ECB(4, 15)])],
            ),
            new self(
                7,
                [6, 22, 38],
                [new ECBlocks(20, [new ECB(2, 78)]),
                    new ECBlocks(18, [new ECB(4, 31)]),
                    new ECBlocks(18, [new ECB(2, 14),
                        new ECB(4, 15)]),
                    new ECBlocks(26, [new ECB(4, 13),
                        new ECB(1, 14)])],
            ),
            new self(
                8,
                [6, 24, 42],
                [new ECBlocks(24, [new ECB(2, 97)]),
                    new ECBlocks(22, [new ECB(2, 38),
                        new ECB(2, 39)]),
                    new ECBlocks(22, [new ECB(4, 18),
                        new ECB(2, 19)]),
                    new ECBlocks(26, [new ECB(4, 14),
                        new ECB(2, 15)])],
            ),
            new self(
                9,
                [6, 26, 46],
                [new ECBlocks(30, [new ECB(2, 116)]),
                    new ECBlocks(22, [new ECB(3, 36),
                        new ECB(2, 37)]),
                    new ECBlocks(20, [new ECB(4, 16),
                        new ECB(4, 17)]),
                    new ECBlocks(24, [new ECB(4, 12),
                        new ECB(4, 13)])],
            ),
            new self(
                10,
                [6, 28, 50],
                [new ECBlocks(18, [new ECB(2, 68),
                    new ECB(2, 69)]),
                    new ECBlocks(26, [new ECB(4, 43),
                        new ECB(1, 44)]),
                    new ECBlocks(24, [new ECB(6, 19),
                        new ECB(2, 20)]),
                    new ECBlocks(28, [new ECB(6, 15),
                        new ECB(2, 16)])],
            ),
            new self(
                11,
                [6, 30, 54],
                [new ECBlocks(20, [new ECB(4, 81)]),
                    new ECBlocks(30, [new ECB(1, 50),
                        new ECB(4, 51)]),
                    new ECBlocks(28, [new ECB(4, 22),
                        new ECB(4, 23)]),
                    new ECBlocks(24, [new ECB(3, 12),
                        new ECB(8, 13)])],
            ),
            new self(
                12,
                [6, 32, 58],
                [new ECBlocks(24, [new ECB(2, 92),
                    new ECB(2, 93)]),
                    new ECBlocks(22, [new ECB(6, 36),
                        new ECB(2, 37)]),
                    new ECBlocks(26, [new ECB(4, 20),
                        new ECB(6, 21)]),
                    new ECBlocks(28, [new ECB(7, 14),
                        new ECB(4, 15)])],
            ),
            new self(
                13,
                [6, 34, 62],
                [new ECBlocks(26, [new ECB(4, 107)]),
                    new ECBlocks(22, [new ECB(8, 37),
                        new ECB(1, 38)]),
                    new ECBlocks(24, [new ECB(8, 20),
                        new ECB(4, 21)]),
                    new ECBlocks(22, [new ECB(12, 11),
                        new ECB(4, 12)])],
            ),
            new self(
                14,
                [6, 26, 46, 66],
                [new ECBlocks(30, [new ECB(3, 115),
                    new ECB(1, 116)]),
                    new ECBlocks(24, [new ECB(4, 40),
                        new ECB(5, 41)]),
                    new ECBlocks(20, [new ECB(11, 16),
                        new ECB(5, 17)]),
                    new ECBlocks(24, [new ECB(11, 12),
                        new ECB(5, 13)])],
            ),
            new self(
                15,
                [6, 26, 48, 70],
                [new ECBlocks(22, [new ECB(5, 87),
                    new ECB(1, 88)]),
                    new ECBlocks(24, [new ECB(5, 41),
                        new ECB(5, 42)]),
                    new ECBlocks(30, [new ECB(5, 24),
                        new ECB(7, 25)]),
                    new ECBlocks(24, [new ECB(11, 12),
                        new ECB(7, 13)])],
            ),
            new self(
                16,
                [6, 26, 50, 74],
                [new ECBlocks(24, [new ECB(5, 98),
                    new ECB(1, 99)]),
                    new ECBlocks(28, [new ECB(7, 45),
                        new ECB(3, 46)]),
                    new ECBlocks(24, [new ECB(15, 19),
                        new ECB(2, 20)]),
                    new ECBlocks(30, [new ECB(3, 15),
                        new ECB(13, 16)])],
            ),
            new self(
                17,
                [6, 30, 54, 78],
                [new ECBlocks(28, [new ECB(1, 107),
                    new ECB(5, 108)]),
                    new ECBlocks(28, [new ECB(10, 46),
                        new ECB(1, 47)]),
                    new ECBlocks(28, [new ECB(1, 22),
                        new ECB(15, 23)]),
                    new ECBlocks(28, [new ECB(2, 14),
                        new ECB(17, 15)])],
            ),
            new self(
                18,
                [6, 30, 56, 82],
                [new ECBlocks(30, [new ECB(5, 120),
                    new ECB(1, 121)]),
                    new ECBlocks(26, [new ECB(9, 43),
                        new ECB(4, 44)]),
                    new ECBlocks(28, [new ECB(17, 22),
                        new ECB(1, 23)]),
                    new ECBlocks(28, [new ECB(2, 14),
                        new ECB(19, 15)])],
            ),
            new self(
                19,
                [6, 30, 58, 86],
                [new ECBlocks(28, [new ECB(3, 113),
                    new ECB(4, 114)]),
                    new ECBlocks(26, [new ECB(3, 44),
                        new ECB(11, 45)]),
                    new ECBlocks(26, [new ECB(17, 21),
                        new ECB(4, 22)]),
                    new ECBlocks(26, [new ECB(9, 13),
                        new ECB(16, 14)])],
            ),
            new self(
                20,
                [6, 34, 62, 90],
                [new ECBlocks(28, [new ECB(3, 107),
                    new ECB(5, 108)]),
                    new ECBlocks(26, [new ECB(3, 41),
                        new ECB(13, 42)]),
                    new ECBlocks(30, [new ECB(15, 24),
                        new ECB(5, 25)]),
                    new ECBlocks(28, [new ECB(15, 15),
                        new ECB(10, 16)])],
            ),
            new self(
                21,
                [6, 28, 50, 72, 94],
                [new ECBlocks(28, [new ECB(4, 116),
                    new ECB(4, 117)]),
                    new ECBlocks(26, [new ECB(17, 42)]),
                    new ECBlocks(28, [new ECB(17, 22),
                        new ECB(6, 23)]),
                    new ECBlocks(30, [new ECB(19, 16),
                        new ECB(6, 17)])],
            ),
            new self(
                22,
                [6, 26, 50, 74, 98],
                [new ECBlocks(28, [new ECB(2, 111),
                    new ECB(7, 112)]),
                    new ECBlocks(28, [new ECB(17, 46)]),
                    new ECBlocks(30, [new ECB(7, 24),
                        new ECB(16, 25)]),
                    new ECBlocks(24, [new ECB(34, 13)])],
            ),
            new self(
                23,
                [6, 30, 54, 78, 102],
                new ECBlocks(30, [new ECB(4, 121),
                    new ECB(5, 122)]),
                new ECBlocks(28, [new ECB(4, 47),
                    new ECB(14, 48)]),
                new ECBlocks(30, [new ECB(11, 24),
                    new ECB(14, 25)]),
                new ECBlocks(30, [new ECB(16, 15),
                    new ECB(14, 16)]),
            ),
            new self(
                24,
                [6, 28, 54, 80, 106],
                [new ECBlocks(30, [new ECB(6, 117),
                    new ECB(4, 118)]),
                    new ECBlocks(28, [new ECB(6, 45),
                        new ECB(14, 46)]),
                    new ECBlocks(30, [new ECB(11, 24),
                        new ECB(16, 25)]),
                    new ECBlocks(30, [new ECB(30, 16),
                        new ECB(2, 17)])],
            ),
            new self(
                25,
                [6, 32, 58, 84, 110],
                [new ECBlocks(26, [new ECB(8, 106),
                    new ECB(4, 107)]),
                    new ECBlocks(28, [new ECB(8, 47),
                        new ECB(13, 48)]),
                    new ECBlocks(30, [new ECB(7, 24),
                        new ECB(22, 25)]),
                    new ECBlocks(30, [new ECB(22, 15),
                        new ECB(13, 16)])],
            ),
            new self(
                26,
                [6, 30, 58, 86, 114],
                [new ECBlocks(28, [new ECB(10, 114),
                    new ECB(2, 115)]),
                    new ECBlocks(28, [new ECB(19, 46),
                        new ECB(4, 47)]),
                    new ECBlocks(28, [new ECB(28, 22),
                        new ECB(6, 23)]),
                    new ECBlocks(30, [new ECB(33, 16),
                        new ECB(4, 17)])],
            ),
            new self(
                27,
                [6, 34, 62, 90, 118],
                [new ECBlocks(30, [new ECB(8, 122),
                    new ECB(4, 123)]),
                    new ECBlocks(28, [new ECB(22, 45),
                        new ECB(3, 46)]),
                    new ECBlocks(30, [new ECB(8, 23),
                        new ECB(26, 24)]),
                    new ECBlocks(30, [new ECB(12, 15),
                        new ECB(28, 16)])],
            ),
            new self(
                28,
                [6, 26, 50, 74, 98, 122],
                [new ECBlocks(30, [new ECB(3, 117),
                    new ECB(10, 118)]),
                    new ECBlocks(28, [new ECB(3, 45),
                        new ECB(23, 46)]),
                    new ECBlocks(30, [new ECB(4, 24),
                        new ECB(31, 25)]),
                    new ECBlocks(30, [new ECB(11, 15),
                        new ECB(31, 16)])],
            ),
            new self(
                29,
                [6, 30, 54, 78, 102, 126],
                [new ECBlocks(30, [new ECB(7, 116),
                    new ECB(7, 117)]),
                    new ECBlocks(28, [new ECB(21, 45),
                        new ECB(7, 46)]),
                    new ECBlocks(30, [new ECB(1, 23),
                        new ECB(37, 24)]),
                    new ECBlocks(30, [new ECB(19, 15),
                        new ECB(26, 16)])],
            ),
            new self(
                30,
                [6, 26, 52, 78, 104, 130],
                [new ECBlocks(30, [new ECB(5, 115),
                    new ECB(10, 116)]),
                    new ECBlocks(28, [new ECB(19, 47),
                        new ECB(10, 48)]),
                    new ECBlocks(30, [new ECB(15, 24),
                        new ECB(25, 25)]),
                    new ECBlocks(30, [new ECB(23, 15),
                        new ECB(25, 16)])],
            ),
            new self(
                31,
                [6, 30, 56, 82, 108, 134],
                [new ECBlocks(30, [new ECB(13, 115),
                    new ECB(3, 116)]),
                    new ECBlocks(28, [new ECB(2, 46),
                        new ECB(29, 47)]),
                    new ECBlocks(30, [new ECB(42, 24),
                        new ECB(1, 25)]),
                    new ECBlocks(30, [new ECB(23, 15),
                        new ECB(28, 16)])],
            ),
            new self(
                32,
                [6, 34, 60, 86, 112, 138],
                [new ECBlocks(30, [new ECB(17, 115)]),
                    new ECBlocks(28, [new ECB(10, 46),
                        new ECB(23, 47)]),
                    new ECBlocks(30, [new ECB(10, 24),
                        new ECB(35, 25)]),
                    new ECBlocks(30, [new ECB(19, 15),
                        new ECB(35, 16)])],
            ),
            new self(
                33,
                [6, 30, 58, 86, 114, 142],
                [new ECBlocks(30, [new ECB(17, 115),
                    new ECB(1, 116)]),
                    new ECBlocks(28, [new ECB(14, 46),
                        new ECB(21, 47)]),
                    new ECBlocks(30, [new ECB(29, 24),
                        new ECB(19, 25)]),
                    new ECBlocks(30, [new ECB(11, 15),
                        new ECB(46, 16)])],
            ),
            new self(
                34,
                [6, 34, 62, 90, 118, 146],
                [new ECBlocks(30, [new ECB(13, 115),
                    new ECB(6, 116)]),
                    new ECBlocks(28, [new ECB(14, 46),
                        new ECB(23, 47)]),
                    new ECBlocks(30, [new ECB(44, 24),
                        new ECB(7, 25)]),
                    new ECBlocks(30, [new ECB(59, 16),
                        new ECB(1, 17)])],
            ),
            new self(
                35,
                [6, 30, 54, 78, 102, 126, 150],
                [new ECBlocks(30, [new ECB(12, 121),
                    new ECB(7, 122)]),
                    new ECBlocks(28, [new ECB(12, 47),
                        new ECB(26, 48)]),
                    new ECBlocks(30, [new ECB(39, 24),
                        new ECB(14, 25)]),
                    new ECBlocks(30, [new ECB(22, 15),
                        new ECB(41, 16)])],
            ),
            new self(
                36,
                [6, 24, 50, 76, 102, 128, 154],
                [new ECBlocks(30, [new ECB(6, 121),
                    new ECB(14, 122)]),
                    new ECBlocks(28, [new ECB(6, 47),
                        new ECB(34, 48)]),
                    new ECBlocks(30, [new ECB(46, 24),
                        new ECB(10, 25)]),
                    new ECBlocks(30, [new ECB(2, 15),
                        new ECB(64, 16)])],
            ),
            new self(
                37,
                [6, 28, 54, 80, 106, 132, 158],
                [new ECBlocks(30, [new ECB(17, 122),
                    new ECB(4, 123)]),
                    new ECBlocks(28, [new ECB(29, 46),
                        new ECB(14, 47)]),
                    new ECBlocks(30, [new ECB(49, 24),
                        new ECB(10, 25)]),
                    new ECBlocks(30, [new ECB(24, 15),
                        new ECB(46, 16)])],
            ),
            new self(
                38,
                [6, 32, 58, 84, 110, 136, 162],
                [new ECBlocks(30, [new ECB(4, 122),
                    new ECB(18, 123)]),
                    new ECBlocks(28, [new ECB(13, 46),
                        new ECB(32, 47)]),
                    new ECBlocks(30, [new ECB(48, 24),
                        new ECB(14, 25)]),
                    new ECBlocks(30, [new ECB(42, 15),
                        new ECB(32, 16)])],
            ),
            new self(
                39,
                [6, 26, 54, 82, 110, 138, 166],
                [new ECBlocks(30, [new ECB(20, 117),
                    new ECB(4, 118)]),
                    new ECBlocks(28, [new ECB(40, 47),
                        new ECB(7, 48)]),
                    new ECBlocks(30, [new ECB(43, 24),
                        new ECB(22, 25)]),
                    new ECBlocks(30, [new ECB(10, 15),
                        new ECB(67, 16)])],
            ),
            new self(
                40,
                [6, 30, 58, 86, 114, 142, 170],
                [new ECBlocks(30, [new ECB(19, 118),
                    new ECB(6, 119)]),
                    new ECBlocks(28, [new ECB(18, 47),
                        new ECB(31, 48)]),
                    new ECBlocks(30, [new ECB(34, 24),
                        new ECB(34, 25)]),
                    new ECBlocks(30, [new ECB(20, 15),
                        new ECB(61, 16)])],
            ),
        ];
    }
}

/**
 * <p>Encapsulates a set of error-correction blocks in one symbol version. Most versions will
 * use blocks of differing sizes within one version, so, this encapsulates the parameters for
 * each set of blocks. It also holds the number of error-correction codewords per block since it
 * will be the same across all blocks within one version.</p>
 * @author Brian Faust <brian@cline.sh>
 */
final class ECBlocks
{
    public function __construct(
        private $ecCodewordsPerBlock,
        private $ecBlocks,
    ) {}

    public function getECCodewordsPerBlock()
    {
        return $this->ecCodewordsPerBlock;
    }

    public function getNumBlocks()
    {
        $total = 0;

        foreach ($this->ecBlocks as $ecBlock) {
            $total += $ecBlock->getCount();
        }

        return $total;
    }

    public function getTotalECCodewords()
    {
        return $this->ecCodewordsPerBlock * $this->getNumBlocks();
    }

    public function getECBlocks()
    {
        return $this->ecBlocks;
    }
}

/**
 * <p>Encapsualtes the parameters for one error-correction block in one symbol version.
 * This includes the number of data codewords, and the number of times a block with these
 * parameters is used consecutively in the QR code version's format.</p>
 * @author Brian Faust <brian@cline.sh>
 */
final class ECB
{
    public function __construct(
        private $count,
        private $dataCodewords,
    ) {}

    public function getCount()
    {
        return $this->count;
    }

    public function getDataCodewords()
    {
        return $this->dataCodewords;
    }

    public function toString(): string
    {
        exit('Version ECB toString()');
        //  return parent::$versionNumber;
    }
}
