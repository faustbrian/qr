<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Writer;

use Cline\Qr\Generator\MatrixFactory;
use Cline\Qr\Label\LabelInterface;
use Cline\Qr\Logo\LogoInterface;
use Cline\Qr\QrCodeInterface;
use Cline\Qr\Writer\Result\ConsoleResult;
use Cline\Qr\Writer\Result\ResultInterface;

/**
 * Writer that prepares ANSI/console-oriented QR output.
 *
 * The writer reuses the common matrix adaptation pipeline and preserves the
 * configured foreground and background colors for the console result object.
 *
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class ConsoleWriter implements WriterInterface
{
    /**
     * Adapt the QR configuration into a matrix and wrap it in a console result.
     *
     * @param mixed $options
     */
    public function write(QrCodeInterface $qrCode, ?LogoInterface $logo = null, ?LabelInterface $label = null, $options = []): ResultInterface
    {
        $matrixFactory = new MatrixFactory();
        $matrix = $matrixFactory->create($qrCode);

        return new ConsoleResult($matrix, $qrCode->getForegroundColor(), $qrCode->getBackgroundColor());
    }
}
