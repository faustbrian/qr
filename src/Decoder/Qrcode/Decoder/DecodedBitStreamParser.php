<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Decoder\Qrcode\Decoder;

use Cline\Qr\Decoder\Common\BitSource;
use Cline\Qr\Decoder\Common\CharacterSetECI;
use Cline\Qr\Decoder\Common\DecoderResult;
use Cline\Qr\Decoder\FormatException;
use InvalidArgumentException;
use ValueError;

use function array_key_exists;
use function array_map;
use function array_merge;
use function chr;
use function count;
use function fill_array;
use function iconv;
use function implode;
use function intdiv;
use function is_string;
use function mb_convert_encoding;
use function mb_detect_encoding;
use function mb_detect_order;
use function mb_strlen;
use function substr_replace;

/**
 * Decode QR code payload bytes into text and metadata.
 *
 * QR symbols may mix multiple segment modes in a single payload. This parser
 * handles the mode switching, character set selection, structured append
 * metadata, and the text decoding rules defined by ISO 18004.
 *
 * @author Sean Owen
 */
final class DecodedBitStreamParser
{
    /**
     * QR alphanumeric character table from ISO 18004.
     */
    private static array $ALPHANUMERIC_CHARS = [
        '0', '1', '2', '3', '4', '5', '6', '7', '8', '9', 'A', 'B',
        'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N',
        'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z',
        ' ', '$', '%', '*', '+', '-', '.', '/', ':',
    ];

    /**
     * Hanzi subset identifier used by the QR specification.
     */
    private static int $GB2312_SUBSET = 1;

    /**
     * Decode QR payload bytes into a structured result.
     *
     * The parser consumes the bit stream segment by segment, honoring ECI,
     * FNC1, structured append, and the standard numeric/alphanumeric/byte/Kanji
     * modes.
     *
     * @psalm-param array<int, mixed> $bytes
     * @return DecoderResult Decoded payload and metadata.
     */
    public static function decode(
        array $bytes,
        Version $version,
        ErrorCorrectionLevel $ecLevel,
        ?array $hints,
    ): DecoderResult {
        $bits = new BitSource($bytes);
        $result = ''; // new StringBuilder(50);
        $byteSegments = [];
        $symbolSequence = -1;
        $parityData = -1;

        try {
            $currentCharacterSetECI = null;
            $fc1InEffect = false;
            $mode = '';

            do {
                // While still another segment to read...
                if ($bits->available() < 4) {
                    // OK, assume we're done. Really, a TERMINATOR mode should have been recorded here
                    $mode = Mode::$TERMINATOR;
                } else {
                    $mode = Mode::forBits($bits->readBits(4)); // mode is encoded by 4 bits
                }

                if ($mode === Mode::$TERMINATOR) {
                    continue;
                }

                if ($mode === Mode::$FNC1_FIRST_POSITION || $mode === Mode::$FNC1_SECOND_POSITION) {
                    // We do little with FNC1 except alter the parsed result a bit according to the spec
                    $fc1InEffect = true;
                } elseif ($mode === Mode::$STRUCTURED_APPEND) {
                    if ($bits->available() < 16) {
                        throw FormatException::getFormatInstance('Bits available < 16');
                    }
                    // sequence number and parity is added later to the result metadata
                    // Read next 8 bits (symbol sequence #) and 8 bits (parity data), then continue
                    $symbolSequence = $bits->readBits(8);
                    $parityData = $bits->readBits(8);
                } elseif ($mode === Mode::$ECI) {
                    // Count doesn't apply to ECI
                    $value = self::parseECIValue($bits);
                    $currentCharacterSetECI = CharacterSetECI::getCharacterSetECIByValue($value);

                    if ($currentCharacterSetECI === null) {
                        throw FormatException::getFormatInstance('Current character set ECI is null');
                    }
                } else {
                    // First handle Hanzi mode which does not start with character count
                    if ($mode === Mode::$HANZI) {
                        // chinese mode contains a sub set indicator right after mode indicator
                        $subset = $bits->readBits(4);
                        $countHanzi = $bits->readBits($mode->getCharacterCountBits($version));

                        if ($subset === self::$GB2312_SUBSET) {
                            self::decodeHanziSegment($bits, $result, $countHanzi);
                        }
                    } else {
                        // "Normal" QR code modes:
                        // How many characters will follow, encoded in this mode?
                        $count = $bits->readBits($mode->getCharacterCountBits($version));

                        if ($mode === Mode::$NUMERIC) {
                            self::decodeNumericSegment($bits, $result, $count);
                        } elseif ($mode === Mode::$ALPHANUMERIC) {
                            self::decodeAlphanumericSegment($bits, $result, $count, $fc1InEffect);
                        } elseif ($mode === Mode::$BYTE) {
                            self::decodeByteSegment($bits, $result, $count, $currentCharacterSetECI, $byteSegments, $hints);
                        } elseif ($mode === Mode::$KANJI) {
                            self::decodeKanjiSegment($bits, $result, $count);
                        } else {
                            throw FormatException::getFormatInstance("Unknown mode {$mode} to decode");
                        }
                    }
                }
            } while ($mode !== Mode::$TERMINATOR);
        } catch (InvalidArgumentException $e) {
            // from readBits() calls
            throw FormatException::getFormatInstance('Invalid argument exception when formatting: '.$e->getMessage());
        }

        return new DecoderResult(
            $bytes,
            $result,
            empty($byteSegments) ? null : $byteSegments,
            $ecLevel === null ? null : 'L', // ErrorCorrectionLevel::toString($ecLevel),
            $symbolSequence,
            $parityData,
        );
    }

