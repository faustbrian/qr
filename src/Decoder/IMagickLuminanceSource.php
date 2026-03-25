<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Decoder;

use Imagick;

use function arraycopy;
use function count;
use function ini_get;
use function is_countable;
use function mb_strlen;
use function mb_strtolower;
use function mb_substr;
use function mb_trim;

/**
 * Imagick-backed luminance source used by the QR decoder.
 *
 * The source eagerly converts the full image into a grayscale luminance buffer
 * so row and matrix reads stay cheap during decode. Cropping keeps a reference
 * to the original raster and adjusts offsets instead of re-reading pixels.
 * Rotation is intentionally unsupported because the decoder expects the source
 * orientation to remain stable while it scans the matrix.
 * @author Brian Faust <brian@cline.sh>
 */
final class IMagickLuminanceSource extends AbstractLuminanceSource
{
    /** @var int */
    private $dataWidth;

    /** @var int */
    private $dataHeight;

    /**
     * Left offset of the current crop within the original image.
     *
     * @var int
     */
    private $left;

    /**
     * Top offset of the current crop within the original image.
     *
     * @var int
     */
    private $top;

    private ?Imagick $image = null;

    /**
     * Build a luminance source from an Imagick image.
     *
     * When no crop rectangle is supplied, the constructor precomputes a full
     * grayscale buffer for the entire image. When crop parameters are present,
     * the instance represents a view into the original image data.
     *
     * @param int      $dataWidth  Width of the full image data.
     * @param int      $dataHeight Height of the full image data.
     * @param null|int $left       Optional crop left offset.
     * @param null|int $top        Optional crop top offset.
     * @param null|int $width      Optional crop width.
     * @param null|int $height     Optional crop height.
     */
    public function __construct(
        /**
         * Cached grayscale luminance data for the current crop window, or the
         * original Imagick image while the full source is being initialized.
         *
         * @var array<int, int>|Imagick
         */
        public array|Imagick $luminances,
        $dataWidth,
        $dataHeight,
        $left = null,
        $top = null,
        $width = null,
        $height = null,
    ) {
        if (!$left && !$top && !$width && !$height) {
            if (!$luminances instanceof Imagick) {
                throw InvalidArgumentException::withMessage('Full-image initialization requires an Imagick instance.');
            }

            $this->_IMagickLuminanceSource($luminances, $dataWidth, $dataHeight);

            return;
        }
        parent::__construct($width, $height);

        if ($left + $width > $dataWidth || $top + $height > $dataHeight) {
            throw InvalidArgumentException::withMessage('Crop rectangle does not fit within image data.');
        }

        $this->dataWidth = $dataWidth;
        $this->dataHeight = $dataHeight;
        $this->left = $left;
        $this->top = $top;
    }

    /**
     * This source cannot be rotated because the decoder reads it in place.
     *
     * @throws RuntimeException Always thrown.
     */
    public function rotateCounterClockwise(): void
    {
        throw RuntimeException::withMessage('This LuminanceSource does not support rotateCounterClockwise');
    }

    /**
     * This source cannot be rotated because the decoder reads it in place.
     *
     * @throws RuntimeException Always thrown.
     */
    public function rotateCounterClockwise45(): void
    {
        throw RuntimeException::withMessage('This LuminanceSource does not support rotateCounterClockwise45');
    }

    /**
     * Initialize a full-image source and export the luminance buffer.
     *
     * This path is optimized for decode throughput: the grayscale data is
     * exported once up front instead of being recomputed for each row access.
     *
     * @param Imagick $image  Source image.
     * @param int     $width  Image width.
     * @param int     $height Image height.
     */
    public function _IMagickLuminanceSource(Imagick $image, $width, $height): void
    {
        parent::__construct($width, $height);

        $this->dataWidth = $width;
        $this->dataHeight = $height;
        $this->left = 0;
        $this->top = 0;
        $this->image = $image;

        // In order to measure pure decoding speed, we convert the entire image to a greyscale array
        // up front, which is the same as the Y channel of the YUVLuminanceSource in the real app.
        $this->luminances = [];

        $image->setImageColorspace(Imagick::COLORSPACE_GRAY);

        // Check that we actually have enough space to do it
        if (ini_get('memory_limit') !== -1 && $width * $height * 16 * 3 > $this->kmgStringToBytes(ini_get('memory_limit'))) {
            throw RuntimeException::withMessage('PHP Memory Limit does not allow pixel export.');
        }
        $pixels = $image->exportImagePixels(1, 1, $width, $height, 'RGB', Imagick::PIXEL_CHAR);

        $array = [];
        $rgb = [];

        $countPixels = count($pixels);

        for ($i = 0; $i < $countPixels; $i += 3) {
            $r = $pixels[$i] & 0xFF;
            $g = $pixels[$i + 1] & 0xFF;
            $b = $pixels[$i + 2] & 0xFF;

            if ($r === $g && $g === $b) {
                // Image is already greyscale, so pick any channel.
                $this->luminances[] = $r; // (($r + 128) % 256) - 128;
            } else {
                // Calculate luminance cheaply, favoring green.
                $this->luminances[] = ($r + 2 * $g + $b) / 4; // (((($r + 2 * $g + $b) / 4) + 128) % 256) - 128;
            }
        }
    }

