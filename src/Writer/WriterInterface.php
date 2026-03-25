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
use Cline\Qr\Writer\Result\ResultInterface;

/**
 * Common contract for all public writer implementations.
 *
 * Writers take a public QR configuration plus optional logo, label, and
 * writer-specific options, then return a result object representing the final
 * rendered output.
 *
 * @author Brian Faust <brian@cline.sh>
 */
interface WriterInterface
{
    /**
     * Render the supplied QR configuration into a writer-specific result.
     *
     * @param array<string, mixed> $options
     */
    public function write(QrCodeInterface $qrCode, ?LogoInterface $logo = null, ?LabelInterface $label = null, array $options = []): ResultInterface;
}
