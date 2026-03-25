<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Writer\Result;

use Cline\Qr\Exception\RuntimeException;
use Cline\Qr\Matrix\MatrixInterface;
use GdImage;

use function function_exists;
use function imagewebp;
use function ob_get_clean;
use function ob_start;
use function throw_unless;

/**
 * Result wrapper for WebP-encoded GD output.
 *
 * The result checks for runtime WebP support at serialization time so the
 * writer can fail with a clear message when GD lacks WebP capabilities.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class WebPResult extends AbstractResult
{
    public function __construct(
        MatrixInterface $matrix,
        private readonly GdImage $image,
        private readonly int $quality = -1,
    ) {
        parent::__construct($matrix);
    }

    /**
     * Serialize the GD image as WebP bytes.
     */
    public function getString(): string
    {
        throw_unless(
            function_exists('imagewebp'),
            RuntimeException::withMessage(
                'WebP support is not available in your GD installation',
            ),
        );

        ob_start();
        imagewebp($this->image, quality: $this->quality);

        return (string) ob_get_clean();
    }

    /**
     * Return the WebP mime type.
     */
    public function getMimeType(): string
    {
        return 'image/webp';
    }
}
