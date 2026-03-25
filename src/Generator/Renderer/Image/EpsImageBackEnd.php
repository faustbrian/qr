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

use function implode;
use function in_array;
use function max;
use function round;
use function sprintf;
use function wordwrap;

/**
 * EPS back end that serializes drawing commands into PostScript.
 *
 * The backend keeps an in-memory EPS document while renderers push transforms,
 * paths, and fills into it. It supports flat colors and gradients, and it
 * automatically converts unsupported color models into a PostScript-friendly
 * representation before writing the output.
 * @author Brian Faust <brian@cline.sh>
 */
final class EpsImageBackEnd implements ImageBackEndInterface
{
    private const int PRECISION = 3;

    private ?string $eps;

    /**
     * Start a new EPS document with the requested canvas size and background.
     *
     * Fully transparent backgrounds are omitted so callers can generate EPS
     * output without a painted page rectangle.
     */
    public function new(int $size, ColorInterface $backgroundColor): void
    {
        $this->eps = "%!PS-Adobe-3.0 EPSF-3.0\n"
            ."%%Creator: Cline\\Qr\\Generator\n"
            .sprintf("%%%%BoundingBox: 0 0 %d %d \n", $size, $size)
            ."%%BeginProlog\n"
            ."save\n"
            ."50 dict begin\n"
            ."/q { gsave } bind def\n"
            ."/Q { grestore } bind def\n"
            ."/s { scale } bind def\n"
            ."/t { translate } bind def\n"
            ."/r { rotate } bind def\n"
            ."/n { newpath } bind def\n"
            ."/m { moveto } bind def\n"
            ."/l { lineto } bind def\n"
            ."/c { curveto } bind def\n"
            ."/z { closepath } bind def\n"
            ."/f { eofill } bind def\n"
            ."/rgb { setrgbcolor } bind def\n"
            ."/cmyk { setcmykcolor } bind def\n"
            ."/gray { setgray } bind def\n"
            ."%%EndProlog\n"
            ."1 -1 s\n"
            .sprintf("0 -%d t\n", $size);

        if ($backgroundColor instanceof Alpha && 0 === $backgroundColor->getAlpha()) {
            return;
        }

        $this->eps .= wordwrap(
            '0 0 m'
            .sprintf(' %s 0 l', (string) $size)
            .sprintf(' %s %s l', (string) $size, (string) $size)
            .sprintf(' 0 %s l', (string) $size)
            .' z'
            .' '.$this->getColorSetString($backgroundColor)." f\n",
            75,
            "\n ",
        );
    }

    /**
     * Apply a uniform scale transform to subsequent drawing operations.
     */
    public function scale(float $size): void
    {
        if (null === $this->eps) {
            throw RuntimeException::withMessage('No image has been started');
        }

        $this->eps .= sprintf("%1\$s %1\$s s\n", round($size, self::PRECISION));
    }

    /**
     * Translate subsequent drawing operations.
     */
    public function translate(float $x, float $y): void
    {
        if (null === $this->eps) {
            throw RuntimeException::withMessage('No image has been started');
        }

        $this->eps .= sprintf("%s %s t\n", round($x, self::PRECISION), round($y, self::PRECISION));
    }

    /**
     * Rotate subsequent drawing operations.
     */
    public function rotate(int $degrees): void
    {
        if (null === $this->eps) {
            throw RuntimeException::withMessage('No image has been started');
        }

        $this->eps .= sprintf("%d r\n", $degrees);
    }

    /**
     * Save the current PostScript graphics state.
     */
    public function push(): void
    {
        if (null === $this->eps) {
            throw RuntimeException::withMessage('No image has been started');
        }

        $this->eps .= "q\n";
    }

    /**
     * Restore the previous PostScript graphics state.
     */
    public function pop(): void
    {
        if (null === $this->eps) {
            throw RuntimeException::withMessage('No image has been started');
        }

        $this->eps .= "Q\n";
    }