    /**
     * Parse an ECI designator value from the bit stream.
     *
     * @param  BitSource       $bits Bit stream positioned at the ECI payload.
     * @throws FormatException When the ECI header is malformed.
     * @return int             Parsed ECI value.
     */
    private static function parseECIValue(BitSource $bits): int
    {
        $firstByte = $bits->readBits(8);

        if (($firstByte & 0x80) === 0) {
            // just one byte
            return $firstByte & 0x7F;
        }

        if (($firstByte & 0xC0) === 0x80) {
            // two bytes
            $secondByte = $bits->readBits(8);

            return (($firstByte & 0x3F) << 8) | $secondByte;
        }

        if (($firstByte & 0xE0) === 0xC0) {
            // three bytes
            $secondThirdBytes = $bits->readBits(16);

            return (($firstByte & 0x1F) << 16) | $secondThirdBytes;
        }

        throw FormatException::getFormatInstance('ECI Value parsing failed.');
    }

    /**
     * Decode a Hanzi segment into UTF-8 text.
     *
     * @psalm-param '' $result
     */
    private static function decodeHanziSegment(
        BitSource $bits,
        string &$result,
        int $count,
    ): void {
        // Don't crash trying to read more bits than we have available.
        if ($count * 13 > $bits->available()) {
            throw FormatException::getFormatInstance('Trying to read more bits than we have available');
        }

        // Each character will require 2 bytes. Read the characters as 2-byte pairs
        // and decode as GB2312 afterwards
        $buffer = fill_array(0, 2 * $count, 0);
        $offset = 0;

        while ($count > 0) {
            // Each 13 bits encodes a 2-byte character
            $twoBytes = $bits->readBits(13);
            $assembledTwoBytes = (($twoBytes / 0x0_60) << 8) | ($twoBytes % 0x0_60);

            if ($assembledTwoBytes < 0x0_03_BF) {
                // In the 0xA1A1 to 0xAAFE range
                $assembledTwoBytes += 0x0_A1_A1;
            } else {
                // In the 0xB0A1 to 0xFAFE range
                $assembledTwoBytes += 0x0_A6_A1;
            }
            $buffer[$offset] = ($assembledTwoBytes >> 8) & 0xFF; // (byte)
            $buffer[$offset + 1] = $assembledTwoBytes & 0xFF; // (byte)
            $offset += 2;
            --$count;
        }
        $result .= iconv('GB2312', 'UTF-8', implode('', $buffer));
    }

