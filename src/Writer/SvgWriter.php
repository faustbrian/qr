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
use Cline\Qr\ImageData\LogoImageData;
use Cline\Qr\Label\LabelInterface;
use Cline\Qr\Logo\LogoInterface;
use Cline\Qr\Matrix\MatrixInterface;
use Cline\Qr\QrCodeInterface;
use Cline\Qr\Writer\Result\ResultInterface;
use Cline\Qr\Writer\Result\SvgResult;
use SimpleXMLElement;

use function array_key_exists;
use function is_scalar;
use function mb_rtrim;
use function number_format;
use function sprintf;
use function throw_if;

/**
 * Writer that serializes the matrix as SVG markup.
 *
 * The writer can emit either one compact `<path>` or repeated `<use>`
 * references to a block definition. It also supports embedding logos as data
 * URIs inside the final SVG document.
 *
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class SvgWriter implements WriterInterface
{
    public const int DECIMAL_PRECISION = 2;

    public const string WRITER_OPTION_COMPACT = 'compact';

    public const string WRITER_OPTION_BLOCK_ID = 'block_id';

    public const string WRITER_OPTION_EXCLUDE_XML_DECLARATION = 'exclude_xml_declaration';

    public const string WRITER_OPTION_EXCLUDE_SVG_WIDTH_AND_HEIGHT = 'exclude_svg_width_and_height';

    public const string WRITER_OPTION_FORCE_XLINK_HREF = 'force_xlink_href';

    /**
     * Render the QR code as SVG, optionally embedding a logo and applying
     * writer-specific SVG options.
     */
    public function write(QrCodeInterface $qrCode, ?LogoInterface $logo = null, ?LabelInterface $label = null, array $options = []): ResultInterface
    {
        if (!isset($options[self::WRITER_OPTION_COMPACT])) {
            $options[self::WRITER_OPTION_COMPACT] = true;
        }

        if (!isset($options[self::WRITER_OPTION_BLOCK_ID])) {
            $options[self::WRITER_OPTION_BLOCK_ID] = 'block';
        }

        if (!isset($options[self::WRITER_OPTION_EXCLUDE_XML_DECLARATION])) {
            $options[self::WRITER_OPTION_EXCLUDE_XML_DECLARATION] = false;
        }

        if (!isset($options[self::WRITER_OPTION_EXCLUDE_SVG_WIDTH_AND_HEIGHT])) {
            $options[self::WRITER_OPTION_EXCLUDE_SVG_WIDTH_AND_HEIGHT] = false;
        }

        $matrixFactory = new MatrixFactory();
        $matrix = $matrixFactory->create($qrCode);

        $xml = new SimpleXMLElement('<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"/>');
        $xml->addAttribute('version', '1.1');

        if (!$options[self::WRITER_OPTION_EXCLUDE_SVG_WIDTH_AND_HEIGHT]) {
            $xml->addAttribute('width', $matrix->getOuterSize().'px');
            $xml->addAttribute('height', $matrix->getOuterSize().'px');
        }

        $xml->addAttribute('viewBox', '0 0 '.$matrix->getOuterSize().' '.$matrix->getOuterSize());

        $background = $xml->addChild('rect');
        $background->addAttribute('x', '0');
        $background->addAttribute('y', '0');
        $background->addAttribute('width', (string) $matrix->getOuterSize());
        $background->addAttribute('height', (string) $matrix->getOuterSize());
        $background->addAttribute('fill', '#'.sprintf('%02x%02x%02x', $qrCode->getBackgroundColor()->getRed(), $qrCode->getBackgroundColor()->getGreen(), $qrCode->getBackgroundColor()->getBlue()));
        $background->addAttribute('fill-opacity', (string) $qrCode->getBackgroundColor()->getOpacity());

        if ($options[self::WRITER_OPTION_COMPACT]) {
            $this->writePath($xml, $qrCode, $matrix);
        } else {
            $this->writeBlockDefinitions($xml, $qrCode, $matrix, $options);
        }

        $result = new SvgResult($matrix, $xml, (bool) $options[self::WRITER_OPTION_EXCLUDE_XML_DECLARATION]);

        if ($logo instanceof LogoInterface) {
            $this->addLogo($logo, $result, $options);
        }

        return $result;
    }

    /**
     * Read a scalar writer option as a string.
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
     * Write all dark modules into one compact SVG path.
     */
    private function writePath(SimpleXMLElement $xml, QrCodeInterface $qrCode, MatrixInterface $matrix): void
    {
        $path = '';

        for ($rowIndex = 0; $rowIndex < $matrix->getBlockCount(); ++$rowIndex) {
            $left = $matrix->getMarginLeft();

            for ($columnIndex = 0; $columnIndex < $matrix->getBlockCount(); ++$columnIndex) {
                if (1 !== $matrix->getBlockValue($rowIndex, $columnIndex)) {
                    continue;
                }

                // When we are at the first column or when the previous column was 0 set new left
                if (0 === $columnIndex || 0 === $matrix->getBlockValue($rowIndex, $columnIndex - 1)) {
                    $left = $matrix->getMarginLeft() + $matrix->getBlockSize() * $columnIndex;
                }

                // When we are at the
                if ($columnIndex !== $matrix->getBlockCount() - 1 && 0 !== $matrix->getBlockValue($rowIndex, $columnIndex + 1)) {
                    continue;
                }

                $top = $matrix->getMarginLeft() + $matrix->getBlockSize() * $rowIndex;
                $bottom = $matrix->getMarginLeft() + $matrix->getBlockSize() * ($rowIndex + 1);
                $right = $matrix->getMarginLeft() + $matrix->getBlockSize() * ($columnIndex + 1);
                $path .= 'M'.$this->formatNumber($left).','.$this->formatNumber($top);
                $path .= 'L'.$this->formatNumber($right).','.$this->formatNumber($top);
                $path .= 'L'.$this->formatNumber($right).','.$this->formatNumber($bottom);
                $path .= 'L'.$this->formatNumber($left).','.$this->formatNumber($bottom).'Z';
            }
        }

        $pathDefinition = $xml->addChild('path');
        $pathDefinition->addAttribute('fill', '#'.sprintf('%02x%02x%02x', $qrCode->getForegroundColor()->getRed(), $qrCode->getForegroundColor()->getGreen(), $qrCode->getForegroundColor()->getBlue()));
        $pathDefinition->addAttribute('fill-opacity', (string) $qrCode->getForegroundColor()->getOpacity());
        $pathDefinition->addAttribute('d', $path);
    }

    /**
     * Write one reusable block definition and reference it for every dark
     * module.
     *
     * @param array<string, mixed> $options
     */
    private function writeBlockDefinitions(SimpleXMLElement $xml, QrCodeInterface $qrCode, MatrixInterface $matrix, array $options): void
    {
        $xml->addChild('defs');

        $blockDefinition = $xml->defs->addChild('rect');
        $blockId = $this->stringOption($options, self::WRITER_OPTION_BLOCK_ID, 'block');

        $blockDefinition->addAttribute('id', $blockId);
        $blockDefinition->addAttribute('width', $this->formatNumber($matrix->getBlockSize()));
        $blockDefinition->addAttribute('height', $this->formatNumber($matrix->getBlockSize()));
        $blockDefinition->addAttribute('fill', '#'.sprintf('%02x%02x%02x', $qrCode->getForegroundColor()->getRed(), $qrCode->getForegroundColor()->getGreen(), $qrCode->getForegroundColor()->getBlue()));
        $blockDefinition->addAttribute('fill-opacity', (string) $qrCode->getForegroundColor()->getOpacity());

        for ($rowIndex = 0; $rowIndex < $matrix->getBlockCount(); ++$rowIndex) {
            for ($columnIndex = 0; $columnIndex < $matrix->getBlockCount(); ++$columnIndex) {
                if (1 !== $matrix->getBlockValue($rowIndex, $columnIndex)) {
                    continue;
                }

                $block = $xml->addChild('use');
                $block->addAttribute('x', $this->formatNumber($matrix->getMarginLeft() + $matrix->getBlockSize() * $columnIndex));
                $block->addAttribute('y', $this->formatNumber($matrix->getMarginLeft() + $matrix->getBlockSize() * $rowIndex));
                $block->addAttribute('xlink:href', '#'.$blockId, 'http://www.w3.org/1999/xlink');
            }
        }
    }

    /**
     * Embed the configured logo as an SVG `<image>` element.
     *
     * @param array<string, mixed> $options
     */
    private function addLogo(LogoInterface $logo, SvgResult $result, array $options): void
    {
        throw_if(
            $logo->getPunchoutBackground(),
            RuntimeException::withMessage(
                'The SVG writer does not support logo punchout background',
            ),
        );

        $logoImageData = LogoImageData::createForLogo($logo);

        if (!isset($options[self::WRITER_OPTION_FORCE_XLINK_HREF])) {
            $options[self::WRITER_OPTION_FORCE_XLINK_HREF] = false;
        }

        $xml = $result->getXml();

        /** @var SimpleXMLElement $xmlAttributes */
        $xmlAttributes = $xml->attributes();

        $x = (int) $xmlAttributes->width / 2 - $logoImageData->getWidth() / 2;
        $y = (int) $xmlAttributes->height / 2 - $logoImageData->getHeight() / 2;

        $imageDefinition = $xml->addChild('image');
        $imageDefinition->addAttribute('x', (string) $x);
        $imageDefinition->addAttribute('y', (string) $y);
        $imageDefinition->addAttribute('width', (string) $logoImageData->getWidth());
        $imageDefinition->addAttribute('height', (string) $logoImageData->getHeight());
        $imageDefinition->addAttribute('preserveAspectRatio', 'none');

        if ($options[self::WRITER_OPTION_FORCE_XLINK_HREF]) {
            $imageDefinition->addAttribute('xlink:href', $logoImageData->createDataUri(), 'http://www.w3.org/1999/xlink');
        } else {
            $imageDefinition->addAttribute('href', $logoImageData->createDataUri());
        }
    }

    /**
     * Format a floating-point coordinate for SVG output.
     */
    private function formatNumber(float $number): string
    {
        $string = number_format($number, self::DECIMAL_PRECISION, '.', '');
        $string = mb_rtrim($string, '0');

        return mb_rtrim($string, '.');
    }
}
