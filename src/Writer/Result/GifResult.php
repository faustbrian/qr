<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Writer\Result;

use Cline\Qr\Matrix\MatrixInterface;
use GdImage;

use function imagegif;
use function ob_get_clean;
use function ob_start;

/**
 * Result wrapper for GIF-encoded GD output.
 *
 * The result stores the GD image until serialization is requested, then writes
 * it to an output buffer through `imagegif()`.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class GifResult extends AbstractResult
{
    public function __construct(
        MatrixInterface $matrix,
        private readonly GdImage $image,
    ) {
        parent::__construct($matrix);
    }

    /**
     * Serialize the GD image as GIF bytes.
     */
    public function getString(): string
    {
        ob_start();
        imagegif($this->image);

        return (string) ob_get_clean();
    }

    /**
     * Return the GIF mime type.
     */
    public function getMimeType(): string
    {
        return 'image/gif';
    }
}
