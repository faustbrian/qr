<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Decoder;

use function arraycopy;
use function count;
use function imagecolorat;
use function imagecolorsforindex;
use function is_countable;

/**
 * Luminance source backed by a GD image resource.
 *
 * This adapter converts a GD image into the grayscale buffer expected by the
 * decoder. It supports cropping, but not rotation, because rotation would need
 * a new image buffer and would change the cost profile of the decode path.
 * @author Brian Faust <brian@cline.sh>
 */
final class GDLuminanceSource extends AbstractLuminanceSource
{
    /**
     * Grayscale luminance samples for the full source image or current crop.
     *
     * @var array<int, float|int>
     */
    public $luminances;

    /**
     * Width of the original image buffer.
     *
     * @var int
     */
    private $dataWidth;

    /**
     * Height of the original image buffer.
     *
     * @var int
     */
    private $dataHeight;

    /**
     * Left offset of the active crop.
     *
     * @var int
     */
    private $left;

    /**
     * Top offset of the active crop.
     *
     * @var int
     */
    private $top;

    /**
     * Original GD image instance used to populate the luminance buffer.
     *
     * @var null|mixed
     */
    private $gdImage;

    /**
     * Creates a luminance source from a GD image and optional crop rectangle.
     *
     * Passing no crop coordinates produces a source covering the full image.
     * When crop coordinates are supplied, they must fit entirely inside the
     * original image bounds.
     * @param mixed      $gdImage
     * @param mixed      $dataWidth
     * @param mixed      $dataHeight
     * @param null|mixed $left
     * @param null|mixed $top
     * @param null|mixed $width
     * @param null|mixed $height
     */
    public function __construct(
        $gdImage,
        $dataWidth,
        $dataHeight,
        $left = null,
        $top = null,
        $width = null,
        $height = null,
    ) {
        if (!$left && !$top && !$width && !$height) {
            $this->GDLuminanceSource($gdImage, $dataWidth, $dataHeight);

            return;
        }
        parent::__construct($width, $height);

        if ($left + $width > $dataWidth || $top + $height > $dataHeight) {
            throw InvalidArgumentException::withMessage('Crop rectangle does not fit within image data.');
        }
        $this->luminances = $gdImage;
        $this->dataWidth = $dataWidth;
        $this->dataHeight = $dataHeight;
        $this->left = $left;
        $this->top = $top;
    }

    /**
     * Builds the full-image luminance buffer from the GD source.
     *
     * This eager conversion keeps later decode operations fast because the
     * grayscale samples are already materialized when the detector asks for rows
     * or the full matrix.
     * @param mixed $gdImage
     * @param mixed $width
     * @param mixed $height
     */
    public function GDLuminanceSource($gdImage, $width, $height): void
    {
        parent::__construct($width, $height);

        $this->dataWidth = $width;
        $this->dataHeight = $height;
        $this->left = 0;
        $this->top = 0;
        $this->gdImage = $gdImage;

        // In order to measure pure decoding speed, we convert the entire image to a greyscale array
        // up front, which is the same as the Y channel of the YUVLuminanceSource in the real app.
        $this->luminances = [];
        // $this->luminances = $this->grayScaleToBitmap($this->grayscale());

        $array = [];
        $rgb = [];

        for ($j = 0; $j < $height; ++$j) {
            for ($i = 0; $i < $width; ++$i) {
                $argb = imagecolorat($this->gdImage, $i, $j);
                $pixel = imagecolorsforindex($this->gdImage, $argb);
                $r = $pixel['red'];
                $g = $pixel['green'];
                $b = $pixel['blue'];

                if ($r === $g && $g === $b) {
                    // Image is already greyscale, so pick any channel.

                    $this->luminances[] = $r; // (($r + 128) % 256) - 128;
                } else {
                    // Calculate luminance cheaply, favoring green.
                    $this->luminances[] = ($r + 2 * $g + $b) / 4; // (((($r + 2 * $g + $b) / 4) + 128) % 256) - 128;
                }
            }
        }

        /*
        for ($y = 0; $y < $height; $y++) {
            $offset = $y * $width;
            for ($x = 0; $x < $width; $x++) {
                $pixel = $pixels[$offset + $x];
                $r = ($pixel >> 16) & 0xff;
                $g = ($pixel >> 8) & 0xff;
                $b = $pixel & 0xff;
                if ($r == $g && $g == $b) {
// Image is already greyscale, so pick any channel.

                    $this->luminances[(int)($offset + $x)] = (($r+128) % 256) - 128;
                } else {
// Calculate luminance cheaply, favoring green.
                    $this->luminances[(int)($offset + $x)] =  (((($r + 2 * $g + $b) / 4)+128)%256) - 128;
                }



            }
        */
        // }
        //   $this->luminances = $this->grayScaleToBitmap($this->luminances);
    }

    /**
     * Returns a single row of luminance samples.
     *
     * @param mixed      $y
     * @param null|mixed $row reusable output buffer
     *
     * @return array<int, float|int> luminance row for the requested y coordinate
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
     * Returns the current crop as a flat luminance matrix.
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
     * Indicates that this source can produce cropped views without re-decoding.
     */
    public function isCropSupported(): bool
    {
        return true;
    }

    /**
     * Creates a cropped luminance source that shares the same backing samples.
     * @param mixed $left
     * @param mixed $top
     * @param mixed $width
     * @param mixed $height
     */
    public function crop($left, $top, $width, $height): self
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
     * Rotation is unsupported because it would require a new pixel buffer.
     */
    public function rotateCounterClockwise(): void
    {
        throw RuntimeException::withMessage('This LuminanceSource does not support rotateCounterClockwise');
    }

    /**
     * Rotation is unsupported because it would require a new pixel buffer.
     */
    public function rotateCounterClockwise45(): void
    {
        throw RuntimeException::withMessage('This LuminanceSource does not support rotateCounterClockwise45');
    }
}
