<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Label\Font;

use Cline\Qr\Exception\InvalidArgumentException;

use function file_exists;
use function sprintf;

/**
 * Immutable label font configuration.
 *
 * Labels use this value object to carry the font file path and point size into
 * image measurement and rendering code. The path is validated eagerly so later
 * label layout failures do not surface as harder-to-diagnose file errors.
 *
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class Font implements FontInterface
{
    public function __construct(
        private string $path,
        private int $size = 16,
    ) {
        $this->assertValidPath($path);
    }

    /**
     * Return the absolute or relative path to the TTF font file.
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Return the configured font size in points.
     */
    public function getSize(): int
    {
        return $this->size;
    }

    /**
     * Ensure the configured font path exists before the object is used.
     */
    private function assertValidPath(string $path): void
    {
        if (!file_exists($path)) {
            throw InvalidArgumentException::withMessage(
                sprintf('Invalid font path "%s"', $path),
            );
        }
    }
}
