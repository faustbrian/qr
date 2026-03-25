<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Generator\Common;

use const PHP_INT_MAX;

/**
 * Decode and hold the QR format information payload.
 *
 * Format information carries the error-correction level and mask pattern for a
 * symbol. The decoder tolerates bit errors by searching the lookup table for
 * the closest valid entry before giving up, which is why the helper methods
 * focus on Hamming distance and fallback decoding behavior.
 * @author Brian Faust <brian@cline.sh>
 */
final class FormatInformation
{
    /**
     * Mask applied to format information in the QR specification.
     */
    private const int FORMAT_INFO_MASK_QR = 0x54_12;

    /**
     * Lookup table for decoding format information.
     *
     * See ISO 18004:2006, Annex C, Table C.1
     */
    private const array FORMAT_INFO_DECODE_LOOKUP = [
        [0x54_12, 0x00],
        [0x51_25, 0x01],
        [0x5E_7C, 0x02],
        [0x5B_4B, 0x03],
        [0x45_F9, 0x04],
        [0x40_CE, 0x05],
        [0x4F_97, 0x06],
        [0x4A_A0, 0x07],
        [0x77_C4, 0x08],
        [0x72_F3, 0x09],
        [0x7D_AA, 0x0A],
        [0x78_9D, 0x0B],
        [0x66_2F, 0x0C],
        [0x63_18, 0x0D],
        [0x6C_41, 0x0E],
        [0x69_76, 0x0F],
        [0x16_89, 0x10],
        [0x13_BE, 0x11],
        [0x1C_E7, 0x12],
        [0x19_D0, 0x13],
        [0x07_62, 0x14],
        [0x02_55, 0x15],
        [0x0D_0C, 0x16],
        [0x08_3B, 0x17],
        [0x35_5F, 0x18],
        [0x30_68, 0x19],
        [0x3F_31, 0x1A],
        [0x3A_06, 0x1B],
        [0x24_B4, 0x1C],
        [0x21_83, 0x1D],
        [0x2E_DA, 0x1E],
        [0x2B_ED, 0x1F],
    ];

    /**
     * Popcount values for half-byte lookup.
     */
    private const array BITS_SET_IN_HALF_BYTE = [0, 1, 1, 2, 1, 2, 2, 3, 1, 2, 2, 3, 2, 3, 3, 4];

    /**
     * Error correction level encoded in the format information.
     */
    private ErrorCorrectionLevel $ecLevel;

    /**
     * Data mask pattern encoded in the format information.
     */
    private int $dataMask;

    /**
     * Decode the compact format information value into its component fields.
     */
    protected function __construct(int $formatInfo)
    {
        $this->ecLevel = ErrorCorrectionLevel::forBits(($formatInfo >> 3) & 0x3);
        $this->dataMask = $formatInfo & 0x7;
    }

    /**
     * Count the number of differing bits between two integers.
     */
    public static function numBitsDiffering(int $a, int $b): int
    {
        $a ^= $b;

        return
            self::BITS_SET_IN_HALF_BYTE[$a & 0xF]
            + self::BITS_SET_IN_HALF_BYTE[BitUtils::unsignedRightShift($a, 4) & 0xF]
            + self::BITS_SET_IN_HALF_BYTE[BitUtils::unsignedRightShift($a, 8) & 0xF]
            + self::BITS_SET_IN_HALF_BYTE[BitUtils::unsignedRightShift($a, 12) & 0xF]
            + self::BITS_SET_IN_HALF_BYTE[BitUtils::unsignedRightShift($a, 16) & 0xF]
            + self::BITS_SET_IN_HALF_BYTE[BitUtils::unsignedRightShift($a, 20) & 0xF]
            + self::BITS_SET_IN_HALF_BYTE[BitUtils::unsignedRightShift($a, 24) & 0xF]
            + self::BITS_SET_IN_HALF_BYTE[BitUtils::unsignedRightShift($a, 28) & 0xF];
    }

    /**
     * Decode format information from the two mirrored symbol locations.
     *
     * The method first attempts a direct decode and then retries after applying
     * the QR mask pattern, which covers symbols that were stored or captured
     * with the mask already removed.
     */
    public static function decodeFormatInformation(int $maskedFormatInfo1, int $maskedFormatInfo2): ?self
    {
        $formatInfo = self::doDecodeFormatInformation($maskedFormatInfo1, $maskedFormatInfo2);

        if (null !== $formatInfo) {
            return $formatInfo;
        }

        // Should return null, but, some QR codes apparently do not mask this info. Try again by actually masking the
        // pattern first.
        return self::doDecodeFormatInformation(
            $maskedFormatInfo1 ^ self::FORMAT_INFO_MASK_QR,
            $maskedFormatInfo2 ^ self::FORMAT_INFO_MASK_QR,
        );
    }

    /**
     * Return the decoded error-correction level.
     */
    public function getErrorCorrectionLevel(): ErrorCorrectionLevel
    {
        return $this->ecLevel;
    }

    /**
     * Return the decoded data mask pattern.
     */
    public function getDataMask(): int
    {
        return $this->dataMask;
    }

    /**
     * Return a compact integer representation of the decoded format payload.
     */
    public function hashCode(): int
    {
        return ($this->ecLevel->getBits() << 3) | $this->dataMask;
    }

    /**
     * Compare two decoded format information instances.
     */
    public function equals(self $other): bool
    {
        return
            $this->ecLevel === $other->ecLevel
            && $this->dataMask === $other->dataMask;
    }

    /**
     * Internal helper that chooses the closest known format table entry.
     */
    private static function doDecodeFormatInformation(int $maskedFormatInfo1, int $maskedFormatInfo2): ?self
    {
        $bestDifference = PHP_INT_MAX;
        $bestFormatInfo = 0;

        foreach (self::FORMAT_INFO_DECODE_LOOKUP as $decodeInfo) {
            $targetInfo = $decodeInfo[0];

            if ($targetInfo === $maskedFormatInfo1 || $targetInfo === $maskedFormatInfo2) {
                // Found an exact match
                return new self($decodeInfo[1]);
            }

            $bitsDifference = self::numBitsDiffering($maskedFormatInfo1, $targetInfo);

            if ($bitsDifference < $bestDifference) {
                $bestFormatInfo = $decodeInfo[1];
                $bestDifference = $bitsDifference;
            }

            if ($maskedFormatInfo1 === $maskedFormatInfo2) {
                continue;
            }

            // Also try the other option
            $bitsDifference = self::numBitsDiffering($maskedFormatInfo2, $targetInfo);

            if ($bitsDifference >= $bestDifference) {
                continue;
            }

            $bestFormatInfo = $decodeInfo[1];
            $bestDifference = $bitsDifference;
        }

        // Hamming distance of the 32 masked codes is 7, by construction, so <= 3 bits differing means we found a match.
        if ($bestDifference <= 3) {
            return new self($bestFormatInfo);
        }

        return null;
    }
}
