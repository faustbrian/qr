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
use Cline\Qr\Writer\Result\BinaryResult;
use Cline\Qr\Writer\Result\ResultInterface;

/**
 * Writer that exposes only the normalized matrix data.
 *
 * This is useful for callers that want the package's matrix adaptation and
 * sizing logic but intend to handle serialization or rendering themselves.
 *
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class BinaryWriter implements WriterInterface
{
    /**
     * Adapt the QR configuration into a matrix and wrap it in a binary result.
     */
    public function write(QrCodeInterface $qrCode, ?LogoInterface $logo = null, ?LabelInterface $label = null, array $options = []): ResultInterface
    {
        $matrixFactory = new MatrixFactory();
        $matrix = $matrixFactory->create($qrCode);

        return new BinaryResult($matrix);
    }
}
