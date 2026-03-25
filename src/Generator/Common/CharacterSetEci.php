<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Generator\Common;

use Cline\Qr\Generator\Internal\Exception\InvalidArgumentException;

use function in_array;
use function mb_strtolower;

/**
 * QR character set assignments used when encoding and decoding ECI values.
 *
 * The enum bridges the compact QR code numeric identifiers with the more
 * familiar encoding names that callers pass in application code. Several cases
 * expose aliases because the QR specification and the surrounding ecosystem do
 * not always use the same label for a given charset.
 * @author Brian Faust <brian@cline.sh>
 */
enum CharacterSetEci: int
{
    case CP437 = 0;
    case ISO8859_1 = 1;
    case ISO8859_2 = 4;
    case ISO8859_3 = 5;
    case ISO8859_4 = 6;
    case ISO8859_5 = 7;
    case ISO8859_6 = 8;
    case ISO8859_7 = 9;
    case ISO8859_8 = 10;
    case ISO8859_9 = 11;
    case ISO8859_10 = 12;
    case ISO8859_11 = 13;
    case ISO8859_12 = 14;
    case ISO8859_13 = 15;
    case ISO8859_14 = 16;
    case ISO8859_15 = 17;
    case ISO8859_16 = 18;
    case SJIS = 20;
    case CP1250 = 21;
    case CP1251 = 22;
    case CP1252 = 23;
    case CP1256 = 24;
    case UNICODE_BIG_UNMARKED = 25;
    case UTF8 = 26;
    case ASCII = 27;
    case BIG5 = 28;
    case GB18030 = 29;
    case EUC_KR = 30;

    /**
     * Resolve an ECI entry from its numeric assignment value.
     *
     * @throws InvalidArgumentException if value is not between 0 and 900
     *
     * The method returns `null` for valid but unassigned ranges. That lets the
     * caller distinguish malformed input from an unknown-but-valid slot.
     */
    public static function getCharacterSetEciByValue(int $value): ?self
    {
        if ($value < 0 || $value >= 900) {
            throw InvalidArgumentException::withMessage('Value must be between 0 and 900');
        }

        foreach (self::cases() as $eci) {
            if (in_array($value, $eci->values(), true)) {
                return $eci;
            }
        }

        return null;
    }

    /**
     * Resolve an ECI entry from either its enum case name or a known alias.
     *
     * The lookup is case-insensitive and includes the alternate encoding names
     * accepted by the QR ecosystem for several encodings.
     */
    public static function getCharacterSetEciByName(string $name): ?self
    {
        $name = mb_strtolower($name);

        foreach (self::cases() as $eci) {
            if (mb_strtolower($eci->name) === $name) {
                return $eci;
            }

            foreach ($eci->otherEncodingNames() as $otherEncodingName) {
                if (mb_strtolower($otherEncodingName) === $name) {
                    return $eci;
                }
            }
        }

        return null;
    }

    /**
     * Return the ECI assignment value used on the QR payload.
     */
    public function getValue(): int
    {
        return $this->value;
    }

    /**
     * Return every numeric assignment value recognized by this case.
     *
     * @return list<int>
     */
    private function values(): array
    {
        return match ($this) {
            self::CP437 => [0, 2],
            self::ISO8859_1 => [1, 3],
            self::ASCII => [27, 170],
            default => [$this->value],
        };
    }

    /**
     * Return the alternate encoding names recognized for this case.
     *
     * @return list<string>
     */
    private function otherEncodingNames(): array
    {
        return match ($this) {
            self::ISO8859_1 => ['ISO-8859-1'],
            self::ISO8859_2 => ['ISO-8859-2'],
            self::ISO8859_3 => ['ISO-8859-3'],
            self::ISO8859_4 => ['ISO-8859-4'],
            self::ISO8859_5 => ['ISO-8859-5'],
            self::ISO8859_6 => ['ISO-8859-6'],
            self::ISO8859_7 => ['ISO-8859-7'],
            self::ISO8859_8 => ['ISO-8859-8'],
            self::ISO8859_9 => ['ISO-8859-9'],
            self::ISO8859_10 => ['ISO-8859-10'],
            self::ISO8859_11 => ['ISO-8859-11'],
            self::ISO8859_12 => ['ISO-8859-12'],
            self::ISO8859_13 => ['ISO-8859-13'],
            self::ISO8859_14 => ['ISO-8859-14'],
            self::ISO8859_15 => ['ISO-8859-15'],
            self::ISO8859_16 => ['ISO-8859-16'],
            self::SJIS => ['Shift_JIS'],
            self::CP1250 => ['windows-1250'],
            self::CP1251 => ['windows-1251'],
            self::CP1252 => ['windows-1252'],
            self::CP1256 => ['windows-1256'],
            self::UNICODE_BIG_UNMARKED => ['UTF-16BE', 'UnicodeBig'],
            self::UTF8 => ['UTF-8'],
            self::ASCII => ['US-ASCII'],
            self::GB18030 => ['GB2312', 'EUC_CN', 'GBK'],
            self::EUC_KR => ['EUC-KR'],
            default => [],
        };
    }
}