    /**
     * Decode a numeric segment into the output string.
     *
     * @param  BitSource       $bits   Bit stream positioned at the numeric payload.
     * @param  string          $result Accumulated output text.
     * @param  int             $count  Number of numeric characters to read.
     * @throws FormatException When the segment is truncated or malformed.
     */
    private static function decodeNumericSegment(
        BitSource $bits,
        string &$result,
        int $count,
    ): void {
        // Read three digits at a time
        while ($count >= 3) {
            // Each 10 bits encodes three digits
            if ($bits->available() < 10) {
                throw FormatException::getFormatInstance('Not enough bits available');
            }
            $threeDigitsBits = $bits->readBits(10);

            if ($threeDigitsBits >= 1_000) {
                throw FormatException::getFormatInstance('Too many three digit bits');
            }
            $result .= self::toAlphaNumericChar(intdiv($threeDigitsBits, 100));
            $result .= self::toAlphaNumericChar(intdiv($threeDigitsBits, 10) % 10);
            $result .= self::toAlphaNumericChar($threeDigitsBits % 10);
            $count -= 3;
        }

        if ($count === 2) {
            // Two digits left over to read, encoded in 7 bits
            if ($bits->available() < 7) {
                throw FormatException::getFormatInstance('Two digits left over to read, encoded in 7 bits, but only '.$bits->available().' bits available');
            }
            $twoDigitsBits = $bits->readBits(7);

            if ($twoDigitsBits >= 100) {
                throw FormatException::getFormatInstance("Too many bits: {$twoDigitsBits} expected < 100");
            }
            $result .= self::toAlphaNumericChar(intdiv($twoDigitsBits, 10));
            $result .= self::toAlphaNumericChar($twoDigitsBits % 10);
        } elseif ($count === 1) {
            // One digit left over to read
            if ($bits->available() < 4) {
                throw FormatException::getFormatInstance('One digit left to read, but < 4 bits available');
            }
            $digitBits = $bits->readBits(4);

            if ($digitBits >= 10) {
                throw FormatException::getFormatInstance("Too many bits: {$digitBits} expected < 10");
            }
            $result .= self::toAlphaNumericChar($digitBits);
        }
    }

    /**
     * Map an alphanumeric code point to its character.
     *
     * @param  float|int       $value QR alphanumeric table index.
     * @throws FormatException If the value is outside the table.
     * @return string          One decoded character.
     */
    private static function toAlphaNumericChar(int|float $value)
    {
        $intVal = (int) $value;

        if ($intVal >= count(self::$ALPHANUMERIC_CHARS)) {
            throw FormatException::getFormatInstance("{$intVal} is too many alphanumeric chars");
        }

        return self::$ALPHANUMERIC_CHARS[(int) $intVal];
    }

    /**
     * Decode an alphanumeric segment into the output string.
     *
     * @param  BitSource       $bits        Bit stream positioned at the alphanumeric payload.
     * @param  string          $result      Accumulated output text.
     * @param  int             $count       Number of alphanumeric characters to read.
     * @param  bool            $fc1InEffect Whether FNC1 replacement rules are active.
     * @throws FormatException When the segment is truncated or malformed.
     */
    private static function decodeAlphanumericSegment(
        BitSource $bits,
        string &$result,
        int $count,
        bool $fc1InEffect,
    ): void {
        // Read two characters at a time
        $start = mb_strlen((string) $result);

        while ($count > 1) {
            if ($bits->available() < 11) {
                throw FormatException::getFormatInstance('Not enough bits available to read two expected characters');
            }
            $nextTwoCharsBits = $bits->readBits(11);
            $result .= self::toAlphaNumericChar(intdiv($nextTwoCharsBits, 45));
            $result .= self::toAlphaNumericChar($nextTwoCharsBits % 45);
            $count -= 2;
        }

        if ($count === 1) {
            // special case: one character left
            if ($bits->available() < 6) {
                throw FormatException::getFormatInstance('Not enough bits available to read one expected character');
            }
            $result .= self::toAlphaNumericChar($bits->readBits(6));
        }

        // See section 6.4.8.1, 6.4.8.2
        if (!$fc1InEffect) {
            return;
        }

        // We need to massage the result a bit if in an FNC1 mode:
        for ($i = $start; $i < mb_strlen((string) $result); ++$i) {
            if ($result[$i] !== '%') {
                continue;
            }

            if ($i < mb_strlen((string) $result) - 1 && $result[$i + 1] === '%') {
                // %% is rendered as %
                $result = substr_replace($result, '', $i + 1, 1); // deleteCharAt(i + 1);
            } else {
                // In alpha mode, % should be converted to FNC1 separator 0x1D
                $result[$i] = chr(0x1D);
            }
        }
    }

