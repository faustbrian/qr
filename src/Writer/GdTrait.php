<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Writer;

use Cline\Qr\Decoder\QrReader;
use Cline\Qr\Exception\InvalidValidationDataException;
use Cline\Qr\Exception\RuntimeException;
use Cline\Qr\Generator\MatrixFactory;
use Cline\Qr\ImageData\LabelImageData;
use Cline\Qr\ImageData\LogoImageData;
use Cline\Qr\Label\LabelAlignment;
use Cline\Qr\Label\LabelInterface;
use Cline\Qr\Logo\LogoInterface;
use Cline\Qr\Matrix\MatrixInterface;
use Cline\Qr\QrCodeInterface;
use Cline\Qr\RoundBlockSizeMode;
use Cline\Qr\Writer\Result\GdResult;
use Cline\Qr\Writer\Result\ResultInterface;

use function extension_loaded;
use function get_debug_type;
use function imagealphablending;
use function imagecolorallocatealpha;
use function imagecopyresampled;
use function imagecreatetruecolor;
use function imagefill;
use function imagefilledrectangle;
use function imagesavealpha;
use function imagesetpixel;
use function imagesx;
use function imagesy;
use function imagettftext;
use function is_string;
use function throw_if;
use function throw_unless;

/**
 * Shared GD-based rendering pipeline for raster writers.
 *
 * The trait centralizes matrix adaptation, base image generation, optional logo
 * compositing, optional label drawing, and result validation so PNG, GIF, and
 * other GD-backed writers stay thin.
 *
 * @author Brian Faust <brian@cline.sh>
 */
trait GdTrait
{
    /**
     * Adapt the public QR configuration into the render-ready matrix model.
     */
    public function getMatrix(QrCodeInterface $qrCode): MatrixInterface
    {
        $matrixFactory = new MatrixFactory();

        return $matrixFactory->create($qrCode);
    }

    /**
     * Render the QR code into a GD image and return the intermediate GD result.
     *
     * The trait creates a base QR image at block resolution, resamples it into
     * the requested target size with margins, and then applies optional logo and
     * label decorations.
     *
     * @param array<string, mixed> $options
     */
    public function writeGd(QrCodeInterface $qrCode, ?LogoInterface $logo = null, ?LabelInterface $label = null, array $options = []): GdResult
    {
        throw_unless(extension_loaded('gd'), RuntimeException::withMessage('Unable to generate image: please check if the GD extension is enabled and configured correctly'));

        $matrix = $this->getMatrix($qrCode);

        $baseBlockSize = RoundBlockSizeMode::None === $qrCode->getRoundBlockSizeMode() ? 10 : (int) ($matrix->getBlockSize());

        /** @var int<1, max> $baseImageSize */
        $baseImageSize = $matrix->getBlockCount() * $baseBlockSize;
        $baseImage = imagecreatetruecolor($baseImageSize, $baseImageSize);

        throw_unless($baseImage, RuntimeException::withMessage('Unable to generate image: please check if the GD extension is enabled and configured correctly'));

        $foregroundColor = imagecolorallocatealpha(
            $baseImage,
            $qrCode->getForegroundColor()->getRed(),
            $qrCode->getForegroundColor()->getGreen(),
            $qrCode->getForegroundColor()->getBlue(),
            $qrCode->getForegroundColor()->getAlpha(),
        );

        throw_if(false === $foregroundColor, RuntimeException::withMessage('Unable to generate image: please check if the GD extension is enabled and configured correctly'));

        $transparentColor = imagecolorallocatealpha($baseImage, 255, 255, 255, 127);

        throw_if(false === $transparentColor, RuntimeException::withMessage('Unable to generate image: please check if the GD extension is enabled and configured correctly'));

        imagefill($baseImage, 0, 0, $transparentColor);

        for ($rowIndex = 0; $rowIndex < $matrix->getBlockCount(); ++$rowIndex) {
            for ($columnIndex = 0; $columnIndex < $matrix->getBlockCount(); ++$columnIndex) {
                if (1 !== $matrix->getBlockValue($rowIndex, $columnIndex)) {
                    continue;
                }

                imagefilledrectangle(
                    $baseImage,
                    $columnIndex * $baseBlockSize,
                    $rowIndex * $baseBlockSize,
                    ($columnIndex + 1) * $baseBlockSize - 1,
                    ($rowIndex + 1) * $baseBlockSize - 1,
                    $foregroundColor,
                );
            }
        }

        /** @var int<1, max> $targetWidth */
        $targetWidth = $matrix->getOuterSize();

        /** @var int<1, max> $targetHeight */
        $targetHeight = $matrix->getOuterSize();

        if ($label instanceof LabelInterface) {
            $labelImageData = LabelImageData::createForLabel($label);
            $targetHeight += $labelImageData->getHeight() + $label->getMargin()->getTop() + $label->getMargin()->getBottom();
        }

        /** @var int<1, max> $targetHeight */
        $targetImage = imagecreatetruecolor($targetWidth, $targetHeight);

        throw_unless($targetImage, RuntimeException::withMessage('Unable to generate image: please check if the GD extension is enabled and configured correctly'));

        $backgroundColor = imagecolorallocatealpha(
            $targetImage,
            $qrCode->getBackgroundColor()->getRed(),
            $qrCode->getBackgroundColor()->getGreen(),
            $qrCode->getBackgroundColor()->getBlue(),
            $qrCode->getBackgroundColor()->getAlpha(),
        );

        throw_if(false === $backgroundColor, RuntimeException::withMessage('Unable to generate image: please check if the GD extension is enabled and configured correctly'));

        imagefill($targetImage, 0, 0, $backgroundColor);

        imagecopyresampled(
            $targetImage,
            $baseImage,
            $matrix->getMarginLeft(),
            $matrix->getMarginLeft(),
            0,
            0,
            $matrix->getInnerSize(),
            $matrix->getInnerSize(),
            imagesx($baseImage),
            imagesy($baseImage),
        );

        if ($qrCode->getBackgroundColor()->getAlpha() > 0) {
            imagesavealpha($targetImage, true);
        }

        $result = new GdResult($matrix, $targetImage);

        if ($logo instanceof LogoInterface) {
            $result = $this->addLogo($logo, $result);
        }

        if ($label instanceof LabelInterface) {
            return $this->addLabel($label, $result);
        }

        return $result;
    }

