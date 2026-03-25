<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Decoder\Qrcode\Decoder;

use InvalidArgumentException;

/**
 * QR Code data-encoding mode metadata.
 *
 * Each mode controls how subsequent bits should be interpreted and how many
 * character-count bits are reserved for the active version range. The decoder
 * keeps the QR specification's original bit assignments so the low-level
 * parser can move between mode bits and length fields without additional
 * translation.
 *
 * @author Sean Owen
 */
final class Mode
{
    public static $TERMINATOR;

    public static $NUMERIC;

    public static $ALPHANUMERIC;

    public static $STRUCTURED_APPEND;

    public static $BYTE;

    public static $ECI;

    public static $KANJI;

    public static $FNC1_FIRST_POSITION;

    public static $FNC1_SECOND_POSITION;

    public static $HANZI;

    public function __construct(
        private readonly array $characterCountBitsForVersions,
        private readonly int $bits,
    ) {}

    /**
     * Initialize the static mode singletons used during parsing.
     */
    public static function Init(): void
    {
        self::$TERMINATOR = new self([0, 0, 0], 0x00); // Not really a mode...
        self::$NUMERIC = new self([10, 12, 14], 0x01);
        self::$ALPHANUMERIC = new self([9, 11, 13], 0x02);
        self::$STRUCTURED_APPEND = new self([0, 0, 0], 0x03); // Not supported
        self::$BYTE = new self([8, 16, 16], 0x04);
        self::$ECI = new self([0, 0, 0], 0x07); // character counts don't apply
        self::$KANJI = new self([8, 10, 12], 0x08);
        self::$FNC1_FIRST_POSITION = new self([0, 0, 0], 0x05);
        self::$FNC1_SECOND_POSITION = new self([0, 0, 0], 0x09);

        /** See GBT 18284-2000; "Hanzi" is a transliteration of this mode name. */
        self::$HANZI = new self([8, 10, 12], 0x0D);
    }

    /**
     * Resolve a raw 4-bit mode indicator into the corresponding mode object.
     *
     * @param int $bits Four-bit QR Code mode indicator.
     *
     * @throws InvalidArgumentException When the bit pattern does not match a
     *                                  QR Code mode defined by the standard.
     * @return self                     Mode encoded by these bits.
     */
    public static function forBits($bits)
    {
        return match ($bits) {
            0x0 => self::$TERMINATOR,
            0x1 => self::$NUMERIC,
            0x2 => self::$ALPHANUMERIC,
            0x3 => self::$STRUCTURED_APPEND,
            0x4 => self::$BYTE,
            0x5 => self::$FNC1_FIRST_POSITION,
            0x7 => self::$ECI,
            0x8 => self::$KANJI,
            0x9 => self::$FNC1_SECOND_POSITION,
            0xD => self::$HANZI,
            default => throw \Cline\Qr\Decoder\InvalidArgumentException::withMessage(),
        };
    }

    /**
     * Return the character-count field width for the supplied version.
     *
     * QR Code mode headers reserve different length widths depending on the
     * version group the symbol belongs to. The method maps version numbers to
     * the correct width without exposing the table to callers.
     *
     * @param Version $version Version whose character-count width is needed.
     *
     * @return int Number of bits consumed by the character-count field.
     */
    public function getCharacterCountBits(version $version)
    {
        $number = $version->getVersionNumber();
        $offset = 0;

        if ($number <= 9) {
            $offset = 0;
        } elseif ($number <= 26) {
            $offset = 1;
        } else {
            $offset = 2;
        }

        return $this->characterCountBitsForVersions[$offset];
    }

    /**
     * Return the raw mode bits as stored in the stream.
     */
    public function getBits()
    {
        return $this->bits;
    }
}

Mode::Init();