    /**
     * Fill a path with a flat color.
     */
    public function drawPathWithColor(Path $path, ColorInterface $color): void
    {
        if (null === $this->eps) {
            throw RuntimeException::withMessage('No image has been started');
        }

        $fromX = 0;
        $fromY = 0;
        $this->eps .= wordwrap(
            'n '
            .$this->drawPathOperations($path, $fromX, $fromY)
            .' '.$this->getColorSetString($color)." f\n",
            75,
            "\n ",
        );
    }

    /**
     * Fill a path with a PostScript shading definition.
     */
    public function drawPathWithGradient(
        Path $path,
        Gradient $gradient,
        float $x,
        float $y,
        float $width,
        float $height,
    ): void {
        if (null === $this->eps) {
            throw RuntimeException::withMessage('No image has been started');
        }

        $fromX = 0;
        $fromY = 0;
        $this->eps .= wordwrap(
            'q n '.$this->drawPathOperations($path, $fromX, $fromY)."\n",
            75,
            "\n ",
        );

        $this->createGradientFill($gradient, $x, $y, $width, $height);
    }

    /**
     * Finalize the EPS document and return it as a string.
     */
    public function done(): string
    {
        if (null === $this->eps) {
            throw RuntimeException::withMessage('No image has been started');
        }

        $this->eps .= "%%TRAILER\nend restore\n%%EOF";
        $blob = $this->eps;
        $this->eps = null;

        return $blob;
    }

    /**
     * Convert path operations into EPS drawing commands.
     * @param mixed $fromX
     * @param mixed $fromY
     */
    private function drawPathOperations(iterable $ops, $fromX, $fromY): string
    {
        $pathData = [];

        foreach ($ops as $op) {
            switch (true) {
                case $op instanceof Move:
                    $fromX = $toX = round($op->getX(), self::PRECISION);
                    $fromY = $toY = round($op->getY(), self::PRECISION);
                    $pathData[] = sprintf('%s %s m', $toX, $toY);

                    break;

                case $op instanceof Line:
                    $fromX = $toX = round($op->getX(), self::PRECISION);
                    $fromY = $toY = round($op->getY(), self::PRECISION);
                    $pathData[] = sprintf('%s %s l', $toX, $toY);

                    break;

                case $op instanceof EllipticArc:
                    $pathData[] = $this->drawPathOperations($op->toCurves($fromX, $fromY), $fromX, $fromY);

                    break;

                case $op instanceof Curve:
                    $x1 = round($op->getX1(), self::PRECISION);
                    $y1 = round($op->getY1(), self::PRECISION);
                    $x2 = round($op->getX2(), self::PRECISION);
                    $y2 = round($op->getY2(), self::PRECISION);
                    $fromX = $x3 = round($op->getX3(), self::PRECISION);
                    $fromY = $y3 = round($op->getY3(), self::PRECISION);
                    $pathData[] = sprintf('%s %s %s %s %s %s c', $x1, $y1, $x2, $y2, $x3, $y3);

                    break;

                case $op instanceof Close:
                    $pathData[] = 'z';

                    break;

                default:
                    throw RuntimeException::withMessage('Unexpected draw operation: '.$op::class);
            }
        }

        return implode(' ', $pathData);
    }

