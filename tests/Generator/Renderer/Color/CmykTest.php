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

it('converts cmyk to rgb', function (): void {
    expect(
        new Cmyk(0, 0, 0, 100)->toRgb(),
    )->toEqual(
        new Rgb(0, 0, 0),
    )
        ->and(
            new Cmyk(0, 0, 0, 0)->toRgb(),
        )->toEqual(
            new Rgb(255, 255, 255),
        )
        ->and(
            new Cmyk(0, 0, 0, 50)->toRgb(),
        )->toEqual(
            new Rgb(128, 128, 128),
        )
        ->and(
            new Cmyk(10, 80, 70, 30)->toRgb(),
        )->toEqual(
            new Rgb(161, 36, 54),
        );
});

it('returns itself from toCmyk', function (): void {
    $cmyk = new Cmyk(10, 20, 30, 40);

    expect($cmyk->toCmyk())->toBe($cmyk);
});

it('converts cmyk to gray', function (): void {
    expect(
        new Cmyk(0, 0, 0, 0)->toGray(),
    )->toEqual(
        new Gray(100),
    )
        ->and(
            new Cmyk(0, 0, 0, 100)->toGray(),
        )->toEqual(
            new Gray(0),
        )
        ->and(
            new Cmyk(0, 0, 0, 50)->toGray(),
        )->toEqual(
            new Gray(50),
        );
});
