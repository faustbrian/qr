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
use function is_countable;
use function round;

/**
 * Luminance source backed by planar camera YUV data.
 *
 * The decoder uses this form when reading preview frames from a camera driver.
 * It can crop to the active scan window and optionally reverse the row order
 * horizontally so the scan result matches the expected orientation.
 *
 * Only the Y channel is used; the chroma planes are ignored.
 *
 * @author dswitkin@google.com (Daniel Switkin)
 */
final class PlanarYUVLuminanceSource extends AbstractLuminanceSource
{
    private static int $THUMBNAIL_SCALE_FACTOR = 2;

    /** @var int */
    private $dataWidth;

    /** @var int */
    private $dataHeight;

    /** @var int */
    private $left;

    /** @var int */
    private $top;

    /**
     * Build a luminance source from a planar YUV frame.
     *
     * @param array<int, int> $yuvData           Raw camera frame data.
     * @param int             $dataWidth         Full frame width.
     * @param int             $dataHeight        Full frame height.
     * @param int             $left              Crop left offset.
     * @param int             $top               Crop top offset.
     * @param int             $width             Crop width.
     * @param int             $height            Crop height.
     * @param bool            $reverseHorizontal Whether to mirror the crop horizontally.
     */
    public function __construct(
        private $yuvData,
        $dataWidth,
        $dataHeight,
        $left,
        $top,
        $width,
        $height,
        $reverseHorizontal,
    ) {
        parent::__construct($width, $height);

        if ($left + $width > $dataWidth || $top + $height > $dataHeight) {
            throw InvalidArgumentException::withMessage('Crop rectangle does not fit within image data.');
        }
        $this->dataWidth = $dataWidth;
        $this->dataHeight = $dataHeight;
        $this->left = $left;
        $this->top = $top;

        if (!$reverseHorizontal) {
            return;
        }

        $this->reverseHorizontal($width, $height);
    }

    /**
     * This source cannot be rotated in place.
     *
     * @throws RuntimeException Always thrown.
     */
    public function rotateCounterClockwise(): void
    {
        throw RuntimeException::withMessage('This LuminanceSource does not support rotateCounterClockwise');
    }

    /**
     * This source cannot be rotated in place.
     *
     * @throws RuntimeException Always thrown.
     */
    public function rotateCounterClockwise45(): void
    {
        throw RuntimeException::withMessage('This LuminanceSource does not support rotateCounterClockwise45');
    }

    /**
     * Fetch one row from the current crop window.
     *
     * @param  int                  $y   Row index within the current crop.
     * @param  null|array<int, int> $row Optional reusable destination buffer.
     * @return array<int, int>      The requested luminance row.
     */
    public function getRow($y, $row = null)
    {
        if ($y < 0 || $y >= $this->getHeight()) {
            throw InvalidArgumentException::withMessage('Requested row is outside the image: ' + $y);
        }
        $width = $this->getWidth();

        if ($row === null || (is_countable($row) ? count($row) : 0) < $width) {
            $row = []; // new byte[width];
        }
        $offset = ($y + $this->top) * $this->dataWidth + $this->left;

        return arraycopy($this->yuvData, $offset, $row, 0, $width);
    }

    /**
     * Return the cropped luminance matrix for the current view.
     *
     * The data is returned in row-major order and may be the underlying frame
     * data itself when the crop covers the full image.
     *
     * @return array<int, int> Row-major luminance values.
     */
    public function getMatrix()
    {
        $width = $this->getWidth();
        $height = $this->getHeight();

        // If the caller asks for the entire underlying image, save the copy and give them the
        // original data. The docs specifically warn that result.length must be ignored.
        if ($width === $this->dataWidth && $height === $this->dataHeight) {
            return $this->yuvData;
        }

        $area = $width * $height;
        $matrix = []; // new byte[area];
        $inputOffset = $this->top * $this->dataWidth + $this->left;

        // If the width matches the full width of the underlying data, perform a single copy.
        if ($width === $this->dataWidth) {
            return arraycopy($this->yuvData, $inputOffset, $matrix, 0, $area);
        }

        // Otherwise copy one cropped row at a time.
        $yuv = $this->yuvData;

        for ($y = 0; $y < $height; ++$y) {
            $outputOffset = $y * $width;
            $matrix = arraycopy($this->yuvData, $inputOffset, $matrix, $outputOffset, $width);
            $inputOffset += $this->dataWidth;
        }

        return $matrix;
    }

    /**
     * This source supports cropping because it tracks its frame offsets.
     */
    public function isCropSupported(): bool
    {
        return true;
    }

    /**
     * Return a new source view cropped from the same YUV frame.
     *
     * @param  int  $left   Crop offset from the current view.
     * @param  int  $top    Crop offset from the current view.
     * @param  int  $width  Crop width.
     * @param  int  $height Crop height.
     * @return self A cropped view.
     */
    public function crop($left, $top, $width, $height): self
    {
        return new self(
            $this->yuvData,
            $this->dataWidth,
            $this->dataHeight,
            $this->left + $left,
            $this->top + $top,
            $width,
            $height,
            false,
        );
    }

    /**
     * Render a low-resolution thumbnail of the cropped region.
     *
     * @return array<int, int> Packed ARGB pixels.
     */
    public function renderThumbnail(): array
    {
        $width = (int) ($this->getWidth() / self::$THUMBNAIL_SCALE_FACTOR);
        $height = (int) ($this->getHeight() / self::$THUMBNAIL_SCALE_FACTOR);
        $pixels = []; // new int[width * height];
        $yuv = $this->yuvData;
        $inputOffset = $this->top * $this->dataWidth + $this->left;

        for ($y = 0; $y < $height; ++$y) {
            $outputOffset = $y * $width;

            for ($x = 0; $x < $width; ++$x) {
                $grey = $yuv[$inputOffset + $x * self::$THUMBNAIL_SCALE_FACTOR] & 0xFF;
                $pixels[$outputOffset + $x] = 0xFF_00_00_00 | ($grey * 0x00_01_01_01);
            }
            $inputOffset += $this->dataWidth * self::$THUMBNAIL_SCALE_FACTOR;
        }

        return $pixels;
    }

    /**
     * Return the thumbnail width for `renderThumbnail()`.
     */
    /*
  public int getThumbnailWidth() {
    return getWidth() / THUMBNAIL_SCALE_FACTOR;
  }
  */

    /**
     * Return the thumbnail height for `renderThumbnail()`.
     */
    /**
     * public function getThumbnailHeight(): int
    }
     */

    /**
     * Reverse the current crop horizontally in place.
     *
     * @param int $width  Crop width.
     * @param int $height Crop height.
     */
    private function reverseHorizontal(int $width, int $height): void
    {
        $yuvData = $this->yuvData;

        for ($y = 0, $rowStart = $this->top * $this->dataWidth + $this->left; $y < $height; $y++, $rowStart += $this->dataWidth) {
            $middle = (int) round($rowStart + $width / 2);

            for ($x1 = $rowStart, $x2 = $rowStart + $width - 1; $x1 < $middle; $x1++, $x2--) {
                $temp = $yuvData[$x1];
                $yuvData[$x1] = $yuvData[$x2];
                $yuvData[$x2] = $temp;
            }
        }
    }
}