    /**
     * Emit the shading dictionary and fill commands for a gradient.
     */
    private function createGradientFill(Gradient $gradient, float $x, float $y, float $width, float $height): void
    {
        $startColor = $gradient->getStartColor();
        $endColor = $gradient->getEndColor();

        if ($startColor instanceof Alpha) {
            $startColor = $startColor->getBaseColor();
        }

        $startColorType = $startColor::class;

        if (!in_array($startColorType, [Rgb::class, Cmyk::class, Gray::class], true)) {
            $startColorType = Cmyk::class;
            $startColor = $startColor->toCmyk();
        }

        if ($endColor::class !== $startColorType) {
            switch ($startColorType) {
                case Cmyk::class:
                    $endColor = $endColor->toCmyk();

                    break;

                case Rgb::class:
                    $endColor = $endColor->toRgb();

                    break;

                case Gray::class:
                    $endColor = $endColor->toGray();

                    break;
            }
        }

        $this->eps .= "eoclip\n<<\n";

        if ($gradient->getType() === GradientType::RADIAL) {
            $this->eps .= " /ShadingType 3\n";
        } else {
            $this->eps .= " /ShadingType 2\n";
        }

        $this->eps .= " /Extend [ true true ]\n"
            ." /AntiAlias true\n";

        switch ($startColorType) {
            case Cmyk::class:
                $this->eps .= " /ColorSpace /DeviceCMYK\n";

                break;

            case Rgb::class:
                $this->eps .= " /ColorSpace /DeviceRGB\n";

                break;

            case Gray::class:
                $this->eps .= " /ColorSpace /DeviceGray\n";

                break;
        }

        switch ($gradient->getType()) {
            case GradientType::HORIZONTAL:
                $this->eps .= sprintf(
                    " /Coords [ %s %s %s %s ]\n",
                    round($x, self::PRECISION),
                    round($y, self::PRECISION),
                    round($x + $width, self::PRECISION),
                    round($y, self::PRECISION),
                );

                break;

            case GradientType::VERTICAL:
                $this->eps .= sprintf(
                    " /Coords [ %s %s %s %s ]\n",
                    round($x, self::PRECISION),
                    round($y, self::PRECISION),
                    round($x, self::PRECISION),
                    round($y + $height, self::PRECISION),
                );

                break;

            case GradientType::DIAGONAL:
                $this->eps .= sprintf(
                    " /Coords [ %s %s %s %s ]\n",
                    round($x, self::PRECISION),
                    round($y, self::PRECISION),
                    round($x + $width, self::PRECISION),
                    round($y + $height, self::PRECISION),
                );

                break;

            case GradientType::INVERSE_DIAGONAL:
                $this->eps .= sprintf(
                    " /Coords [ %s %s %s %s ]\n",
                    round($x, self::PRECISION),
                    round($y + $height, self::PRECISION),
                    round($x + $width, self::PRECISION),
                    round($y, self::PRECISION),
                );

                break;

            case GradientType::RADIAL:
                $centerX = ($x + $width) / 2;
                $centerY = ($y + $height) / 2;

                $this->eps .= sprintf(
                    " /Coords [ %s %s 0 %s %s %s ]\n",
                    round($centerX, self::PRECISION),
                    round($centerY, self::PRECISION),
                    round($centerX, self::PRECISION),
                    round($centerY, self::PRECISION),
                    round(max($width, $height) / 2, self::PRECISION),
                );

                break;
        }

        $this->eps .= " /Function\n"
            ." <<\n"
            ."  /FunctionType 2\n"
            ."  /Domain [ 0 1 ]\n"
            .sprintf("  /C0 [ %s ]\n", $this->getColorString($startColor))
            .sprintf("  /C1 [ %s ]\n", $this->getColorString($endColor))
            ."  /N 1\n"
            ." >>\n>>\nshfill\nQ\n";
    }

    /**
     * Return the PostScript color-setting command for the supplied color.
     */
    private function getColorSetString(ColorInterface $color): string
    {
        if ($color instanceof Rgb) {
            return $this->getColorString($color).' rgb';
        }

        if ($color instanceof Cmyk) {
            return $this->getColorString($color).' cmyk';
        }

        if ($color instanceof Gray) {
            return $this->getColorString($color).' gray';
        }

        return $this->getColorSetString($color->toCmyk());
    }

    /**
     * Return the numeric color components in the order PostScript expects.
     */
    private function getColorString(ColorInterface $color): string
    {
        if ($color instanceof Rgb) {
            return sprintf('%s %s %s', $color->getRed() / 255, $color->getGreen() / 255, $color->getBlue() / 255);
        }

        if ($color instanceof Cmyk) {
            return sprintf(
                '%s %s %s %s',
                $color->getCyan() / 100,
                $color->getMagenta() / 100,
                $color->getYellow() / 100,
                $color->getBlack() / 100,
            );
        }

        if ($color instanceof Gray) {
            return sprintf('%s', $color->getGray() / 100);
        }

        return $this->getColorString($color->toCmyk());
    }
}
