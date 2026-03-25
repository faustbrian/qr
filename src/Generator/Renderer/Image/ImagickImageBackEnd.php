<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Generator\Renderer\Image;

use Cline\Qr\Generator\Internal\Exception\RuntimeException;
use Cline\Qr\Generator\Renderer\Color\Alpha;
use Cline\Qr\Generator\Renderer\Color\Cmyk;
use Cline\Qr\Generator\Renderer\Color\ColorInterface;
use Cline\Qr\Generator\Renderer\Color\Gray;
use Cline\Qr\Generator\Renderer\Color\Rgb;
use Cline\Qr\Generator\Renderer\Path\Close;
use Cline\Qr\Generator\Renderer\Path\Curve;
use Cline\Qr\Generator\Renderer\Path\EllipticArc;
use Cline\Qr\Generator\Renderer\Path\Line;
use Cline\Qr\Generator\Renderer\Path\Move;
use Cline\Qr\Generator\Renderer\Path\Path;
use Cline\Qr\Generator\Renderer\RendererStyle\Gradient;
use Cline\Qr\Generator\Renderer\RendererStyle\GradientType;
use Imagick;
use ImagickDraw;
use ImagickPixel;

use function class_exists;
use function intdiv;
use function sprintf;
use function sqrt;

/**
 * Image back end that renders paths through the Imagick extension.
 *
 * This implementation keeps both an `Imagick` canvas and an `ImagickDraw`
 * command stream alive until `done()` is called. It also tracks the active
 * transformation matrices so gradient dimensions can be adjusted to match the
 * same transformed coordinate system as the paths they fill.
 * @author Brian Faust <brian@cline.sh>
 */
final class ImagickImageBackEnd implements ImageBackEndInterface
{
    private ?Imagick $image;

    private ?ImagickDraw $draw;

    private ?int $gradientCount;

    /** @var null|array<TransformationMatrix> */
    private ?array $matrices;

    private ?int $matrixIndex;

    public function __construct(
        private readonly string $imageFormat = 'png',
        private readonly int $compressionQuality = 100,
    ) {
        if (!class_exists(Imagick::class)) {
            throw RuntimeException::withMessage('You need to install the imagick extension to use this back end');
        }
    }

    /**
     * Allocate a fresh Imagick image and drawing context.
     */
    public function new(int $size, ColorInterface $backgroundColor): void
    {
        $this->image = new Imagick();
        $this->image->newImage($size, $size, $this->getColorPixel($backgroundColor));
        $this->image->setImageFormat($this->imageFormat);
        $this->image->setCompressionQuality($this->compressionQuality);
        $this->draw = new ImagickDraw();
        $this->gradientCount = 0;
        $this->matrices = [new TransformationMatrix()];
        $this->matrixIndex = 0;
    }

    /**
     * Apply a scale transform to future drawing commands and gradient math.
     */
    public function scale(float $size): void
    {
        if (null === $this->draw) {
            throw RuntimeException::withMessage('No image has been started');
        }

        $this->draw->scale($size, $size);
        $this->matrices[$this->matrixIndex] = $this->matrices[$this->matrixIndex]
            ->multiply(TransformationMatrix::scale($size));
    }

    /**
     * Apply a translation transform to future drawing commands and gradient math.
     */
    public function translate(float $x, float $y): void
    {
        if (null === $this->draw) {
            throw RuntimeException::withMessage('No image has been started');
        }

        $this->draw->translate($x, $y);
        $this->matrices[$this->matrixIndex] = $this->matrices[$this->matrixIndex]
            ->multiply(TransformationMatrix::translate($x, $y));
    }

    /**
     * Apply a rotation transform to future drawing commands and gradient math.
     */
    public function rotate(int $degrees): void
    {
        if (null === $this->draw) {
            throw RuntimeException::withMessage('No image has been started');
        }

        $this->draw->rotate($degrees);
        $this->matrices[$this->matrixIndex] = $this->matrices[$this->matrixIndex]
            ->multiply(TransformationMatrix::rotate($degrees));
    }

    /**
     * Save the current drawing and transform state.
     */
    public function push(): void
    {
        if (null === $this->draw) {
            throw RuntimeException::withMessage('No image has been started');
        }

        $this->draw->push();
        $this->matrices[++$this->matrixIndex] = $this->matrices[$this->matrixIndex - 1];
    }

    /**
     * Restore the previously pushed drawing and transform state.
     */
    public function pop(): void
    {
        if (null === $this->draw) {
            throw RuntimeException::withMessage('No image has been started');
        }

        $this->draw->pop();
        unset($this->matrices[$this->matrixIndex--]);
    }

    /**
     * Fill a path with a flat Imagick color.
     */
    public function drawPathWithColor(Path $path, ColorInterface $color): void
    {
        if (null === $this->draw) {
            throw RuntimeException::withMessage('No image has been started');
        }

        $this->draw->setFillColor($this->getColorPixel($color));
        $this->drawPath($path);
    }

    /**
     * Fill a path with an Imagick pattern-based gradient.
     */
    public function drawPathWithGradient(
        Path $path,
        Gradient $gradient,
        float $x,
        float $y,
        float $width,
        float $height,
    ): void {
        if (null === $this->draw) {
            throw RuntimeException::withMessage('No image has been started');
        }

        $this->draw->setFillPatternURL('#'.$this->createGradientFill($gradient, $x, $y, $width, $height));
        $this->drawPath($path);
    }

