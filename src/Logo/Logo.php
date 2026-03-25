<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Logo;

/**
 * Immutable logo placement and resize configuration.
 *
 * Writers use this value object to determine what image to load, how it should
 * be resized, and whether the QR background beneath it should be punched out.
 *
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class Logo implements LogoInterface
{
    public function __construct(
        private string $path,
        private ?int $resizeToWidth = null,
        private ?int $resizeToHeight = null,
        private bool $punchoutBackground = false,
    ) {}

    /**
     * Return the local path or remote URL for the logo source.
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Return the requested target width, if one was provided.
     */
    public function getResizeToWidth(): ?int
    {
        return $this->resizeToWidth;
    }

    /**
     * Return the requested target height, if one was provided.
     */
    public function getResizeToHeight(): ?int
    {
        return $this->resizeToHeight;
    }

    /**
     * Return whether the logo should punch out the QR background beneath it.
     */
    public function getPunchoutBackground(): bool
    {
        return $this->punchoutBackground;
    }
}
