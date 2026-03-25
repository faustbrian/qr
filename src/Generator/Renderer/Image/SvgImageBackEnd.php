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
use Cline\Qr\Generator\Renderer\Color\ColorInterface;
use Cline\Qr\Generator\Renderer\Path\Close;
use Cline\Qr\Generator\Renderer\Path\Curve;
use Cline\Qr\Generator\Renderer\Path\EllipticArc;
use Cline\Qr\Generator\Renderer\Path\Line;
use Cline\Qr\Generator\Renderer\Path\Move;
use Cline\Qr\Generator\Renderer\Path\Path;
use Cline\Qr\Generator\Renderer\RendererStyle\Gradient;
use Cline\Qr\Generator\Renderer\RendererStyle\GradientType;
use XMLWriter;

use function array_pop;
use function class_exists;
use function hash;
use function implode;
use function max;
use function round;
use function sprintf;

/**
 * SVG back end that serializes paths and gradients with `XMLWriter`.
 *
 * The backend keeps track of nested transform groups so renderers can push and
 * pop temporary eye-local transforms while still producing valid, compact SVG
 * markup.
 * @author Brian Faust <brian@cline.sh>
 */
final class SvgImageBackEnd implements ImageBackEndInterface
{
    private const int PRECISION = 3;

    private ?XMLWriter $xmlWriter;

    private ?array $stack;

    private ?int $currentStack;

    private ?int $gradientCount;

    public function __construct()
    {
        if (!class_exists(XMLWriter::class)) {
            throw RuntimeException::withMessage('You need to install the libxml extension to use this back end');
        }
    }

    /**
     * Start a new SVG document and paint the optional background rectangle.
     */
    public function new(int $size, ColorInterface $backgroundColor): void
    {
        $this->xmlWriter = new XMLWriter();
        $this->xmlWriter->openMemory();

        $this->xmlWriter->startDocument('1.0', 'UTF-8');
        $this->xmlWriter->startElement('svg');
        $this->xmlWriter->writeAttribute('xmlns', 'http://www.w3.org/2000/svg');
        $this->xmlWriter->writeAttribute('version', '1.1');
        $this->xmlWriter->writeAttribute('width', (string) $size);
        $this->xmlWriter->writeAttribute('height', (string) $size);
        $this->xmlWriter->writeAttribute('viewBox', '0 0 '.$size.' '.$size);

        $this->gradientCount = 0;
        $this->currentStack = 0;
        $this->stack[0] = 0;

        $alpha = 1;

        if ($backgroundColor instanceof Alpha) {
            $alpha = $backgroundColor->getAlpha() / 100;
        }

        if (0 === $alpha) {
            return;
        }

        $this->xmlWriter->startElement('rect');
        $this->xmlWriter->writeAttribute('x', '0');
        $this->xmlWriter->writeAttribute('y', '0');
        $this->xmlWriter->writeAttribute('width', (string) $size);
        $this->xmlWriter->writeAttribute('height', (string) $size);
        $this->xmlWriter->writeAttribute('fill', $this->getColorString($backgroundColor));

        if ($alpha < 1) {
            $this->xmlWriter->writeAttribute('fill-opacity', (string) $alpha);
        }

        $this->xmlWriter->endElement();
    }

    /**
     * Open a transform group that scales subsequent drawing operations.
     */
    public function scale(float $size): void
    {
        if (null === $this->xmlWriter) {
            throw RuntimeException::withMessage('No image has been started');
        }

        $this->xmlWriter->startElement('g');
        $this->xmlWriter->writeAttribute(
            'transform',
            sprintf('scale(%s)', round($size, self::PRECISION)),
        );
        ++$this->stack[$this->currentStack];
    }

    /**
     * Open a transform group that translates subsequent drawing operations.
     */
    public function translate(float $x, float $y): void
    {
        if (null === $this->xmlWriter) {
            throw RuntimeException::withMessage('No image has been started');
        }

        $this->xmlWriter->startElement('g');
        $this->xmlWriter->writeAttribute(
            'transform',
            sprintf('translate(%s,%s)', round($x, self::PRECISION), round($y, self::PRECISION)),
        );
        ++$this->stack[$this->currentStack];
    }

    /**
     * Open a transform group that rotates subsequent drawing operations.
     */
    public function rotate(int $degrees): void
    {
        if (null === $this->xmlWriter) {
            throw RuntimeException::withMessage('No image has been started');
        }

        $this->xmlWriter->startElement('g');
        $this->xmlWriter->writeAttribute('transform', sprintf('rotate(%d)', $degrees));
        ++$this->stack[$this->currentStack];
    }

