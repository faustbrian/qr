<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Generator\Renderer;

use Cline\Qr\Generator\Encoder\MatrixUtil;
use Cline\Qr\Generator\Encoder\QrCode;
use Cline\Qr\Generator\Internal\Exception\InvalidArgumentException;
use Cline\Qr\Generator\Renderer\Image\ImageBackEndInterface;
use Cline\Qr\Generator\Renderer\Path\Path;
use Cline\Qr\Generator\Renderer\RendererStyle\EyeFill;
use Cline\Qr\Generator\Renderer\RendererStyle\RendererStyle;

/**
 * High-level renderer that combines module paths, eye paths, and an image
 * back end into the final output.
 *
 * This renderer builds one module path for the payload area, draws eye shapes
 * separately so they can inherit or override fills independently, and then
 * delegates the actual serialization to an `ImageBackEndInterface`.
 * @author Brian Faust <brian@cline.sh>
 */
final class ImageRenderer implements RendererInterface
{
    public function __construct(
        private readonly RendererStyle $rendererStyle,
        private readonly ImageBackEndInterface $imageBackEnd,
    ) {}

    /**
     * Render the QR code through the configured image back end.
     *
     * The matrix must be square. The renderer removes the position-detection
     * patterns from the module path because those eye shapes are drawn through
     * the dedicated eye-style pipeline instead.
     *
     * @throws InvalidArgumentException if matrix width doesn't match height
     */
    public function render(QrCode $qrCode): string
    {
        $size = $this->rendererStyle->getSize();
        $margin = $this->rendererStyle->getMargin();
        $matrix = $qrCode->getMatrix();
        $matrixSize = $matrix->getWidth();

        if ($matrixSize !== $matrix->getHeight()) {
            throw InvalidArgumentException::withMessage('Matrix must have the same width and height');
        }

        $totalSize = $matrixSize + ($margin * 2);
        $moduleSize = $size / $totalSize;
        $fill = $this->rendererStyle->getFill();

        $this->imageBackEnd->new($size, $fill->getBackgroundColor());
        $this->imageBackEnd->scale((float) $moduleSize);
        $this->imageBackEnd->translate((float) $margin, (float) $margin);

        $module = $this->rendererStyle->getModule();
        $moduleMatrix = clone $matrix;
        MatrixUtil::removePositionDetectionPatterns($moduleMatrix);
        $modulePath = $this->drawEyes($matrixSize, $module->createPath($moduleMatrix));

        if ($fill->hasGradientFill()) {
            $this->imageBackEnd->drawPathWithGradient(
                $modulePath,
                $fill->getForegroundGradient(),
                0,
                0,
                $matrixSize,
                $matrixSize,
            );
        } else {
            $this->imageBackEnd->drawPathWithColor($modulePath, $fill->getForegroundColor());
        }

        return $this->imageBackEnd->done();
    }

    /**
     * Draw the three finder eyes and merge any inherited-color shapes back into
     * the module path.
     */
    private function drawEyes(int $matrixSize, Path $modulePath): Path
    {
        $fill = $this->rendererStyle->getFill();

        $eye = $this->rendererStyle->getEye();
        $externalPath = $eye->getExternalPath();
        $internalPath = $eye->getInternalPath();

        $modulePath = $this->drawEye(
            $externalPath,
            $internalPath,
            $fill->getTopLeftEyeFill(),
            3.5,
            3.5,
            0,
            $modulePath,
        );
        $modulePath = $this->drawEye(
            $externalPath,
            $internalPath,
            $fill->getTopRightEyeFill(),
            $matrixSize - 3.5,
            3.5,
            90,
            $modulePath,
        );

        return $this->drawEye(
            $externalPath,
            $internalPath,
            $fill->getBottomLeftEyeFill(),
            3.5,
            $matrixSize - 3.5,
            -90,
            $modulePath,
        );
    }

    /**
     * Render one finder eye, either by appending its paths to the module path
     * or by painting its colors directly through the image back end.
     */
    private function drawEye(
        Path $externalPath,
        Path $internalPath,
        EyeFill $fill,
        float $xTranslation,
        float $yTranslation,
        int $rotation,
        Path $modulePath,
    ): Path {
        if ($fill->inheritsBothColors()) {
            return $modulePath
                ->append(
                    $externalPath->rotate($rotation)->translate($xTranslation, $yTranslation),
                )
                ->append(
                    $internalPath->rotate($rotation)->translate($xTranslation, $yTranslation),
                );
        }

        $this->imageBackEnd->push();
        $this->imageBackEnd->translate($xTranslation, $yTranslation);

        if (0 !== $rotation) {
            $this->imageBackEnd->rotate($rotation);
        }

        if ($fill->inheritsExternalColor()) {
            $modulePath = $modulePath->append(
                $externalPath->rotate($rotation)->translate($xTranslation, $yTranslation),
            );
        } else {
            $this->imageBackEnd->drawPathWithColor($externalPath, $fill->getExternalColor());
        }

        if ($fill->inheritsInternalColor()) {
            $modulePath = $modulePath->append(
                $internalPath->rotate($rotation)->translate($xTranslation, $yTranslation),
            );
        } else {
            $this->imageBackEnd->drawPathWithColor($internalPath, $fill->getInternalColor());
        }

        $this->imageBackEnd->pop();

        return $modulePath;
    }
}
