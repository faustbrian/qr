<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Qr\Builder\Builder;
use Cline\Qr\Color\Color;
use Cline\Qr\Encoding\Encoding;
use Cline\Qr\ErrorCorrectionLevel;
use Cline\Qr\Generator\MatrixFactory;
use Cline\Qr\Label\Label;
use Cline\Qr\Logo\Logo;
use Cline\Qr\Matrix\MatrixInterface;
use Cline\Qr\QrCode;
use Cline\Qr\RoundBlockSizeMode;
use Cline\Qr\Writer\BinaryWriter;
use Cline\Qr\Writer\ConsoleWriter;
use Cline\Qr\Writer\DebugWriter;
use Cline\Qr\Writer\EpsWriter;
use Cline\Qr\Writer\GifWriter;
use Cline\Qr\Writer\PdfWriter;
use Cline\Qr\Writer\PngWriter;
use Cline\Qr\Writer\Result\BinaryResult;
use Cline\Qr\Writer\Result\ConsoleResult;
use Cline\Qr\Writer\Result\DebugResult;
use Cline\Qr\Writer\Result\EpsResult;
use Cline\Qr\Writer\Result\GifResult;
use Cline\Qr\Writer\Result\PdfResult;
use Cline\Qr\Writer\Result\PngResult;
use Cline\Qr\Writer\Result\ResultInterface;
use Cline\Qr\Writer\Result\SvgResult;
use Cline\Qr\Writer\Result\WebPResult;
use Cline\Qr\Writer\SvgWriter;
use Cline\Qr\Writer\ValidatingWriterInterface;
use Cline\Qr\Writer\WebPWriter;
use Cline\Qr\Writer\WriterInterface;

it('writes each writer result with the expected mime type', function (
    WriterInterface $writer,
    string $resultClass,
    string $contentType,
): void {
    $qrCode = new QrCode(
        data: 'Data',
        encoding: new Encoding('UTF-8'),
        errorCorrectionLevel: ErrorCorrectionLevel::Low,
        size: 300,
        margin: 10,
        roundBlockSizeMode: RoundBlockSizeMode::Margin,
        foregroundColor: new Color(0, 0, 0),
        backgroundColor: new Color(255, 255, 255),
    );

    $logo = new Logo(
        path: __DIR__.'/assets/bender.png',
        resizeToWidth: 50,
    );

    $label = new Label(
        text: 'Label',
        textColor: new Color(255, 0, 0),
    );

    $result = $writer->write($qrCode, $logo, $label);

    expect($result->getMatrix())->toBeInstanceOf(MatrixInterface::class);

    if ($writer instanceof ValidatingWriterInterface) {
        $writer->validateResult($result, $qrCode->getData());
    }

    expect($result)->toBeInstanceOf($resultClass)
        ->and($result->getMimeType())->toBe($contentType)
        ->and($result->getDataUri())->toContain(
            'data:'.$result->getMimeType().';base64,',
        );
})->with([
    [new BinaryWriter(), BinaryResult::class, 'text/plain'],
    [new ConsoleWriter(), ConsoleResult::class, 'text/plain'],
    [new DebugWriter(), DebugResult::class, 'text/plain'],
    [new EpsWriter(), EpsResult::class, 'image/eps'],
    [new GifWriter(), GifResult::class, 'image/gif'],
    [new PdfWriter(), PdfResult::class, 'application/pdf'],
    [new PngWriter(), PngResult::class, 'image/png'],
    [new SvgWriter(), SvgResult::class, 'image/svg+xml'],
    [new WebPWriter(), WebPResult::class, 'image/webp'],
]);

it('handles size and margin correctly', function (): void {
    $builder = new Builder(
        data: 'QR Code',
        size: 400,
        margin: 15,
    );

    $result = $builder->build();
    $image = imagecreatefromstring($result->getString());

    assert($image instanceof GdImage);

    expect(imagesx($image))->toBe(430)
        ->and(imagesy($image))->toBe(430);
});