    /**
     * Push a new transform-scope group.
     */
    public function push(): void
    {
        if (null === $this->xmlWriter) {
            throw RuntimeException::withMessage('No image has been started');
        }

        $this->xmlWriter->startElement('g');
        $this->stack[] = 1;
        ++$this->currentStack;
    }

    /**
     * Close the current transform-scope group and restore its parent.
     */
    public function pop(): void
    {
        if (null === $this->xmlWriter) {
            throw RuntimeException::withMessage('No image has been started');
        }

        for ($i = 0; $i < $this->stack[$this->currentStack]; ++$i) {
            $this->xmlWriter->endElement();
        }

        array_pop($this->stack);
        --$this->currentStack;
    }

    /**
     * Emit a filled `<path>` element with a flat color.
     */
    public function drawPathWithColor(Path $path, ColorInterface $color): void
    {
        if (null === $this->xmlWriter) {
            throw RuntimeException::withMessage('No image has been started');
        }

        $alpha = 1;

        if ($color instanceof Alpha) {
            $alpha = $color->getAlpha() / 100;
        }

        $this->startPathElement($path);
        $this->xmlWriter->writeAttribute('fill', $this->getColorString($color));

        if ($alpha < 1) {
            $this->xmlWriter->writeAttribute('fill-opacity', (string) $alpha);
        }

        $this->xmlWriter->endElement();
    }

    /**
     * Emit a filled `<path>` element that references a generated gradient.
     */
    public function drawPathWithGradient(
        Path $path,
        Gradient $gradient,
        float $x,
        float $y,
        float $width,
        float $height,
    ): void {
        if (null === $this->xmlWriter) {
            throw RuntimeException::withMessage('No image has been started');
        }

        $gradientId = $this->createGradientFill($gradient, $x, $y, $width, $height);
        $this->startPathElement($path);
        $this->xmlWriter->writeAttribute('fill', 'url(#'.$gradientId.')');
        $this->xmlWriter->endElement();
    }

    /**
     * Close all open groups and return the serialized SVG document.
     */
    public function done(): string
    {
        if (null === $this->xmlWriter) {
            throw RuntimeException::withMessage('No image has been started');
        }

        foreach ($this->stack as $openElements) {
            for ($i = $openElements; $i > 0; --$i) {
                $this->xmlWriter->endElement();
            }
        }

        $this->xmlWriter->endDocument();
        $blob = $this->xmlWriter->outputMemory(true);
        $this->xmlWriter = null;
        $this->stack = null;
        $this->currentStack = null;
        $this->gradientCount = null;

        return $blob;
    }

    /**
     * Start a `<path>` element and serialize the path commands into its `d`
     * attribute.
     */
    private function startPathElement(Path $path): void
    {
        $pathData = [];

        foreach ($path as $op) {
            switch (true) {
                case $op instanceof Move:
                    $pathData[] = sprintf(
                        'M%s %s',
                        round($op->getX(), self::PRECISION),
                        round($op->getY(), self::PRECISION),
                    );

                    break;

                case $op instanceof Line:
                    $pathData[] = sprintf(
                        'L%s %s',
                        round($op->getX(), self::PRECISION),
                        round($op->getY(), self::PRECISION),
                    );

                    break;

                case $op instanceof EllipticArc:
                    $pathData[] = sprintf(
                        'A%s %s %s %u %u %s %s',
                        round($op->getXRadius(), self::PRECISION),
                        round($op->getYRadius(), self::PRECISION),
                        round($op->getXAxisAngle(), self::PRECISION),
                        $op->isLargeArc(),
                        $op->isSweep(),
                        round($op->getX(), self::PRECISION),
                        round($op->getY(), self::PRECISION),
                    );

                    break;

                case $op instanceof Curve:
                    $pathData[] = sprintf(
                        'C%s %s %s %s %s %s',
                        round($op->getX1(), self::PRECISION),
                        round($op->getY1(), self::PRECISION),
                        round($op->getX2(), self::PRECISION),
                        round($op->getY2(), self::PRECISION),
                        round($op->getX3(), self::PRECISION),
                        round($op->getY3(), self::PRECISION),
                    );

                    break;

                case $op instanceof Close:
                    $pathData[] = 'Z';

                    break;

                default:
                    throw RuntimeException::withMessage('Unexpected draw operation: '.$op::class);
            }
        }

        $this->xmlWriter->startElement('path');
        $this->xmlWriter->writeAttribute('fill-rule', 'evenodd');
        $this->xmlWriter->writeAttribute('d', implode('', $pathData));
    }

