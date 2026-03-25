<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Generator\Renderer\Image;

use function cos;
use function deg2rad;
use function sin;

/**
 * Lightweight affine transformation matrix for image-backend bookkeeping.
 *
 * The Imagick backend uses this to mirror the scale, translate, and rotate
 * operations it sends to the drawing context so gradient dimensions can be
 * transformed consistently with the paths they fill.
 * @author Brian Faust <brian@cline.sh>
 */
final class TransformationMatrix
{
    /** @var array<float> */
    private array $values;

    public function __construct()
    {
        $this->values = [1, 0, 0, 1, 0, 0];
    }

    /**
     * Create a uniform scale matrix.
     */
    public static function scale(float $size): self
    {
        $matrix = new self();
        $matrix->values = [$size, 0, 0, $size, 0, 0];

        return $matrix;
    }

    /**
     * Create a translation matrix.
     */
    public static function translate(float $x, float $y): self
    {
        $matrix = new self();
        $matrix->values = [1, 0, 0, 1, $x, $y];

        return $matrix;
    }

    /**
     * Create a rotation matrix for the supplied degree value.
     */
    public static function rotate(int $degrees): self
    {
        $matrix = new self();
        $rad = deg2rad($degrees);
        $matrix->values = [cos($rad), sin($rad), -sin($rad), cos($rad), 0, 0];

        return $matrix;
    }

    /**
     * Multiply this matrix by another affine transform and return the result.
     */
    public function multiply(self $other): self
    {
        $matrix = new self();
        $matrix->values[0] = $this->values[0] * $other->values[0] + $this->values[2] * $other->values[1];
        $matrix->values[1] = $this->values[1] * $other->values[0] + $this->values[3] * $other->values[1];
        $matrix->values[2] = $this->values[0] * $other->values[2] + $this->values[2] * $other->values[3];
        $matrix->values[3] = $this->values[1] * $other->values[2] + $this->values[3] * $other->values[3];
        $matrix->values[4] = $this->values[0] * $other->values[4] + $this->values[2] * $other->values[5]
            + $this->values[4];
        $matrix->values[5] = $this->values[1] * $other->values[4] + $this->values[3] * $other->values[5]
            + $this->values[5];

        return $matrix;
    }

    /**
     * Apply the affine transform to a point and return the transformed
     * coordinates.
     *
     * @return array<float>
     */
    public function apply(float $x, float $y): array
    {
        return [
            $x * $this->values[0] + $y * $this->values[2] + $this->values[4],
            $x * $this->values[1] + $y * $this->values[3] + $this->values[5],
        ];
    }
}
