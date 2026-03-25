<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Generator\Common;

/**
 * QR mode indicators used while choosing payload encoding width.
 *
 * The enum maps the compact 4-bit mode markers from the QR specification to
 * the variable-length character-count field widths required by each version
 * range. That makes the encoder and matrix writer agree on how much header
 * space a payload needs before any data bytes are written.
 * @author Brian Faust <brian@cline.sh>
 */
enum Mode: int
{
    case TERMINATOR = 0x00;
    case NUMERIC = 0x01;
    case ALPHANUMERIC = 0x02;
    case STRUCTURED_APPEND = 0x03;
    case BYTE = 0x04;
    case ECI = 0x07;
    case KANJI = 0x08;
    case FNC1_FIRST_POSITION = 0x05;
    case FNC1_SECOND_POSITION = 0x09;
    case HANZI = 0x0D;

    /**
     * Return the width of the character-count field for the supplied version.
     *
     * QR versions are grouped into three ranges because the number of bits
     * allocated to the length field changes as the symbol grows. Modes that do
     * not carry a character count always return `0`.
     */
    public function getCharacterCountBits(Version $version): int
    {
        $offset = match (true) {
            $version->getVersionNumber() <= 9 => 0,
            $version->getVersionNumber() <= 26 => 1,
            default => 2,
        };

        return match ($this) {
            self::TERMINATOR, self::STRUCTURED_APPEND, self::ECI,
            self::FNC1_FIRST_POSITION, self::FNC1_SECOND_POSITION => [0, 0, 0][$offset],
            self::NUMERIC => [10, 12, 14][$offset],
            self::ALPHANUMERIC => [9, 11, 13][$offset],
            self::BYTE => [8, 16, 16][$offset],
            self::KANJI, self::HANZI => [8, 10, 12][$offset],
        };
    }

    /**
     * Return the raw 4-bit mode indicator value.
     */
    public function getBits(): int
    {
        return $this->value;
    }
}
