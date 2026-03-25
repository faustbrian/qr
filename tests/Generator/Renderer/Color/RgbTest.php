<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Qr\Generator\Renderer\Color\Cmyk;
use Cline\Qr\Generator\Renderer\Color\Gray;
use Cline\Qr\Generator\Renderer\Color\Rgb;

it('returns itself from toRgb', function (): void {
    $rgb = new Rgb(10, 20, 30);

    expect($rgb->toRgb())->toBe($rgb);
});

it('converts rgb to cmyk', function (): void {
    expect(
        new Rgb(0, 0, 0)->toCmyk(),
    )->toEqual(
        new Cmyk(0, 0, 0, 100),
    )
        ->and(
            new Rgb(255, 255, 255)->toCmyk(),
        )->toEqual(
            new Cmyk(0, 0, 0, 0),
        )
        ->and(
            new Rgb(255, 0, 0)->toCmyk(),
        )->toEqual(
            new Cmyk(0, 100, 100, 0),
        )
        ->and(
            new Rgb(100, 150, 200)->toCmyk(),
        )->toEqual(
            new Cmyk(50, 25, 0, 22),
        );
});

it('converts rgb to gray', function (): void {
    expect(
        new Rgb(0, 0, 0)->toGray(),
    )->toEqual(
        new Gray(0),
    )
        ->and(
            new Rgb(255, 255, 255)->toGray(),
        )->toEqual(
            new Gray(100),
        )
        ->and(
            new Rgb(255, 0, 0)->toGray(),
        )->toEqual(
            new Gray(21),
        )
        ->and(
            new Rgb(0, 255, 0)->toGray(),
        )->toEqual(
            new Gray(72),
        )
        ->and(
            new Rgb(100, 150, 200)->toGray(),
        )->toEqual(
            new Gray(56),
        );
});
