<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Decoder;

use Cline\Qr\Decoder\Common\Detector\MathUtils;

use function floatToIntBits;

/**
 * Shared geometric point behavior for detector result points.
 *
 * The detector needs one common representation for plain corner points and the
 * specialized finder/alignment pattern points that carry extra metadata. This
 * abstract base keeps the geometry logic centralized while allowing each
 * concrete point type to remain `final`.
 *
 * @author Sean Owen
 */
abstract class AbstractResultPoint
{
    protected float $x;

    protected float $y;

    public function __construct($x, $y)
    {
        $this->x = (float) $x;
        $this->y = (float) $y;
    }

    /**
     * Order three points so the longest side becomes the base of the triangle.
     *
     * @param array<int, self> $patterns
     *
     * @return array<int, self>
     */
    public static function orderBestPatterns(array $patterns): array
    {
        $zeroOneDistance = self::distance($patterns[0], $patterns[1]);
        $oneTwoDistance = self::distance($patterns[1], $patterns[2]);
        $zeroTwoDistance = self::distance($patterns[0], $patterns[2]);

        $pointA = '';
        $pointB = '';
        $pointC = '';

        if ($oneTwoDistance >= $zeroOneDistance && $oneTwoDistance >= $zeroTwoDistance) {
            $pointB = $patterns[0];
            $pointA = $patterns[1];
            $pointC = $patterns[2];
        } elseif ($zeroTwoDistance >= $oneTwoDistance && $zeroTwoDistance >= $zeroOneDistance) {
            $pointB = $patterns[1];
            $pointA = $patterns[0];
            $pointC = $patterns[2];
        } else {
            $pointB = $patterns[2];
            $pointA = $patterns[0];
            $pointC = $patterns[1];
        }

        if (self::crossProductZ($pointA, $pointB, $pointC) < 0.0) {
            $temp = $pointA;
            $pointA = $pointC;
            $pointC = $temp;
        }

        $patterns[0] = $pointA;
        $patterns[1] = $pointB;
        $patterns[2] = $pointC;

        return $patterns;
    }

    public static function distance($pattern1, $pattern2)
    {
        return MathUtils::distance($pattern1->x, $pattern1->y, $pattern2->x, $pattern2->y);
    }

    public function getX(): float
    {
        return $this->x;
    }

    public function getY(): float
    {
        return $this->y;
    }

    public function equals($other): bool
    {
        if ($other instanceof self) {
            return $this->x === $other->x && $this->y === $other->y;
        }

        return false;
    }

    public function hashCode()
    {
        return 31 * floatToIntBits($this->x) + floatToIntBits($this->y);
    }

    public function toString(): string
    {
        return '('.$this->x.','.$this->y.')';
    }

    /**
     * Return the z component of the cross product for the point ordering test.
     *
     * @param mixed $pointA
     * @param mixed $pointB
     * @param mixed $pointC
     */
    private static function crossProductZ($pointA, $pointB, $pointC)
    {
        $bX = $pointB->x;
        $bY = $pointB->y;

        return (($pointC->x - $bX) * ($pointA->y - $bY)) - (($pointC->y - $bY) * ($pointA->x - $bX));
    }
}
