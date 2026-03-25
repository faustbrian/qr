<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Decoder\Common;

use Cline\Qr\Decoder\NotFoundException;
use Exception;

use function count;
use function fill_array;
use function is_countable;

/**
 * Default perspective sampler used by the QR detector.
 *
 * The sampler converts a quadrilateral in the source image back into a
 * normalized square bit matrix. Its job is to preserve the same geometric
 * behavior as the decoder while keeping all validation in one place, so callers only
 * need to provide the source image and the corner coordinates.
 *
 * @author Sean Owen
 */
final class DefaultGridSampler extends AbstractGridSampler
{
    /**
     * Sample a quadrilateral region from the source image.
     *
     * The source and destination coordinates describe the transformation from
     * the detected QR code corners into the normalized grid that downstream
     * decoders expect.
     * @param mixed $image
     * @param mixed $dimensionX
     * @param mixed $dimensionY
     * @param mixed $p1ToX
     * @param mixed $p1ToY
     * @param mixed $p2ToX
     * @param mixed $p2ToY
     * @param mixed $p3ToX
     * @param mixed $p3ToY
     * @param mixed $p4ToX
     * @param mixed $p4ToY
     * @param mixed $p1FromX
     * @param mixed $p1FromY
     * @param mixed $p2FromX
     * @param mixed $p2FromY
     * @param mixed $p3FromX
     * @param mixed $p3FromY
     * @param mixed $p4FromX
     * @param mixed $p4FromY
     */
    public function sampleGrid(
        $image,
        $dimensionX,
        $dimensionY,
        $p1ToX,
        $p1ToY,
        $p2ToX,
        $p2ToY,
        $p3ToX,
        $p3ToY,
        $p4ToX,
        $p4ToY,
        $p1FromX,
        $p1FromY,
        $p2FromX,
        $p2FromY,
        $p3FromX,
        $p3FromY,
        $p4FromX,
        $p4FromY,
    ) {
        $transform = PerspectiveTransform::quadrilateralToQuadrilateral(
            $p1ToX,
            $p1ToY,
            $p2ToX,
            $p2ToY,
            $p3ToX,
            $p3ToY,
            $p4ToX,
            $p4ToY,
            $p1FromX,
            $p1FromY,
            $p2FromX,
            $p2FromY,
            $p3FromX,
            $p3FromY,
            $p4FromX,
            $p4FromY,
        );

        return $this->sampleGrid_($image, $dimensionX, $dimensionY, $transform);
    }

    /**
     * Sample a normalized grid using a precomputed perspective transform.
     *
     * The helper performs the actual point mapping and bounds validation before
     * copying bits into the output matrix.
     */
    public function sampleGrid_(
        BitMatrix $image,
        int $dimensionX,
        int $dimensionY,
        PerspectiveTransform $transform,
    ): BitMatrix {
        if ($dimensionX <= 0 || $dimensionY <= 0) {
            throw NotFoundException::getNotFoundInstance('X or Y dimensions smaller than zero');
        }
        $bits = new BitMatrix($dimensionX, $dimensionY);
        $points = fill_array(0, 2 * $dimensionX, 0.0);

        for ($y = 0; $y < $dimensionY; ++$y) {
            $max = is_countable($points) ? count($points) : 0;
            $iValue = (float) $y + 0.5;

            for ($x = 0; $x < $max; $x += 2) {
                $points[$x] = (float) ($x / 2) + 0.5;
                $points[$x + 1] = $iValue;
            }
            $transform->transformPoints($points);
            // Quick check to see if points transformed to something inside the image;
            // sufficient to check the endpoints
            self::checkAndNudgePoints($image, $points);

            try {
                for ($x = 0; $x < $max; $x += 2) {
                    if (!$image->get((int) $points[$x], (int) $points[$x + 1])) {
                        continue;
                    }

                    // Black(-ish) pixel
                    $bits->set($x / 2, $y);
                }
            } catch (Exception) { // ArrayIndexOutOfBoundsException
                // This feels wrong, but, sometimes if the finder patterns are misidentified, the resulting
                // transform gets "twisted" such that it maps a straight line of points to a set of points
                // whose endpoints are in bounds, but others are not. There is probably some mathematical
                // way to detect this about the transformation that I don't know yet.
                // This results in an ugly runtime exception despite our clever checks above -- can't have
                // that. We could check each point's coordinates but that feels duplicative. We settle for
                // catching and wrapping ArrayIndexOutOfBoundsException.
                throw NotFoundException::getNotFoundInstance('ArrayIndexOutOfBoundsException');
            }
        }

        return $bits;
    }
}
