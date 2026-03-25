<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Qr\Builder\Builder;
use Cline\Qr\Encoding\Encoding;
use Cline\Qr\ErrorCorrectionLevel;
use Cline\Qr\Label\Font\OpenSans;
use Cline\Qr\Label\LabelAlignment;
use Cline\Qr\RoundBlockSizeMode;
use Cline\Qr\Writer\PngWriter;
use Cline\Qr\Writer\Result\PngResult;
use Cline\Qr\Writer\SvgWriter;

it('writes advanced examples via the builder', function (): void {
    $builder = new Builder(
        writer: new PngWriter(),
        writerOptions: [],
        data: 'Custom QR code contents',
        encoding: new Encoding('UTF-8'),
        errorCorrectionLevel: ErrorCorrectionLevel::High,
        size: 300,
        margin: 10,
        roundBlockSizeMode: RoundBlockSizeMode::Margin,
        labelText: 'This is the label',
        labelFont: new OpenSans(20),
        labelAlignment: LabelAlignment::Center,
    );
    $result = $builder->build();

    expect($result)->toBeInstanceOf(PngResult::class)
        ->and($result->getMimeType())->toBe('image/png');
});

it('allows builder defaults to be overridden', function (): void {
    $builder = new Builder(
        writer: new SvgWriter(),
    );

    $result = $builder->build(
        writer: new PngWriter(),
    );

    expect($result)->toBeInstanceOf(PngResult::class)
        ->and($result->getMimeType())->toBe('image/png');
});
