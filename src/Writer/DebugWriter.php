<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Writer;

use Cline\Qr\Exception\RuntimeException;
use Cline\Qr\Generator\MatrixFactory;
use Cline\Qr\Label\LabelInterface;
use Cline\Qr\Logo\LogoInterface;
use Cline\Qr\QrCodeInterface;
use Cline\Qr\Writer\Result\DebugResult;
use Cline\Qr\Writer\Result\ResultInterface;

use function throw_unless;

/**
 * Writer that captures QR render state for debugging and inspection.
 *
 * Instead of serializing immediately, this writer returns a rich debug result
 * containing the matrix, QR configuration, optional logo, optional label, and
 * writer options so callers can inspect or dump the intermediate state.
 *
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class DebugWriter implements ValidatingWriterInterface, WriterInterface
{
    /**
     * Build the matrix and package all render inputs into a debug result.
     */
    public function write(QrCodeInterface $qrCode, ?LogoInterface $logo = null, ?LabelInterface $label = null, array $options = []): ResultInterface
    {
        $matrixFactory = new MatrixFactory();
        $matrix = $matrixFactory->create($qrCode);

        return new DebugResult($matrix, $qrCode, $logo, $label, $options);
    }

    /**
     * Mark a debug result so later inspection can include validation state.
     */
    public function validateResult(ResultInterface $result, string $expectedData): void
    {
        throw_unless(
            $result instanceof DebugResult,
            RuntimeException::withMessage(
                'Unable to write logo: instance of DebugResult expected',
            ),
        );

        $result->setValidateResult(true);
    }
}
