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
use function fill_array;
use function floor;
use function intdiv;
use function is_countable;

/**
 * Luminance source backed by an RGB pixel array.
 *
 * The decoder uses this implementation when it receives image data that has
 * already been expanded into RGB channels. It eagerly converts the pixels to a
 * grayscale luminance buffer so downstream binarizers can work without further
 * color-space conversion. Rotation is intentionally unsupported.
 *
 * @author Betaminos
 * @author dswitkin@google.com (Daniel Switkin)
 */
final class RGBLuminanceSource extends AbstractLuminanceSource
{
    public $luminances;

    private $dataWidth;

    private $dataHeight;

    /**
     * Left edge of the cropped region within the original image.
     *
     * @var int|mixed
     */
    private $left;

    /**
     * Top edge of the cropped region within the original image.
     *
     * @var int|mixed
     */
    private $top;

    /**
     * Raw pixel array supplied by the caller.
     *
     * @var null|mixed
     */
    private $pixels;

    /**
     * Create a luminance source from RGB pixels.
     *
     * When no crop rectangle is supplied, the full image becomes the active
     * decoding region. When crop coordinates are provided, the source keeps the
     * original pixel buffer and reads only the requested sub-rectangle.
     *
     * @param mixed $pixels     RGB pixel data in the legacy decoder array format.
     * @param mixed $dataWidth  Total image width.
     * @param mixed $dataHeight Total image height.
     * @param mixed $left       Optional crop origin on the x axis.
     * @param mixed $top        Optional crop origin on the y axis.
     * @param mixed $width      Optional crop width.
     * @param mixed $height     Optional crop height.
     */
    public function __construct(
        $pixels,
        $dataWidth,
        $dataHeight,
        $left = null,
        $top = null,
        $width = null,
        $height = null,
    ) {
        if (!$left && !$top && !$width && !$height) {
            $this->RGBLuminanceSource_($pixels, $dataWidth, $dataHeight);

            return;
        }
        parent::__construct($width, $height);

        if ($left + $width > $dataWidth || $top + $height > $dataHeight) {
            throw InvalidArgumentException::withMessage('Crop rectangle does not fit within image data.');
        }
        $this->luminances = $pixels;
        $this->dataWidth = $dataWidth;
        $this->dataHeight = $dataHeight;
        $this->left = $left;
        $this->top = $top;
    }

    /**
     * Rotation is not supported because the backing pixel buffer is not
     * re-sampled in place.
     */
    public function rotateCounterClockwise(): void
    {
        throw RuntimeException::withMessage('This LuminanceSource does not support rotateCounterClockwise');
    }

    /**
     * Rotation is not supported because the backing pixel buffer is not
     * re-sampled in place.
     */
    public function rotateCounterClockwise45(): void
    {
        throw RuntimeException::withMessage('This LuminanceSource does not support rotateCounterClockwise45');
    }

