<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Generator\Encoder;

use Cline\Qr\Generator\Common\BitUtils;
use Cline\Qr\Generator\Internal\Exception\InvalidArgumentException;

use function abs;
use function floor;

/**
 * Evaluate and apply QR data-mask penalty rules.
 *
 * The encoder uses this helper to score candidate masks before choosing the
 * final pattern. The penalty constants mirror the QR specification so the
 * encoded symbol prefers runs, blocks, and dark ratios that scanners can read
 * reliably.
 * @author Brian Faust <brian@cline.sh>
 */
final class MaskUtil
{
    /**
     * #@+
     * Penalty weights from section 6.8.2.1
     */
    public const int N1 = 3;

    public const int N2 = 3;

    public const int N3 = 40;

    public const int N4 = 10;

    /**
     * #@-
     */
    private function __construct() {}

    /**
     * Penalize long runs of identical modules horizontally and vertically.
     */
    public static function applyMaskPenaltyRule1(ByteMatrix $matrix): int
    {
        return
            self::applyMaskPenaltyRule1Internal($matrix, true)
            + self::applyMaskPenaltyRule1Internal($matrix, false);
    }

    /**
     * Penalize solid 2x2 blocks of the same color.
     */
    public static function applyMaskPenaltyRule2(ByteMatrix $matrix): int
    {
        $penalty = 0;
        $array = $matrix->getArray();
        $width = $matrix->getWidth();
        $height = $matrix->getHeight();

        for ($y = 0; $y < $height - 1; ++$y) {
            for ($x = 0; $x < $width - 1; ++$x) {
                $value = $array[$y][$x];

                if ($value !== $array[$y][$x + 1]
                    || $value !== $array[$y + 1][$x]
                    || $value !== $array[$y + 1][$x + 1]
                ) {
                    continue;
                }

                ++$penalty;
            }
        }

        return self::N2 * $penalty;
    }

    /**
     * Penalize finder-like run patterns that can confuse scanners.
     */
    public static function applyMaskPenaltyRule3(ByteMatrix $matrix): int
    {
        $penalty = 0;
        $array = $matrix->getArray();
        $width = $matrix->getWidth();
        $height = $matrix->getHeight();

        for ($y = 0; $y < $height; ++$y) {
            for ($x = 0; $x < $width; ++$x) {
                if ($x + 6 < $width
                    && 1 === $array[$y][$x]
                    && 0 === $array[$y][$x + 1]
                    && 1 === $array[$y][$x + 2]
                    && 1 === $array[$y][$x + 3]
                    && 1 === $array[$y][$x + 4]
                    && 0 === $array[$y][$x + 5]
                    && 1 === $array[$y][$x + 6]
                    && (
                        (
                            $x + 10 < $width
                            && 0 === $array[$y][$x + 7]
                            && 0 === $array[$y][$x + 8]
                            && 0 === $array[$y][$x + 9]
                            && 0 === $array[$y][$x + 10]
                        )
                        || (
                            $x - 4 >= 0
                            && 0 === $array[$y][$x - 1]
                            && 0 === $array[$y][$x - 2]
                            && 0 === $array[$y][$x - 3]
                            && 0 === $array[$y][$x - 4]
                        )
                    )
                ) {
                    $penalty += self::N3;
                }

                if ($y + 6 >= $height
                    || 1 !== $array[$y][$x]
                    || 0 !== $array[$y + 1][$x]
                    || 1 !== $array[$y + 2][$x]
                    || 1 !== $array[$y + 3][$x]
                    || 1 !== $array[$y + 4][$x]
                    || 0 !== $array[$y + 5][$x]
                    || 1 !== $array[$y + 6][$x]
                    || (
                        (
                            $y + 10 >= $height
                            || 0 !== $array[$y + 7][$x]
                            || 0 !== $array[$y + 8][$x]
                            || 0 !== $array[$y + 9][$x]
                            || 0 !== $array[$y + 10][$x]
                        )
                        && (
                            $y - 4 < 0
                            || 0 !== $array[$y - 1][$x]
                            || 0 !== $array[$y - 2][$x]
                            || 0 !== $array[$y - 3][$x]
                            || 0 !== $array[$y - 4][$x]
                        )
                    )
                ) {
                    continue;
                }

                $penalty += self::N3;
            }
        }

        return $penalty;
    }

    /**
     * Penalize symbols whose dark/light balance drifts too far from 50%.
     */
    public static function applyMaskPenaltyRule4(ByteMatrix $matrix): int
    {
        $numDarkCells = 0;

        $array = $matrix->getArray();
        $width = $matrix->getWidth();
        $height = $matrix->getHeight();

        for ($y = 0; $y < $height; ++$y) {
            $arrayY = $array[$y];

            for ($x = 0; $x < $width; ++$x) {
                if (1 !== $arrayY[$x]) {
                    continue;
                }

                ++$numDarkCells;
            }
        }

        $numTotalCells = $height * $width;
        $darkRatio = $numDarkCells / $numTotalCells;
        $fixedPercentVariances = (int) floor(abs($darkRatio - 0.5) * 20);

        return $fixedPercentVariances * self::N4;
    }

    /**
     * Return whether the data mask flips the bit at the given coordinate.
     *
     * @throws InvalidArgumentException if an invalid mask pattern was supplied
     */
    public static function getDataMaskBit(int $maskPattern, int $x, int $y): bool
    {
        switch ($maskPattern) {
            case 0:
                $intermediate = ($y + $x) & 0x1;

                break;

            case 1:
                $intermediate = $y & 0x1;

                break;

            case 2:
                $intermediate = $x % 3;

                break;

            case 3:
                $intermediate = ($y + $x) % 3;

                break;

            case 4:
                $intermediate = (BitUtils::unsignedRightShift($y, 1) + (int) ($x / 3)) & 0x1;

                break;

            case 5:
                $temp = $y * $x;
                $intermediate = ($temp & 0x1) + ($temp % 3);

                break;

            case 6:
                $temp = $y * $x;
                $intermediate = (($temp & 0x1) + ($temp % 3)) & 0x1;

                break;

            case 7:
                $temp = $y * $x;
                $intermediate = (($temp % 3) + (($y + $x) & 0x1)) & 0x1;

                break;

            default:
                throw InvalidArgumentException::withMessage('Invalid mask pattern: '.$maskPattern);
        }

        return 0 === $intermediate;
    }

    /**
     * Shared implementation for the horizontal and vertical run detector.
     */
    private static function applyMaskPenaltyRule1Internal(ByteMatrix $matrix, bool $isHorizontal): int
    {
        $penalty = 0;
        $iLimit = $isHorizontal ? $matrix->getHeight() : $matrix->getWidth();
        $jLimit = $isHorizontal ? $matrix->getWidth() : $matrix->getHeight();
        $array = $matrix->getArray();

        for ($i = 0; $i < $iLimit; ++$i) {
            $numSameBitCells = 0;
            $prevBit = -1;

            for ($j = 0; $j < $jLimit; ++$j) {
                $bit = $isHorizontal ? $array[$i][$j] : $array[$j][$i];

                if ($bit === $prevBit) {
                    ++$numSameBitCells;
                } else {
                    if ($numSameBitCells >= 5) {
                        $penalty += self::N1 + ($numSameBitCells - 5);
                    }

                    $numSameBitCells = 1;
                    $prevBit = $bit;
                }
            }

            if ($numSameBitCells < 5) {
                continue;
            }

            $penalty += self::N1 + ($numSameBitCells - 5);
        }

        return $penalty;
    }
}