    /**
     * Decode the rendered output again and ensure it matches the expected
     * payload.
     */
    public function validateResult(ResultInterface $result, string $expectedData): void
    {
        $string = $result->getString();

        $reader = new QrReader($string, QrReader::SOURCE_TYPE_BLOB);
        $actualData = $reader->text();
        $actualDataString = is_string($actualData)
            ? $actualData
            : get_debug_type($actualData);

        if (!is_string($actualData) || $actualData !== $expectedData) {
            throw InvalidValidationDataException::forExpectedAndActual($expectedData, $actualDataString);
        }
    }

    /**
     * Composite a raster logo into the center of the GD image.
     */
    private function addLogo(LogoInterface $logo, GdResult $result): GdResult
    {
        $logoImageData = LogoImageData::createForLogo($logo);

        throw_if('image/svg+xml' === $logoImageData->getMimeType(), RuntimeException::withMessage('GD Writer does not support SVG logo'));

        $targetImage = $result->getImage();
        $matrix = $result->getMatrix();

        if ($logoImageData->getPunchoutBackground()) {
            $transparent = imagecolorallocatealpha($targetImage, 255, 255, 255, 127);

            throw_if(false === $transparent, RuntimeException::withMessage('Unable to allocate color: please check if the GD extension is enabled and configured correctly'));

            imagealphablending($targetImage, false);
            $xOffsetStart = (int) ($matrix->getOuterSize() / 2 - $logoImageData->getWidth() / 2);
            $yOffsetStart = (int) ($matrix->getOuterSize() / 2 - $logoImageData->getHeight() / 2);

            for ($xOffset = $xOffsetStart; $xOffset < $xOffsetStart + $logoImageData->getWidth(); ++$xOffset) {
                for ($yOffset = $yOffsetStart; $yOffset < $yOffsetStart + $logoImageData->getHeight(); ++$yOffset) {
                    imagesetpixel($targetImage, $xOffset, $yOffset, $transparent);
                }
            }
        }

        imagecopyresampled(
            $targetImage,
            $logoImageData->getImage(),
            (int) ($matrix->getOuterSize() / 2 - $logoImageData->getWidth() / 2),
            (int) ($matrix->getOuterSize() / 2 - $logoImageData->getHeight() / 2),
            0,
            0,
            $logoImageData->getWidth(),
            $logoImageData->getHeight(),
            imagesx($logoImageData->getImage()),
            imagesy($logoImageData->getImage()),
        );

        return new GdResult($matrix, $targetImage);
    }

    /**
     * Draw the configured text label onto the GD image.
     */
    private function addLabel(LabelInterface $label, GdResult $result): GdResult
    {
        $targetImage = $result->getImage();

        $labelImageData = LabelImageData::createForLabel($label);

        $textColor = imagecolorallocatealpha(
            $targetImage,
            $label->getTextColor()->getRed(),
            $label->getTextColor()->getGreen(),
            $label->getTextColor()->getBlue(),
            $label->getTextColor()->getAlpha(),
        );

        throw_if(false === $textColor, RuntimeException::withMessage('Unable to allocate color: please check if the GD extension is enabled and configured correctly'));

        $x = (int) (imagesx($targetImage) / 2 - $labelImageData->getWidth() / 2);
        $y = imagesy($targetImage) - $label->getMargin()->getBottom();

        if (LabelAlignment::Left === $label->getAlignment()) {
            $x = $label->getMargin()->getLeft();
        } elseif (LabelAlignment::Right === $label->getAlignment()) {
            $x = imagesx($targetImage) - $labelImageData->getWidth() - $label->getMargin()->getRight();
        }

        imagettftext($targetImage, $label->getFont()->getSize(), 0, $x, $y, $textColor, $label->getFont()->getPath(), $label->getText());

        return new GdResult($result->getMatrix(), $targetImage);
    }
}
