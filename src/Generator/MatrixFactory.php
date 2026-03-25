<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Generator;

use Cline\Qr\Generator\Encoder\Encoder;
use Cline\Qr\Matrix\Matrix;
use Cline\Qr\Matrix\MatrixFactoryInterface;
use Cline\Qr\Matrix\MatrixInterface;
use Cline\Qr\QrCodeInterface;

/**
 * Convert a public `QrCodeInterface` value object into a render-ready matrix.
 *
 * The public package API keeps the QR payload and styling concerns separate
 * from the internal encoder. This factory bridges those layers by invoking the
 * generator engine, normalizing its byte matrix into integer block values, and
 * returning the package-level `MatrixInterface` used by writers.
 *
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class MatrixFactory implements MatrixFactoryInterface
{
    /**
     * Encode the QR payload and adapt the internal byte matrix to the package
     * matrix abstraction.
     *
     * The returned matrix preserves the caller's requested size, margin, and
     * block-rounding mode while translating the engine's `1` and `0` modules
     * into the block grid consumed by downstream renderers.
     */
    public function create(QrCodeInterface $qrCode): MatrixInterface
    {
        $errorCorrectionLevel = ErrorCorrectionLevelConverter::convertToGeneratorErrorCorrectionLevel($qrCode->getErrorCorrectionLevel());
        $encodedMatrix = Encoder::encode($qrCode->getData(), $errorCorrectionLevel, (string) $qrCode->getEncoding())->getMatrix();

        $blockValues = [];
        $columnCount = $encodedMatrix->getWidth();
        $rowCount = $encodedMatrix->getHeight();

        for ($rowIndex = 0; $rowIndex < $rowCount; ++$rowIndex) {
            $blockValues[$rowIndex] = [];

            for ($columnIndex = 0; $columnIndex < $columnCount; ++$columnIndex) {
                $blockValues[$rowIndex][$columnIndex] = 1 === $encodedMatrix->get($columnIndex, $rowIndex) ? 1 : 0;
            }
        }

        return new Matrix($blockValues, $qrCode->getSize(), $qrCode->getMargin(), $qrCode->getRoundBlockSizeMode());
    }
}
