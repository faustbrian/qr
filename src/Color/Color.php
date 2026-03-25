<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Color;

use function sprintf;

/**
 * Immutable RGBA color value used throughout rendering.
 *
 * The class models the package's canonical color representation and is shared
 * by QR codes, labels, logos, and writer backends. Channel values are stored as
 * normalized integers so downstream renderers can convert them without any
 * additional validation or coercion.
 *
 * Alpha follows the GD convention of `0` for fully opaque and `127` for fully
 * transparent. `getOpacity()` exposes the inverse, human-friendly value because
 * several renderers and writers reason about opacity rather than alpha.
 *
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class Color implements ColorInterface
{
    public function __construct(
        /** @var int<0, 255> */
        private int $red,
        /** @var int<0, 255> */
        private int $green,
        /** @var int<0, 255> */
        private int $blue,
        /** @var int<0, 127> */
        private int $alpha = 0,
    ) {}

    public function getRed(): int
    {
        return $this->red;
    }

    /**
     * @param int<0, 255> $red
     */
    public function withRed(int $red): self
    {
        return new self(
            red: $red,
            green: $this->green,
            blue: $this->blue,
            alpha: $this->alpha,
        );
    }

    public function getGreen(): int
    {
        return $this->green;
    }

    /**
     * @param int<0, 255> $green
     */
    public function withGreen(int $green): self
    {
        return new self(
            red: $this->red,
            green: $green,
            blue: $this->blue,
            alpha: $this->alpha,
        );
    }

    public function getBlue(): int
    {
        return $this->blue;
    }

    /**
     * @param int<0, 255> $blue
     */
    public function withBlue(int $blue): self
    {
        return new self(
            red: $this->red,
            green: $this->green,
            blue: $blue,
            alpha: $this->alpha,
        );
    }

    public function getAlpha(): int
    {
        return $this->alpha;
    }

    /**
     * @param int<0, 127> $alpha
     */
    public function withAlpha(int $alpha): self
    {
        return new self(
            red: $this->red,
            green: $this->green,
            blue: $this->blue,
            alpha: $alpha,
        );
    }

    /**
     * Convert the internal GD alpha scale into a normalized opacity fraction.
     *
     * The result is `1.0` for a fully opaque color and approaches `0.0` as the
     * alpha channel increases toward transparency.
     */
    public function getOpacity(): float
    {
        return 1 - $this->alpha / 127;
    }

    /**
     * Return the color as a CSS-style hexadecimal string.
     *
     * The alpha channel is intentionally omitted because most callers use this
     * representation for display or debugging, where RGB is the useful portion.
     */
    public function getHex(): string
    {
        return sprintf('#%02x%02x%02x', $this->red, $this->green, $this->blue);
    }

    /**
     * Export the normalized channel values as a simple associative array.
     *
     * This is primarily used when colors need to be serialized, logged, or
     * compared without depending on the concrete object type.
     *
     * @return array{red:int, green:int, blue:int, alpha:int}
     */
    public function toArray(): array
    {
        return [
            'red' => $this->red,
            'green' => $this->green,
            'blue' => $this->blue,
            'alpha' => $this->alpha,
        ];
    }
}