    /**
     * Flush the drawing commands into the image and return the binary blob.
     */
    public function done(): string
    {
        if (null === $this->draw) {
            throw RuntimeException::withMessage('No image has been started');
        }

        $this->image->drawImage($this->draw);
        $blob = $this->image->getImageBlob();
        $this->draw->clear();
        $this->image->clear();
        $this->draw = null;
        $this->image = null;
        $this->gradientCount = null;

        return $blob;
    }

    /**
     * Replay a normalized path into the active `ImagickDraw` object.
     */
    private function drawPath(Path $path): void
    {
        $this->draw->pathStart();

        foreach ($path as $op) {
            switch (true) {
                case $op instanceof Move:
                    $this->draw->pathMoveToAbsolute($op->getX(), $op->getY());

                    break;

                case $op instanceof Line:
                    $this->draw->pathLineToAbsolute($op->getX(), $op->getY());

                    break;

                case $op instanceof EllipticArc:
                    $this->draw->pathEllipticArcAbsolute(
                        $op->getXRadius(),
                        $op->getYRadius(),
                        $op->getXAxisAngle(),
                        $op->isLargeArc(),
                        $op->isSweep(),
                        $op->getX(),
                        $op->getY(),
                    );

                    break;

                case $op instanceof Curve:
                    $this->draw->pathCurveToAbsolute(
                        $op->getX1(),
                        $op->getY1(),
                        $op->getX2(),
                        $op->getY2(),
                        $op->getX3(),
                        $op->getY3(),
                    );

                    break;

                case $op instanceof Close:
                    $this->draw->pathClose();

                    break;

                default:
                    throw RuntimeException::withMessage('Unexpected draw operation: '.$op::class);
            }
        }

        $this->draw->pathFinish();
    }

    /**
     * Create a reusable gradient pattern and return its pattern identifier.
     *
     * The current transformation matrix is applied to the width and height so
     * the gradient fills the same transformed area as the path that references
     * it.
     */
    private function createGradientFill(Gradient $gradient, float $x, float $y, float $width, float $height): string
    {
        [$width, $height] = $this->matrices[$this->matrixIndex]->apply($width, $height);

        $startColor = $this->getColorPixel($gradient->getStartColor())->getColorAsString();
        $endColor = $this->getColorPixel($gradient->getEndColor())->getColorAsString();
        $gradientImage = new Imagick();

        switch ($gradient->getType()) {
            case GradientType::HORIZONTAL:
                $gradientImage->newPseudoImage((int) $height, (int) $width, sprintf(
                    'gradient:%s-%s',
                    $startColor,
                    $endColor,
                ));
                $gradientImage->rotateImage('transparent', -90);

                break;

            case GradientType::VERTICAL:
                $gradientImage->newPseudoImage((int) $width, (int) $height, sprintf(
                    'gradient:%s-%s',
                    $startColor,
                    $endColor,
                ));

                break;

            case GradientType::DIAGONAL:
            case GradientType::INVERSE_DIAGONAL:
                $gradientImage->newPseudoImage((int) ($width * sqrt(2)), (int) ($height * sqrt(2)), sprintf(
                    'gradient:%s-%s',
                    $startColor,
                    $endColor,
                ));

                if (GradientType::DIAGONAL === $gradient->getType()) {
                    $gradientImage->rotateImage('transparent', -45);
                } else {
                    $gradientImage->rotateImage('transparent', -135);
                }

                $rotatedWidth = $gradientImage->getImageWidth();
                $rotatedHeight = $gradientImage->getImageHeight();

                $gradientImage->setImagePage($rotatedWidth, $rotatedHeight, 0, 0);
                $gradientImage->cropImage(
                    intdiv($rotatedWidth, 2) - 2,
                    intdiv($rotatedHeight, 2) - 2,
                    intdiv($rotatedWidth, 4) + 1,
                    intdiv($rotatedWidth, 4) + 1,
                );

                break;

            case GradientType::RADIAL:
                $gradientImage->newPseudoImage((int) $width, (int) $height, sprintf(
                    'radial-gradient:%s-%s',
                    $startColor,
                    $endColor,
                ));

                break;
        }

        $id = sprintf('g%d', ++$this->gradientCount);
        $this->draw->pushPattern($id, 0, 0, $width, $height);
        $this->draw->composite(Imagick::COMPOSITE_COPY, 0, 0, $width, $height, $gradientImage);
        $this->draw->popPattern();

        return $id;
    }

    /**
     * Convert a renderer color into an `ImagickPixel`, preserving alpha.
     */
    private function getColorPixel(ColorInterface $color): ImagickPixel
    {
        $alpha = 100;

        if ($color instanceof Alpha) {
            $alpha = $color->getAlpha();
            $color = $color->getBaseColor();
        }

        if ($color instanceof Rgb) {
            return new ImagickPixel(sprintf(
                'rgba(%d, %d, %d, %F)',
                $color->getRed(),
                $color->getGreen(),
                $color->getBlue(),
                $alpha / 100,
            ));
        }

        if ($color instanceof Cmyk) {
            return new ImagickPixel(sprintf(
                'cmyka(%d, %d, %d, %d, %F)',
                $color->getCyan(),
                $color->getMagenta(),
                $color->getYellow(),
                $color->getBlack(),
                $alpha / 100,
            ));
        }

        if ($color instanceof Gray) {
            return new ImagickPixel(sprintf(
                'graya(%d%%, %F)',
                $color->getGray(),
                $alpha / 100,
            ));
        }

        return $this->getColorPixel(
            new Alpha($alpha, $color->toRgb()),
        );
    }
}
