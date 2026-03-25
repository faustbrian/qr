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
use Cline\Qr\Writer\Result\EpsResult;
use Cline\Qr\Writer\Result\ResultInterface;

use function number_format;

/**
 * Writer that emits a minimal EPS document made of filled rectangles.
 *
 * This writer renders each active module as a rectangle in PostScript space
 * after the matrix has been normalized to the package's final sizing model.
 *
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class EpsWriter implements WriterInterface
{
    public const int DECIMAL_PRECISION = 10;

    /**
     * Convert the matrix into EPS drawing commands and wrap them in an EPS
     * result object.
     */
    public function write(QrCodeInterface $qrCode, ?LogoInterface $logo = null, ?LabelInterface $label = null, array $options = []): ResultInterface
    {
        $matrixFactory = new MatrixFactory();
        $matrix = $matrixFactory->create($qrCode);

        $lines = [
            '%!PS-Adobe-3.0 EPSF-3.0',
            '%%BoundingBox: 0 0 '.$matrix->getOuterSize().' '.$matrix->getOuterSize(),
            '/F { rectfill } def',
            number_format($qrCode->getBackgroundColor()->getRed() / 100, 2, '.', ',').' '.number_format($qrCode->getBackgroundColor()->getGreen() / 100, 2, '.', ',').' '.number_format($qrCode->getBackgroundColor()->getBlue() / 100, 2, '.', ',').' setrgbcolor',
            '0 0 '.$matrix->getOuterSize().' '.$matrix->getOuterSize().' F',
            number_format($qrCode->getForegroundColor()->getRed() / 100, 2, '.', ',').' '.number_format($qrCode->getForegroundColor()->getGreen() / 100, 2, '.', ',').' '.number_format($qrCode->getForegroundColor()->getBlue() / 100, 2, '.', ',').' setrgbcolor',
        ];

        for ($rowIndex = 0; $rowIndex < $matrix->getBlockCount(); ++$rowIndex) {
            for ($columnIndex = 0; $columnIndex < $matrix->getBlockCount(); ++$columnIndex) {
                if (1 !== $matrix->getBlockValue($matrix->getBlockCount() - 1 - $rowIndex, $columnIndex)) {
                    continue;
                }

                $x = $matrix->getMarginLeft() + $matrix->getBlockSize() * $columnIndex;
                $y = $matrix->getMarginLeft() + $matrix->getBlockSize() * $rowIndex;
                $lines[] = number_format($x, self::DECIMAL_PRECISION, '.', '').' '.number_format($y, self::DECIMAL_PRECISION, '.', '').' '.number_format($matrix->getBlockSize(), self::DECIMAL_PRECISION, '.', '').' '.number_format($matrix->getBlockSize(), self::DECIMAL_PRECISION, '.', '').' F';
            }
        }

        return new EpsResult($matrix, $lines);
    }
}
