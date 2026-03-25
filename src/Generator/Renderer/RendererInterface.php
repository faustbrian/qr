<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Generator\Renderer;

use Cline\Qr\Generator\Encoder\QrCode;

/**
 * Contract for encoder-output renderers.
 *
 * Implementations accept a fully encoded `QrCode` matrix and transform it into
 * a specific representation such as a raster image, vector image, or plain
 * text.
 * @author Brian Faust <brian@cline.sh>
 */
interface RendererInterface
{
    /**
     * Render the encoded QR symbol into the renderer's output format.
     */
    public function render(QrCode $qrCode): string;
}
