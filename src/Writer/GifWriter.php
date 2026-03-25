<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Writer;

use Cline\Qr\Label\LabelInterface;
use Cline\Qr\Logo\LogoInterface;
use Cline\Qr\QrCodeInterface;
use Cline\Qr\Writer\Result\GifResult;
use Cline\Qr\Writer\Result\ResultInterface;

/**
 * GIF writer built on the shared GD pipeline.
 *
 * The writer delegates image construction, optional logo placement, optional
 * label drawing, and validation support to `GdTrait`, then wraps the image in a
 * GIF-specific result object.
 *
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class GifWriter implements ValidatingWriterInterface, WriterInterface
{
    use GdTrait;

    /**
     * Render the QR code through GD and return a GIF result wrapper.
     */
    public function write(QrCodeInterface $qrCode, ?LogoInterface $logo = null, ?LabelInterface $label = null, array $options = []): ResultInterface
    {
        $gdResult = $this->writeGd($qrCode, $logo, $label, $options);

        return new GifResult($gdResult->getMatrix(), $gdResult->getImage());
    }
}
