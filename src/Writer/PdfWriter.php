<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Writer;

use Cline\Qr\Exception\InvalidArgumentException;
use Cline\Qr\Exception\RuntimeException;
use Cline\Qr\Generator\MatrixFactory;
use Cline\Qr\Label\LabelInterface;
use Cline\Qr\Logo\LogoInterface;
use Cline\Qr\QrCodeInterface;
use Cline\Qr\Writer\Result\PdfResult;
use Cline\Qr\Writer\Result\ResultInterface;
use FPDF;

use function array_key_exists;
use function class_exists;
use function getimagesize;
use function implode;
use function in_array;
use function is_numeric;
use function is_scalar;
use function sprintf;
use function throw_if;
use function throw_unless;

/**
 * Writer that paints QR modules into an `FPDF` document.
 *
 * The writer either uses an injected `FPDF` instance or creates one on demand,
 * then draws the background, QR modules, optional logo, optional label, and
 * optional hyperlink into PDF coordinates.
 *
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class PdfWriter implements WriterInterface
{
    public const string WRITER_OPTION_UNIT = 'unit';

    public const string WRITER_OPTION_PDF = 'fpdf';

    public const string WRITER_OPTION_X = 'x';

    public const string WRITER_OPTION_Y = 'y';

    public const string WRITER_OPTION_LINK = 'link';

    /**
     * Render the QR code into a PDF result, optionally reusing an existing
     * `FPDF` instance supplied in the writer options.
     */
    public function write(QrCodeInterface $qrCode, ?LogoInterface $logo = null, ?LabelInterface $label = null, array $options = []): ResultInterface
    {
        $matrixFactory = new MatrixFactory();
        $matrix = $matrixFactory->create($qrCode);

        $unit = $this->stringOption($options, self::WRITER_OPTION_UNIT, 'mm');

        $allowedUnits = ['mm', 'pt', 'cm', 'in'];

        if (!in_array($unit, $allowedUnits, true)) {
            throw InvalidArgumentException::withMessage(
                sprintf(
                    'PDF Measure unit should be one of [%s]',
                    implode(', ', $allowedUnits),
                ),
            );
        }

        $labelSpace = 0;

        if ($label instanceof LabelInterface) {
            $labelSpace = 30;
        }

        throw_unless(class_exists(FPDF::class), RuntimeException::withMessage('Unable to find FPDF: check your installation'));

        $foregroundColor = $qrCode->getForegroundColor();
        throw_if($foregroundColor->getAlpha() > 0, InvalidArgumentException::withMessage('PDF Writer does not support alpha channels'));

        $backgroundColor = $qrCode->getBackgroundColor();

        throw_if($backgroundColor->getAlpha() > 0, InvalidArgumentException::withMessage('PDF Writer does not support alpha channels'));

        if (isset($options[self::WRITER_OPTION_PDF])) {
            $fpdf = $options[self::WRITER_OPTION_PDF];
            throw_unless($fpdf instanceof FPDF, InvalidArgumentException::withMessage('pdf option must be an instance of FPDF'));
        } else {
            /** @todo Check how to add label height later */
            $fpdf = new FPDF('P', $unit, [$matrix->getOuterSize(), $matrix->getOuterSize() + $labelSpace]);
            $fpdf->AddPage();
        }

        $x = $this->floatOption($options, self::WRITER_OPTION_X, 0.0);
        $y = $this->floatOption($options, self::WRITER_OPTION_Y, 0.0);

        $fpdf->SetFillColor($backgroundColor->getRed(), $backgroundColor->getGreen(), $backgroundColor->getBlue());
        $fpdf->Rect($x, $y, $matrix->getOuterSize(), $matrix->getOuterSize(), 'F');
        $fpdf->SetFillColor($foregroundColor->getRed(), $foregroundColor->getGreen(), $foregroundColor->getBlue());

        for ($rowIndex = 0; $rowIndex < $matrix->getBlockCount(); ++$rowIndex) {
            for ($columnIndex = 0; $columnIndex < $matrix->getBlockCount(); ++$columnIndex) {
                if (1 !== $matrix->getBlockValue($rowIndex, $columnIndex)) {
                    continue;
                }

                $fpdf->Rect(
                    $x + $matrix->getMarginLeft() + ($columnIndex * $matrix->getBlockSize()),
                    $y + $matrix->getMarginLeft() + ($rowIndex * $matrix->getBlockSize()),
                    $matrix->getBlockSize(),
                    $matrix->getBlockSize(),
                    'F',
                );
            }
        }

        if ($logo instanceof LogoInterface) {
            $this->addLogo($logo, $fpdf, $x, $y, $matrix->getOuterSize());
        }

        if ($label instanceof LabelInterface) {
            $fpdf->SetXY($x, $y + $matrix->getOuterSize() + $labelSpace - 25);
            $fpdf->SetFont('Helvetica', '', $label->getFont()->getSize());
            $fpdf->Cell($matrix->getOuterSize(), 0, $label->getText(), 0, 0, 'C');
        }

        if (isset($options[self::WRITER_OPTION_LINK])) {
            $link = $this->stringOption($options, self::WRITER_OPTION_LINK);
            $fpdf->Link($x, $y, $x + $matrix->getOuterSize(), $y + $matrix->getOuterSize(), $link);
        }

        return new PdfResult($matrix, $fpdf);
    }

    /**
     * Read a string-valued writer option.
     *
     * @param array<string, mixed> $options
     */
    private function stringOption(array $options, string $key, string $default = ''): string
    {
        if (!array_key_exists($key, $options)) {
            return $default;
        }

        $value = $options[$key];

        if (!is_scalar($value) && null !== $value) {
            throw InvalidArgumentException::withMessage(
                sprintf('Writer option "%s" must be scalar', $key),
            );
        }

        return (string) $value;
    }

    /**
     * Read a numeric writer option as a float.
     *
     * @param array<string, mixed> $options
     */
    private function floatOption(array $options, string $key, float $default = 0.0): float
    {
        if (!array_key_exists($key, $options)) {
            return $default;
        }

        $value = $options[$key];

        if (!is_numeric($value)) {
            throw InvalidArgumentException::withMessage(
                sprintf('Writer option "%s" must be numeric', $key),
            );
        }

        return (float) $value;
    }

    /**
     * Add the configured logo image to the middle of the PDF output.
     */
    private function addLogo(LogoInterface $logo, FPDF $fpdf, float $x, float $y, float $size): void
    {
        $logoPath = $logo->getPath();
        $logoHeight = $logo->getResizeToHeight();
        $logoWidth = $logo->getResizeToWidth();

        if (null === $logoHeight || null === $logoWidth) {
            $imageSize = getimagesize($logoPath);

            if (!$imageSize) {
                throw RuntimeException::withMessage(
                    sprintf('Unable to read image size for logo "%s"', $logoPath),
                );
            }

            [$logoSourceWidth, $logoSourceHeight] = $imageSize;

            if (null === $logoWidth) {
                $logoWidth = $logoSourceWidth;
            }

            if (null === $logoHeight) {
                $aspectRatio = $logoWidth / $logoSourceWidth;
                $logoHeight = (int) ($logoSourceHeight * $aspectRatio);
            }
        }

        $logoX = $x + $size / 2 - $logoWidth / 2;
        $logoY = $y + $size / 2 - $logoHeight / 2;

        $fpdf->Image($logoPath, $logoX, $logoY, $logoWidth, $logoHeight);
    }
}
