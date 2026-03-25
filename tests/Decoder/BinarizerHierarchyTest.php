<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Qr\Decoder\AbstractResultPoint;
use Cline\Qr\Decoder\Common\AbstractGlobalHistogramBinarizer;
use Cline\Qr\Decoder\Common\GlobalHistogramBinarizer;
use Cline\Qr\Decoder\Common\HybridBinarizer;
use Cline\Qr\Decoder\Qrcode\Detector\AlignmentPattern;
use Cline\Qr\Decoder\Qrcode\Detector\FinderPattern;
use Cline\Qr\Decoder\ResultPoint;

it('keeps the shared histogram layer abstract', function (): void {
    expect(
        new ReflectionClass(AbstractGlobalHistogramBinarizer::class)->isAbstract(),
    )->toBeTrue();
});

it('keeps the concrete binarizers final', function (): void {
    expect(
        new ReflectionClass(GlobalHistogramBinarizer::class)->isFinal(),
    )->toBeTrue()
        ->and(
            new ReflectionClass(HybridBinarizer::class)->isFinal(),
        )->toBeTrue();
});

it('keeps the shared result point layer abstract', function (): void {
    expect(
        new ReflectionClass(AbstractResultPoint::class)->isAbstract(),
    )->toBeTrue();
});

it('keeps the concrete result points final', function (): void {
    expect(
        new ReflectionClass(ResultPoint::class)->isFinal(),
    )->toBeTrue()
        ->and(
            new ReflectionClass(AlignmentPattern::class)->isFinal(),
        )->toBeTrue()
        ->and(
            new ReflectionClass(FinderPattern::class)->isFinal(),
        )->toBeTrue();
});
