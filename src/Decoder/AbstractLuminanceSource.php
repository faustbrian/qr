<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Decoder;

use Cline\Qr\Decoder\Common\BitMatrix;

/**
 * Abstract base for QR decoder luminance sources.
 *
 * Implementations hide platform-specific bitmap representations behind a
 * consistent grayscale API. Consumers read rows or full matrices without
 * mutating the underlying image, and optional crop/rotation methods are used
 * to create derived views when a concrete source can support them.
 *
 * @author dswitkin@google.com (Daniel Switkin)
 */
abstract class AbstractLuminanceSource
{
    public function __construct(
        private readonly int $width,
        private readonly int $height,
    ) {}

    /**
     * @return int The pixel width of the current view.
     */
    final public function getWidth(): int
    {
        return $this->width;
    }

    /**
     * @return int The pixel height of the current view.
     */
    final public function getHeight(): int
    {
        return $this->height;
    }

    /**
     * @return bool Whether the concrete source can create cropped views.
     */
    public function isCropSupported(): bool
    {
        return false;
    }

    /**
     * @return bool Whether the concrete source can rotate counter-clockwise.
     */
    public function isRotateSupported(): bool
    {
        return false;
    }

    final public function toString(): string
    {
        $row = [];
        $result = '';

        for ($y = 0; $y < $this->height; ++$y) {
            $row = $this->getRow($y, $row);

            for ($x = 0; $x < $this->width; ++$x) {
                $luminance = $row[$x] & 0xFF;
                $c = '';

                if ($luminance < 0x40) {
                    $c = '#';
                } elseif ($luminance < 0x80) {
                    $c = '+';
                } elseif ($luminance < 0xC0) {
                    $c = '.';
                } else {
                    $c = ' ';
                }
                $result .= $c;
            }
            $result .= '\n';
        }

        return $result;
    }

    /**
     * Fetch the full luminance matrix for the current view.
     *
     * Values are returned in row-major order and should be treated as
     * read-only by callers.
     *
     * @return array<int, int> Row-major luminance values.
     */
    abstract public function getMatrix();

    /**
     * Return a cropped view of the current luminance source.
     *
     * Implementations may keep a reference to the original data rather than
     * copying it. Callers should only use this when `isCropSupported()` is true.
     *
     * @param  int  $left   Left coordinate within the current view.
     * @param  int  $top    Top coordinate within the current view.
     * @param  int  $width  Crop width.
     * @param  int  $height Crop height.
     * @return self A cropped view.
     */
    abstract public function crop($left, $top, $width, $height): self;

    /**
     * Inversion is not implemented in this package.
     *
     * The original decoder API exposes an inverted wrapper, but this port keeps
     * the hook commented out because the decoder never relies on it directly.
     */
    // public function invert()
    // {
    // 	return new InvertedLuminanceSource($this);
    // }

    /**
     * Return a 90-degree counter-clockwise rotated view.
     *
     * Only callable when `isRotateSupported()` returns true.
     */
    abstract public function rotateCounterClockwise(): void;

    /**
     * Return a 45-degree counter-clockwise rotated view.
     *
     * Only callable when `isRotateSupported()` returns true.
     */
    abstract public function rotateCounterClockwise45(): void;

    /**
     * Fetch one row of luminance data from the current view.
     *
     * Values range from 0 (black) to 255 (white). Implementations should prefer
     * row-wise access over decoding the full matrix when possible, because many
     * callers only need the active scan line.
     *
     * @param  int             $y   Row index within the current view.
     * @param  array<int, int> $row Optional reusable destination buffer.
     * @return array<int, int> The requested luminance row.
     */
    abstract public function getRow(int $y, array $row);
}