    /**
     * Legacy initializer retained for the original decoder port structure.
     *
     * @param mixed $width  Image width.
     * @param mixed $height Image height.
     * @param mixed $pixels RGB pixel data.
     */
    public function RGBLuminanceSource_($width, $height, $pixels): void
    {
        parent::__construct($width, $height);

        $this->dataWidth = $width;
        $this->dataHeight = $height;
        $this->left = 0;
        $this->top = 0;
        $this->pixels = $pixels;

        // In order to measure pure decoding speed, we convert the entire image to a greyscale array
        // up front, which is the same as the Y channel of the YUVLuminanceSource in the real app.
        $this->luminances = [];
        // $this->luminances = $this->grayScaleToBitmap($this->grayscale());

        foreach ($pixels as $key => $pixel) {
            $r = $pixel['red'];
            $g = $pixel['green'];
            $b = $pixel['blue'];

            /*
                 * if (($pixel & 0xFF000000) == 0) {
                 $pixel = 0xFFFFFFFF; // = white
             }

             // .229R + 0.587G + 0.114B (YUV/YIQ for PAL and NTSC)

             $this->luminances[$key] =
                 (306 * (($pixel >> 16) & 0xFF) +
                     601 * (($pixel >> 8) & 0xFF) +
                     117 * ($pixel & 0xFF) +
                     0x200) >> 10;

            */
            // $r = ($pixel >> 16) & 0xff;
            // $g = ($pixel >> 8) & 0xff;
            // $b = $pixel & 0xff;
            if ($r === $g && $g === $b) {
                // Image is already greyscale, so pick any channel.

                $this->luminances[$key] = $r; // (($r + 128) % 256) - 128;
            } else {
                // Calculate luminance cheaply, favoring green.
                $this->luminances[$key] = intdiv($r + (2 * $g) + $b, 4); // (((($r + 2 * $g + $b) / 4) + 128) % 256) - 128;
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
     * Build the full grayscale buffer for the original image.
     *
     * This helper converts every pixel in the source image to a luminance value
     * using the same fast approximation the reader expects at decode time.
     *
     * @return array<int, int|mixed>
     *
     * @psalm-return array<int, int|mixed>
     */
    public function grayscale(): array
    {
        $width = $this->dataWidth;
        $height = $this->dataHeight;

        $ret = fill_array(0, $width * $height, 0);

        for ($y = 0; $y < $height; ++$y) {
            for ($x = 0; $x < $width; ++$x) {
                $gray = $this->getPixel($x, $y, $width, $height);

                $ret[$x + $y * $width] = $gray;
            }
        }

        return $ret;
    }

    public function getPixel(int $x, int $y, $width, $height): int
    {
        $image = $this->pixels;

        if ($width < $x) {
            exit('error');
        }

        if ($height < $y) {
            exit('error');
        }
        $point = $x + ($y * $width);

        $r = $image[$point]['red']; // ($image[$point] >> 16) & 0xff;
        $g = $image[$point]['green']; // ($image[$point] >> 8) & 0xff;
        $b = $image[$point]['blue']; // $image[$point] & 0xff;

        return (int) (($r * 33 + $g * 34 + $b * 33) / 100);
    }

    /**
     * Convert grayscale samples into a thresholded black/white bitmap.
     *
     * The method first computes a per-area brightness threshold and then writes
     * either black or white into the output buffer for each pixel.
     *
     * @param array<int, int|mixed> $grayScale Grayscale samples from `grayscale()`.
     *
     * @return array<int, int|mixed>
     *
     * @psalm-return array<int, 0|255|mixed>
     */
    public function grayScaleToBitmap($grayScale): array
    {
        $middle = $this->getMiddleBrightnessPerArea($grayScale);
        $sqrtNumArea = is_countable($middle) ? count($middle) : 0;
        $areaWidth = floor($this->dataWidth / $sqrtNumArea);
        $areaHeight = floor($this->dataHeight / $sqrtNumArea);
        $bitmap = fill_array(0, $this->dataWidth * $this->dataHeight, 0);

        for ($ay = 0; $ay < $sqrtNumArea; ++$ay) {
            for ($ax = 0; $ax < $sqrtNumArea; ++$ax) {
                for ($dy = 0; $dy < $areaHeight; ++$dy) {
                    for ($dx = 0; $dx < $areaWidth; ++$dx) {
                        $bitmap[(int) ($areaWidth * $ax + $dx + ($areaHeight * $ay + $dy) * $this->dataWidth)] = ($grayScale[(int) ($areaWidth * $ax + $dx + ($areaHeight * $ay + $dy) * $this->dataWidth)] < $middle[$ax][$ay]) ? 0 : 255;
                    }
                }
            }
        }

        return $bitmap;
    }

    /**
     * @param  mixed                            $image
     * @return array<array<mixed>>&array<float>
     */
    public function getMiddleBrightnessPerArea($image): array
    {
        $numSqrtArea = 4;
        // obtain middle brightness((min + max) / 2) per area
        $areaWidth = floor($this->dataWidth / $numSqrtArea);
        $areaHeight = floor($this->dataHeight / $numSqrtArea);
        $minmax = fill_array(0, $numSqrtArea, 0);

        for ($i = 0; $i < $numSqrtArea; ++$i) {
            $minmax[$i] = fill_array(0, $numSqrtArea, 0);

            for ($i2 = 0; $i2 < $numSqrtArea; ++$i2) {
                $minmax[$i][$i2] = [0, 0];
            }
        }

        for ($ay = 0; $ay < $numSqrtArea; ++$ay) {
            for ($ax = 0; $ax < $numSqrtArea; ++$ax) {
                $minmax[$ax][$ay][0] = 0xFF;

                for ($dy = 0; $dy < $areaHeight; ++$dy) {
                    for ($dx = 0; $dx < $areaWidth; ++$dx) {
                        $target = $image[(int) ($areaWidth * $ax + $dx + ($areaHeight * $ay + $dy) * $this->dataWidth)];

                        if ($target < $minmax[$ax][$ay][0]) {
                            $minmax[$ax][$ay][0] = $target;
                        }

                        if ($target <= $minmax[$ax][$ay][1]) {
                            continue;
                        }

                        $minmax[$ax][$ay][1] = $target;
                    }
                }
                // minmax[ax][ay][0] = (minmax[ax][ay][0] + minmax[ax][ay][1]) / 2;
            }
        }
        $middle = [];

        for ($i3 = 0; $i3 < $numSqrtArea; ++$i3) {
            $middle[$i3] = [];
        }

        for ($ay = 0; $ay < $numSqrtArea; ++$ay) {
            for ($ax = 0; $ax < $numSqrtArea; ++$ax) {
                $middle[$ax][$ay] = floor(($minmax[$ax][$ay][0] + $minmax[$ax][$ay][1]) / 2);
                // Console.out.print(middle[ax][ay] + ",");
            }
            // Console.out.println("");
        }

        // Console.out.println("");

        return $middle;
    }

    public function getRow($y, $row = null)
    {
        if ($y < 0 || $y >= $this->getHeight()) {
            throw InvalidArgumentException::withMessage('Requested row is outside the image: ' + $y);
        }
        $width = $this->getWidth();

        if ($row === null || (is_countable($row) ? count($row) : 0) < $width) {
            $row = [];
        }
        $offset = ($y + $this->top) * $this->dataWidth + $this->left;

        return arraycopy($this->luminances, $offset, $row, 0, $width);
    }

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

    public function isCropSupported(): bool
    {
        return true;
    }

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
}
