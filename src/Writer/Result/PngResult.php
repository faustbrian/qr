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

use function imagepng;
use function imagetruecolortopalette;
use function ob_get_clean;
use function ob_start;

/**
 * Result wrapper for PNG-encoded GD output.
 *
 * The result can optionally reduce the image to a palette before serialization,
 * which keeps the PNG writer's color-count option encapsulated in one place.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class PngResult extends AbstractResult
{
    public function __construct(
        MatrixInterface $matrix,
        private readonly GdImage $image,
        private readonly int $quality = -1,
        private readonly ?int $numberOfColors = null,
    ) {
        parent::__construct($matrix);
    }

    /**
     * Serialize the GD image as PNG bytes, applying optional palette reduction.
     */
    public function getString(): string
    {
        ob_start();

        if (null !== $this->numberOfColors) {
            imagetruecolortopalette($this->image, false, $this->numberOfColors);
        }

        imagepng($this->image, quality: $this->quality);

        return (string) ob_get_clean();
    }

    /**
     * Return the PNG mime type.
     */
    public function getMimeType(): string
    {
        return 'image/png';
    }
}