    /**
     * Fetch a single row from the current crop window.
     *
     * @param int                  $y   Row index within the current crop.
     * @param null|array<int, int> $row Optional reusable destination buffer.
     *
     * @throws InvalidArgumentException When the requested row falls outside the image.
     * @return array<int, int>          The requested luminance row.
     */
    public function getRow($y, $row = null)
    {
        if ($y < 0 || $y >= $this->getHeight()) {
            throw InvalidArgumentException::withMessage('Requested row is outside the image: '.$y);
        }
        $width = $this->getWidth();

        if ($row === null || (is_countable($row) ? count($row) : 0) < $width) {
            $row = [];
        }
        $offset = ($y + $this->top) * $this->dataWidth + $this->left;

        return arraycopy($this->luminances, $offset, $row, 0, $width);
    }

    /**
     * Return the current luminance matrix.
     *
     * If the caller requests the full underlying image, the original buffer is
     * returned directly. Otherwise a cropped copy is assembled row by row.
     *
     * @return array<int, int> Row-major luminance values for the current view.
     */
    public function getMatrix()
    {
        $width = $this->getWidth();
        $height = $this->getHeight();

        // If the caller asks for the entire underlying image, save the copy and give them the
        // original data. The docs specifically warn that result.length must be ignored.
        if ($width === $this->dataWidth && $height === $this->dataHeight) {
            return $this->luminances;
        }

        $area = $width * $height;
        $matrix = [];
        $inputOffset = $this->top * $this->dataWidth + $this->left;

        // If the width matches the full width of the underlying data, perform a single copy.
        if ($width === $this->dataWidth) {
            return arraycopy($this->luminances, $inputOffset, $matrix, 0, $area);
        }

        // Otherwise copy one cropped row at a time.
        $rgb = $this->luminances;

        for ($y = 0; $y < $height; ++$y) {
            $outputOffset = $y * $width;
            $matrix = arraycopy($rgb, $inputOffset, $matrix, $outputOffset, $width);
            $inputOffset += $this->dataWidth;
        }

        return $matrix;
    }

    /**
     * This source supports cropping because it keeps track of image offsets.
     */
    public function isCropSupported(): bool
    {
        return true;
    }

    /**
     * Create a cropped view into the same Imagick-backed image data.
     *
     * @param  int                     $left   Crop offset from the current view's left edge.
     * @param  int                     $top    Crop offset from the current view's top edge.
     * @param  int                     $width  Crop width.
     * @param  int                     $height Crop height.
     * @return AbstractLuminanceSource A new cropped source.
     */
    public function crop($left, $top, $width, $height): AbstractLuminanceSource
    {
        return new self(
            $this->luminances,
            $this->dataWidth,
            $this->dataHeight,
            $this->left + $left,
            $this->top + $top,
            $width,
            $height,
        );
    }

    /**
     * Convert shorthand PHP memory-limit notation to bytes.
     *
     * This mirrors the familiar `128M` / `2G` parsing used by `ini_get()`.
     *
     * @param  string $val Memory size shorthand notation string.
     * @return int    Bytes represented by the shorthand value.
     */
    protected static function kmgStringToBytes(string $val)
    {
        $val = mb_trim($val);
        $last = mb_strtolower($val[mb_strlen($val) - 1]);
        $val = mb_substr($val, 0, -1);

        switch ($last) {
            // The 'G' modifier is available since PHP 5.1.0
            case 'g':
                $val *= 1_024;

                // no break
            case 'm':
                $val *= 1_024;

                // no break
            case 'k':
                $val *= 1_024;
        }

        return $val;
    }
}
