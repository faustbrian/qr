<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Label\Font;

/**
 * Convenience font value object for the bundled Open Sans asset.
 *
 * This keeps callers from hardcoding the package asset path when they want the
 * default label typeface but a non-default point size.
 *
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class OpenSans implements FontInterface
{
    public function __construct(
        private int $size = 16,
    ) {}

    /**
     * Return the bundled Open Sans font path shipped with the package.
     */
    public function getPath(): string
    {
        return __DIR__.'/../../../assets/open_sans.ttf';
    }

    /**
     * Return the configured Open Sans point size.
     */
    public function getSize(): int
    {
        return $this->size;
    }

    public function withSize(int $size): self
    {
        return new self($size);
    }
}