it('handles size and margin correctly with rounded blocks', function (
    int $size,
    int $margin,
    RoundBlockSizeMode $roundBlockSizeMode,
    int $expectedSize,
): void {
    $builder = new Builder(
        data: 'QR Code contents with some length to have some data',
        size: $size,
        margin: $margin,
        roundBlockSizeMode: $roundBlockSizeMode,
    );

    $result = $builder->build();
    $image = imagecreatefromstring($result->getString());

    assert($image instanceof GdImage);

    expect(imagesx($image))->toBe($expectedSize)
        ->and(imagesy($image))->toBe($expectedSize);
})->with([
    [400, 0, RoundBlockSizeMode::Enlarge, 406],
    [400, 5, RoundBlockSizeMode::Enlarge, 416],
    [400, 0, RoundBlockSizeMode::Margin, 400],
    [400, 5, RoundBlockSizeMode::Margin, 410],
    [400, 0, RoundBlockSizeMode::Shrink, 377],
    [400, 5, RoundBlockSizeMode::Shrink, 387],
]);

it('throws for an invalid logo path', function (): void {
    $writer = new SvgWriter();
    $qrCode = new QrCode('QR Code');
    $logo = new Logo('/my/invalid/path.png');

    expect(fn (): ResultInterface => $writer->write($qrCode, $logo))
        ->toThrow(
            Exception::class,
            'Could not read logo image data from path "/my/invalid/path.png"',
        );
});

it('throws for invalid logo data', function (): void {
    $writer = new SvgWriter();
    $qrCode = new QrCode('QR Code');
    $logo = new Logo(__DIR__.'/QrCodeTest.php');

    expect(fn (): ResultInterface => $writer->write($qrCode, $logo))
        ->toThrow(Exception::class, 'Logo path is not an image');
});

it('can save a result to a file', function (): void {
    $path = __DIR__.'/test-save-to-file.png';

    try {
        $writer = new PngWriter();
        $qrCode = new QrCode('QR Code');
        $writer->write($qrCode)->saveToFile($path);

        $contents = file_get_contents($path);

        expect($contents)->not->toBeFalse();

        $image = imagecreatefromstring((string) $contents);

        expect($image)->toBeInstanceOf(GdImage::class);
    } finally {
        if (file_exists($path)) {
            unlink($path);
        }
    }
});

it('rejects label line breaks', function (): void {
    $qrCode = new QrCode('QR Code');
    $label = new Label("this\none has\nline breaks in it");
    $writer = new PngWriter();

    expect(fn (): ResultInterface => $writer->write($qrCode, null, $label))
        ->toThrow(Exception::class, 'Label does not support line breaks');
});

it('rejects a block size below one', function (): void {
    $qrCode = new QrCode(
        data: str_repeat('alot', 100),
        size: 10,
    );

    $matrixFactory = new MatrixFactory();

    expect(fn (): MatrixInterface => $matrixFactory->create($qrCode))
        ->toThrow(
            Exception::class,
            'Too much data: increase image dimensions or lower error correction level',
        );
});

it('accepts SVG logos only for the SVG writer', function (): void {
    $qrCode = new QrCode('QR Code');
    $logo = new Logo(
        path: __DIR__.'/assets/bender.svg',
        resizeToWidth: 100,
        resizeToHeight: 50,
    );

    $svgWriter = new SvgWriter();
    $result = $svgWriter->write($qrCode, $logo);

    expect($result)->toBeInstanceOf(SvgResult::class);

    $pngWriter = new PngWriter();

    expect(fn (): ResultInterface => $pngWriter->write($qrCode, $logo))
        ->toThrow(Exception::class, 'GD Writer does not support SVG logo');
});

it('writes both compact and non-compact SVG output', function (): void {
    $qrCode = new QrCode('QR Code');

    $compactResult = new SvgWriter()->write(
        qrCode: $qrCode,
        options: [SvgWriter::WRITER_OPTION_COMPACT => true],
    );

    $expandedResult = new SvgWriter()->write(
        qrCode: $qrCode,
        options: [SvgWriter::WRITER_OPTION_COMPACT => false],
    );

    expect($compactResult)->toBeInstanceOf(SvgResult::class)
        ->and($expandedResult)->toBeInstanceOf(SvgResult::class);
});

it('limits logo punchout background support to GD writers', function (): void {
    $qrCode = new QrCode('QR Code');
    $logo = new Logo(
        path: __DIR__.'/assets/bender.svg',
        resizeToWidth: 100,
        punchoutBackground: true,
    );

    $svgWriter = new SvgWriter();

    expect(fn (): ResultInterface => $svgWriter->write($qrCode, $logo))
        ->toThrow(
            Exception::class,
            'The SVG writer does not support logo punchout background',
        );
});