    /**
     * Define an SVG gradient element and return its generated identifier.
     */
    private function createGradientFill(Gradient $gradient, float $x, float $y, float $width, float $height): string
    {
        $this->xmlWriter->startElement('defs');

        $startColor = $gradient->getStartColor();
        $endColor = $gradient->getEndColor();

        if ($gradient->getType() === GradientType::RADIAL) {
            $this->xmlWriter->startElement('radialGradient');
        } else {
            $this->xmlWriter->startElement('linearGradient');
        }

        $this->xmlWriter->writeAttribute('gradientUnits', 'userSpaceOnUse');

        switch ($gradient->getType()) {
            case GradientType::HORIZONTAL:
                $this->xmlWriter->writeAttribute('x1', (string) round($x, self::PRECISION));
                $this->xmlWriter->writeAttribute('y1', (string) round($y, self::PRECISION));
                $this->xmlWriter->writeAttribute('x2', (string) round($x + $width, self::PRECISION));
                $this->xmlWriter->writeAttribute('y2', (string) round($y, self::PRECISION));

                break;

            case GradientType::VERTICAL:
                $this->xmlWriter->writeAttribute('x1', (string) round($x, self::PRECISION));
                $this->xmlWriter->writeAttribute('y1', (string) round($y, self::PRECISION));
                $this->xmlWriter->writeAttribute('x2', (string) round($x, self::PRECISION));
                $this->xmlWriter->writeAttribute('y2', (string) round($y + $height, self::PRECISION));

                break;

            case GradientType::DIAGONAL:
                $this->xmlWriter->writeAttribute('x1', (string) round($x, self::PRECISION));
                $this->xmlWriter->writeAttribute('y1', (string) round($y, self::PRECISION));
                $this->xmlWriter->writeAttribute('x2', (string) round($x + $width, self::PRECISION));
                $this->xmlWriter->writeAttribute('y2', (string) round($y + $height, self::PRECISION));

                break;

            case GradientType::INVERSE_DIAGONAL:
                $this->xmlWriter->writeAttribute('x1', (string) round($x, self::PRECISION));
                $this->xmlWriter->writeAttribute('y1', (string) round($y + $height, self::PRECISION));
                $this->xmlWriter->writeAttribute('x2', (string) round($x + $width, self::PRECISION));
                $this->xmlWriter->writeAttribute('y2', (string) round($y, self::PRECISION));

                break;

            case GradientType::RADIAL:
                $this->xmlWriter->writeAttribute('cx', (string) round(($x + $width) / 2, self::PRECISION));
                $this->xmlWriter->writeAttribute('cy', (string) round(($y + $height) / 2, self::PRECISION));
                $this->xmlWriter->writeAttribute('r', (string) round(max($width, $height) / 2, self::PRECISION));

                break;
        }

        $toBeHashed = $this->getColorString($startColor)
            .$this->getColorString($endColor)
            .$gradient->getType()->name;

        if ($startColor instanceof Alpha) {
            $toBeHashed .= (string) $startColor->getAlpha();
        }
        $id = sprintf('g%d-%s', ++$this->gradientCount, hash('xxh64', $toBeHashed));
        $this->xmlWriter->writeAttribute('id', $id);

        $this->xmlWriter->startElement('stop');
        $this->xmlWriter->writeAttribute('offset', '0%');
        $this->xmlWriter->writeAttribute('stop-color', $this->getColorString($startColor));

        if ($startColor instanceof Alpha) {
            $this->xmlWriter->writeAttribute('stop-opacity', (string) $startColor->getAlpha());
        }

        $this->xmlWriter->endElement();

        $this->xmlWriter->startElement('stop');
        $this->xmlWriter->writeAttribute('offset', '100%');
        $this->xmlWriter->writeAttribute('stop-color', $this->getColorString($endColor));

        if ($endColor instanceof Alpha) {
            $this->xmlWriter->writeAttribute('stop-opacity', (string) $endColor->getAlpha());
        }

        $this->xmlWriter->endElement();

        $this->xmlWriter->endElement();
        $this->xmlWriter->endElement();

        return $id;
    }

    /**
     * Convert a renderer color into an RGB CSS hex string.
     */
    private function getColorString(ColorInterface $color): string
    {
        $color = $color->toRgb();

        return sprintf(
            '#%02x%02x%02x',
            $color->getRed(),
            $color->getGreen(),
            $color->getBlue(),
        );
    }
}
