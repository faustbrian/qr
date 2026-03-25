<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Decoder\Common;

use Cline\Qr\Decoder\NotFoundException;

use function count;
use function is_countable;

/**
 * Samples a QR code grid from a binarized image after perspective correction.
 *
 * The sampler is the last geometric step before decoding. It takes the detected
 * finder-pattern geometry and maps the source image back into a square module
 * matrix. The implementation is intentionally pluggable because sampling can be
 * optimized differently depending on the runtime and image backend, but the
 * contract is the same: given a valid transform, return a matrix of sampled bits
 * or fail when the requested region cannot be mapped safely inside the image.
 *
 * @author Sean Owen
 */
abstract class AbstractGridSampler
{
    /**
     * Cached process-wide sampler implementation.
     *
     * The library keeps one sampler instance because the sampling strategy is a
     * platform concern rather than request-scoped state. Callers may replace it
     * once during bootstrap to install a more efficient implementation.
     *
     * @var null|self
     */
    private static $gridSampler;

    /**
     * Installs the sampler implementation used for all subsequent decode calls.
     *
     * This is a global hook, not a per-request dependency. That mirrors the decoder
     * design and keeps the geometric sampling strategy consistent for the lifetime
     * of the process.
     *
     * @param self $newGridSampler platform-specific sampler to install
     */
    public static function setGridSampler($newGridSampler): void
    {
        self::$gridSampler = $newGridSampler;
    }

    /**
     * Returns the active sampler implementation.
     *
     * Falls back to the default implementation the first time it is called so the
     * decoder can operate without explicit bootstrap configuration.
     *
     * @return self active sampler implementation
     */
    public static function getInstance()
    {
        if (!self::$gridSampler) {
            self::$gridSampler = new DefaultGridSampler();
        }

        return self::$gridSampler;
    }

    /**
     * Samples a rectangular QR module grid from the source image.
     *
     * The coordinate arguments describe the quadrilateral in the source image and
     * the matching quadrilateral in the destination grid. Implementations are
     * expected to apply the transform, validate the resulting points, and return a
     * matrix whose dimensions match the requested output size.
     *
     * @param BitMatrix $image      source image to sample
     * @param int       $dimensionX output width in modules
     * @param int       $dimensionY output height in modules
     * @param float     $p1ToX      destination point 1 x-coordinate
     * @param float     $p1ToY      destination point 1 y-coordinate
     * @param float     $p2ToX      destination point 2 x-coordinate
     * @param float     $p2ToY      destination point 2 y-coordinate
     * @param float     $p3ToX      destination point 3 x-coordinate
     * @param float     $p3ToY      destination point 3 y-coordinate
     * @param float     $p4ToX      destination point 4 x-coordinate
     * @param float     $p4ToY      destination point 4 y-coordinate
     * @param float     $p1FromX    source point 1 x-coordinate
     * @param float     $p1FromY    source point 1 y-coordinate
     * @param float     $p2FromX    source point 2 x-coordinate
     * @param float     $p2FromY    source point 2 y-coordinate
     * @param float     $p3FromX    source point 3 x-coordinate
     * @param float     $p3FromY    source point 3 y-coordinate
     * @param float     $p4FromX    source point 4 x-coordinate
     * @param float     $p4FromY    source point 4 y-coordinate
     *
     * @throws NotFoundException when the image cannot be safely sampled
     * @return BitMatrix         sampled module matrix
     */
    abstract public function sampleGrid(
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
    );

    abstract public function sampleGrid_(
        BitMatrix $image,
        int $dimensionX,
        int $dimensionY,
        PerspectiveTransform $transform,
    ): BitMatrix;

    /**
     * Validates that sampled points lie on or just inside the source image.
     *
     * The sampler tolerates coordinates that drift one pixel outside the image
     * bounds and nudges them back in. That preserves decode robustness when the
     * finder-pattern geometry is slightly off or the code touches the border of
     * the source image. Anything farther out is treated as an unrecoverable
     * detection failure.
     *
     * @param BitMatrix $image  source image used for bounds checking
     * @param array     $points sampled points in x1,y1,...,xn,yn order
     *
     * @psalm-param array<int, float> $points
     * @throws NotFoundException when a point lies outside the recoverable range
     */
    protected static function checkAndNudgePoints(
        BitMatrix $image,
        array $points,
    ): void {
        $width = $image->getWidth();
        $height = $image->getHeight();
        // Check and nudge points from start until we see some that are OK:
        $nudged = true;

        for ($offset = 0; $offset < (is_countable($points) ? count($points) : 0) && $nudged; $offset += 2) {
            $x = (int) $points[$offset];
            $y = (int) $points[$offset + 1];

            if ($x < -1 || $x > $width || $y < -1 || $y > $height) {
                throw NotFoundException::getNotFoundInstance("Endpoint ({$x}, {$y}) lies outside the image boundaries ({$width}, {$height})");
            }
            $nudged = false;

            if ($x === -1) {
                $points[$offset] = 0.0;
                $nudged = true;
            } elseif ($x === $width) {
                $points[$offset] = $width - 1;
                $nudged = true;
            }

            if ($y === -1) {
                $points[$offset + 1] = 0.0;
                $nudged = true;
            } elseif ($y === $height) {
                $points[$offset + 1] = $height - 1;
                $nudged = true;
            }
        }
        // Check and nudge points from end:
        $nudged = true;

        for ($offset = (is_countable($points) ? count($points) : 0) - 2; $offset >= 0 && $nudged; $offset -= 2) {
            $x = (int) $points[$offset];
            $y = (int) $points[$offset + 1];

            if ($x < -1 || $x > $width || $y < -1 || $y > $height) {
                throw NotFoundException::getNotFoundInstance("Endpoint ({$x}, {$y}) lies outside the image boundaries ({$width}, {$height})");
            }
            $nudged = false;

            if ($x === -1) {
                $points[$offset] = 0.0;
                $nudged = true;
            } elseif ($x === $width) {
                $points[$offset] = $width - 1;
                $nudged = true;
            }

            if ($y === -1) {
                $points[$offset + 1] = 0.0;
                $nudged = true;
            } elseif ($y === $height) {
                $points[$offset + 1] = $height - 1;
                $nudged = true;
            }
        }
    }
}
