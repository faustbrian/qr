<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Decoder\Common;

use Cline\Qr\Decoder\InvalidArgumentException;
use UnexpectedValueException;

use function array_search;
use function mb_strtoupper;

/**
 * Registry of QR character set ECI assignments.
 *
 * QR payload decoding needs to translate between ECI values, canonical names,
 * and a handful of compatibility aliases. This class centralizes that mapping
 * so the decoder can resolve character encodings without leaking lookup logic
 * into the parsing code.
 * @author Brian Faust <brian@cline.sh>
 */
final class CharacterSetECI
{
    /**
     * #@+
     * Character set constants.
     */
    public const int CP437 = 0;

    public const int ISO8859_1 = 1;

    public const int ISO8859_2 = 4;

    public const int ISO8859_3 = 5;

    public const int ISO8859_4 = 6;

    public const int ISO8859_5 = 7;

    public const int ISO8859_6 = 8;

    public const int ISO8859_7 = 9;

    public const int ISO8859_8 = 10;

    public const int ISO8859_9 = 11;

    public const int ISO8859_10 = 12;

    public const int ISO8859_11 = 13;

    public const int ISO8859_12 = 14;

    public const int ISO8859_13 = 15;

    public const int ISO8859_14 = 16;

    public const int ISO8859_15 = 17;

    public const int ISO8859_16 = 18;

    public const int SJIS = 20;

    public const int CP1250 = 21;

    public const int CP1251 = 22;

    public const int CP1252 = 23;

    public const int CP1256 = 24;

    public const int UNICODE_BIG_UNMARKED = 25;

    public const int UTF8 = 26;

    public const int ASCII = 27;

    public const int BIG5 = 28;

    public const int GB18030 = 29;

    public const int EUC_KR = 30;

    /**
     * Map between character names and their ECI values.
     */
    private static array $nameToEci = [
        'ISO-8859-1' => self::ISO8859_1,
        'ISO-8859-2' => self::ISO8859_2,
        'ISO-8859-3' => self::ISO8859_3,
        'ISO-8859-4' => self::ISO8859_4,
        'ISO-8859-5' => self::ISO8859_5,
        'ISO-8859-6' => self::ISO8859_6,
        'ISO-8859-7' => self::ISO8859_7,
        'ISO-8859-8' => self::ISO8859_8,
        'ISO-8859-9' => self::ISO8859_9,
        'ISO-8859-10' => self::ISO8859_10,
        'ISO-8859-11' => self::ISO8859_11,
        'ISO-8859-12' => self::ISO8859_12,
        'ISO-8859-13' => self::ISO8859_13,
        'ISO-8859-14' => self::ISO8859_14,
        'ISO-8859-15' => self::ISO8859_15,
        'ISO-8859-16' => self::ISO8859_16,
        'SHIFT-JIS' => self::SJIS,
        'WINDOWS-1250' => self::CP1250,
        'WINDOWS-1251' => self::CP1251,
        'WINDOWS-1252' => self::CP1252,
        'WINDOWS-1256' => self::CP1256,
        'UTF-16BE' => self::UNICODE_BIG_UNMARKED,
        'UTF-8' => self::UTF8,
        'ASCII' => self::ASCII,
        'GBK' => self::GB18030,
        'EUC-KR' => self::EUC_KR,
    ];

    /** #@- */
    /**
     * Additional possible values for character sets.
     */
    private static array $additionalValues = [
        self::CP437 => 2,
        self::ASCII => 170,
    ];

    private static int|string|null $name = null;

    /**
     * Resolve a character set ECI from its numeric QR value.
     *
     * Some names have compatibility aliases, so the lookup first normalizes the
     * numeric value through the compatibility table before constructing the ECI.
     *
     * @return null|self `null` when the value does not map to a known encoding
     */
    public static function getCharacterSetECIByValue(int $value)
    {
        if ($value < 0 || $value >= 900) {
            throw InvalidArgumentException::withMessage('Value must be between 0 and 900');
        }

        if (false !== ($key = array_search($value, self::$additionalValues, true))) {
            $value = $key;
        }
        array_search($value, self::$nameToEci, true);

        try {
            self::setName($value);

            return new self($value);
        } catch (UnexpectedValueException) {
            return null;
        }
    }

    /**
     * Return the most recently resolved canonical ECI name.
     */
    public static function name(): string|int|null
    {
        return self::$name;
    }

    /**
     * Resolve a character set ECI from its canonical or alias name.
     *
     * @return null|self `null` when the name is not recognized
     */
    public static function getCharacterSetECIByName(string $name)
    {
        $name = mb_strtoupper($name);

        if (isset(self::$nameToEci[$name])) {
            return new self(self::$nameToEci[$name]);
        }

        return null;
    }

    /**
     * Remember the canonical name for the supplied ECI value.
     *
     * @param int|string $value ECI value or alias key
     *
     * @psalm-param array-key $value
     *
     * @return null|true `true` when a name was recorded, `null` otherwise
     */
    private static function setName($value)
    {
        foreach (self::$nameToEci as $name => $key) {
            if ($key === $value) {
                self::$name = $name;

                return true;
            }
        }

        if (self::$name !== null) {
            return;
        }

        foreach (self::$additionalValues as $name => $key) {
            if ($key === $value) {
                self::$name = $name;

                return true;
            }
        }
    }
}
