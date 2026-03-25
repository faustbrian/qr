<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Decoder\Qrcode\Decoder;

use const PHP_INT_MAX;

use function uRShift;

/**
 * Decoded QR Code format information.
 *
 * This type stores the two values encoded in the 15-bit format field: the
 * selected error-correction level and the data-mask pattern applied to the
 * symbol. The decoder attempts an exact match first and then falls back to a
 * Hamming-distance search so that mildly damaged symbols can still be read.
 *
 * @author Sean Owen
 * @see AbstractDataMask
 * @see ErrorCorrectionLevel
 */
final class FormatInformation
{
    public static $FORMAT_INFO_MASK_QR;

    /**
     * QR Code format-code lookup table from ISO 18004:2006, Annex C.
     */
    public static $FORMAT_INFO_DECODE_LOOKUP;

    /**
     * Offset i holds the number of 1 bits in the binary representation of i.
     *
     * @var null|array<int>
     */
    private static ?array $BITS_SET_IN_HALF_BYTE = null;

    private readonly ErrorCorrectionLevel $errorCorrectionLevel;

    private readonly int $dataMask;

    private function __construct($formatInfo)
    {
        // Bits 3,4
        $this->errorCorrectionLevel = ErrorCorrectionLevel::forBits(($formatInfo >> 3) & 0x03);
        // Bottom 3 bits
        $this->dataMask = $formatInfo & 0x07; // (byte)
    }

    /**
     * Populate the decode tables used to recover masked format information.
     *
     * The lookup data is static because the QR Code spec defines a fixed set of
     * 32 format codewords.
     */
    public static function Init(): void
    {
        self::$FORMAT_INFO_MASK_QR = 0x54_12;
        self::$BITS_SET_IN_HALF_BYTE = [0, 1, 1, 2, 1, 2, 2, 3, 1, 2, 2, 3, 2, 3, 3, 4];
        self::$FORMAT_INFO_DECODE_LOOKUP = [
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
    }

    /**
     * Decode the two observed format-code copies into a single logical result.
     *
     * The QR Code places the same information in two locations. This method
     * first checks the raw values, then retries after XORing the standard mask
     * pattern so damaged or partially unmasked symbols still have a chance to
     * decode.
     *
     * @param int $maskedFormatInfo1 First observed masked format value.
     * @param int $maskedFormatInfo2 Second observed masked format value.
     *
     * @return null|self Decoded format information, or `null` when no close
     *                   match exists.
     */
    public static function decodeFormatInformation(int $maskedFormatInfo1, int $maskedFormatInfo2)
    {
        $formatInfo = self::doDecodeFormatInformation($maskedFormatInfo1, $maskedFormatInfo2);

        if ($formatInfo !== null) {
            return $formatInfo;
        }

        // Should return null, but, some QR codes apparently
        // do not mask this info. Try again by actually masking the pattern
        // first
        return self::doDecodeFormatInformation(
            $maskedFormatInfo1 ^ self::$FORMAT_INFO_MASK_QR,
            $maskedFormatInfo2 ^ self::$FORMAT_INFO_MASK_QR,
        );
    }

    /**
     * Count the number of differing bits between two format-code candidates.
     *
     * @param int $a First bit pattern.
     * @param int $b Second bit pattern.
     */
    public static function numBitsDiffering(int $a, $b): int
    {
        $a ^= $b; // a now has a 1 bit exactly where its bit differs with b's

        // Count bits set quickly with a series of lookups:
        return self::$BITS_SET_IN_HALF_BYTE[$a & 0x0F] +
            self::$BITS_SET_IN_HALF_BYTE[(int) (uRShift($a, 4) & 0x0F)] +
            self::$BITS_SET_IN_HALF_BYTE[uRShift($a, 8) & 0x0F] +
            self::$BITS_SET_IN_HALF_BYTE[uRShift($a, 12) & 0x0F] +
            self::$BITS_SET_IN_HALF_BYTE[uRShift($a, 16) & 0x0F] +
            self::$BITS_SET_IN_HALF_BYTE[uRShift($a, 20) & 0x0F] +
            self::$BITS_SET_IN_HALF_BYTE[uRShift($a, 24) & 0x0F] +
            self::$BITS_SET_IN_HALF_BYTE[uRShift($a, 28) & 0x0F];
    }

    /**
     * Return the decoded error-correction tier.
     */
    public function getErrorCorrectionLevel(): ErrorCorrectionLevel
    {
        return $this->errorCorrectionLevel;
    }

    /**
     * Return the 3-bit data-mask pattern identifier.
     */
    public function getDataMask(): int
    {
        return $this->dataMask;
    }

    /**
     * Hash the logical format contents into the same shape used by the decoder.
     */
    public function hashCode()
    {
        return ($this->errorCorrectionLevel->getOrdinal() << 3) | (int) $this->dataMask;
    }

    /**
     * Compare the logical error-correction and mask values.
     * @param mixed $o
     */
    public function equals($o): bool
    {
        if (!$o instanceof self) {
            return false;
        }
        $other = $o;

        return $this->errorCorrectionLevel === $other->errorCorrectionLevel
            && $this->dataMask === $other->dataMask;
    }

    /**
     * Compare two observed format codes against the canonical decode table.
     *
     * This helper returns the closest exact or near-exact match from the spec's
     * fixed 32-entry table. A maximum of three differing bits is tolerated.
     *
     * @param int $maskedFormatInfo1 First candidate format code.
     * @param int $maskedFormatInfo2 Second candidate format code.
     *
     * @return null|self The best matching format information, or `null` if the
     *                   observed bits are too far away from any known codeword.
     */
    private static function doDecodeFormatInformation(int $maskedFormatInfo1, int $maskedFormatInfo2): ?self
    {
        // Find the int in FORMAT_INFO_DECODE_LOOKUP with fewest bits differing
        $bestDifference = PHP_INT_MAX;
        $bestFormatInfo = 0;

        foreach (self::$FORMAT_INFO_DECODE_LOOKUP as $decodeInfo) {
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

            // also try the other option
            $bitsDifference = self::numBitsDiffering($maskedFormatInfo2, $targetInfo);

            if ($bitsDifference >= $bestDifference) {
                continue;
            }

            $bestFormatInfo = $decodeInfo[1];
            $bestDifference = $bitsDifference;
        }

        // Hamming distance of the 32 masked codes is 7, by construction, so <= 3 bits
        // differing means we found a match
        if ($bestDifference <= 3) {
            return new self($bestFormatInfo);
        }

        return null;
    }
}

FormatInformation::Init();