    /**
     * Decode a byte segment using the selected character set.
     *
     * @param  BitSource                 $bits                   Bit stream positioned at the byte payload.
     * @param  string                    $result                 Accumulated output text.
     * @param  int                       $count                  Number of bytes to read.
     * @param  null|CharacterSetECI      $currentCharacterSetECI Active ECI, if any.
     * @param  array<int, int>           $byteSegments           Collected raw byte segment data.
     * @param  null|array<string, mixed> $hints                  Optional decoder hints.
     * @throws FormatException           When the segment is truncated.
     */
    private static function decodeByteSegment(
        BitSource $bits,
        string &$result,
        int $count,
        ?CharacterSetECI $currentCharacterSetECI,
        array &$byteSegments,
        ?array $hints,
    ): void {
        // Don't crash trying to read more bits than we have available.
        if (8 * $count > $bits->available()) {
            throw FormatException::getFormatInstance('Trying to read more bits than we have available');
        }

        $readBytes = fill_array(0, $count, 0);

        for ($i = 0; $i < $count; ++$i) {
            $readBytes[$i] = $bits->readBits(8); // (byte)
        }
        $text = implode('', array_map('chr', $readBytes));

        if ($hints !== null && array_key_exists('BINARY_MODE', $hints) && $hints['BINARY_MODE']) {
            $result .= $text;
        } else {
            $encoding = '';

            if ($currentCharacterSetECI === null) {
                // The spec isn't clear on this mode; see
                // section 6.4.5: t does not say which encoding to assuming
                // upon decoding. I have seen ISO-8859-1 used as well as
                // Shift_JIS -- without anything like an ECI designator to
                // give a hint.

                try {
                    $encodingHint = self::extractCharacterSetHint($hints);
                    $encoding = $encodingHint ?? mb_detect_encoding($text, mb_detect_order(), true);
                } catch (ValueError $e) {
                    $encoding = mb_detect_encoding($text, mb_detect_order(), false);
                }
            } else {
                $encoding = $currentCharacterSetECI->name();
            }
            $result .= mb_convert_encoding($text, $encoding); // (new String(readBytes, encoding));
        }

        $byteSegments = array_merge($byteSegments, $readBytes);
    }

    /**
     * Return the requested text encoding hint when one is explicitly supplied.
     */
    private static function extractCharacterSetHint(?array $hints): ?string
    {
        if ($hints === null || !array_key_exists('CHARACTER_SET', $hints)) {
            return null;
        }

        $characterSet = $hints['CHARACTER_SET'];

        if (!is_string($characterSet) || $characterSet === '') {
            return null;
        }

        return $characterSet;
    }

    /**
     * Decode a Kanji segment into UTF-8 text.
     *
     * @param  BitSource       $bits   Bit stream positioned at the Kanji payload.
     * @param  string          $result Accumulated output text.
     * @param  int             $count  Number of Kanji characters to read.
     * @throws FormatException When the segment is truncated or malformed.
     */
    private static function decodeKanjiSegment(
        BitSource $bits,
        string &$result,
        int $count,
    ): void {
        // Don't crash trying to read more bits than we have available.
        if ($count * 13 > $bits->available()) {
            throw FormatException::getFormatInstance('Trying to read more bits than we have available');
        }

        // Each character will require 2 bytes. Read the characters as 2-byte pairs
        // and decode as Shift_JIS afterwards
        $buffer = [0, 2 * $count, 0];
        $offset = 0;

        while ($count > 0) {
            // Each 13 bits encodes a 2-byte character
            $twoBytes = $bits->readBits(13);
            $assembledTwoBytes = (($twoBytes / 0x0_C0) << 8) | ($twoBytes % 0x0_C0);

            if ($assembledTwoBytes < 0x0_1F_00) {
                // In the 0x8140 to 0x9FFC range
                $assembledTwoBytes += 0x0_81_40;
            } else {
                // In the 0xE040 to 0xEBBF range
                $assembledTwoBytes += 0x0_C1_40;
            }
            $buffer[$offset] = $assembledTwoBytes >> 8; // (byte)
            $buffer[$offset + 1] = $assembledTwoBytes; // (byte)
            $offset += 2;
            --$count;
        }
        // Shift_JIS may not be supported in some environments:

        $result .= iconv('shift-jis', 'utf-8', implode('', $buffer));
    }

    /**
     * Constructor intentionally disabled; this is a static utility class.
     */
    private function DecodedBitStreamParser(): void {}
}
