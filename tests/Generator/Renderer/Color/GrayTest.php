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

it('converts gray to rgb', function (): void {
    expect(
        new Gray(0)->toRgb(),
    )->toEqual(
        new Rgb(0, 0, 0),
    )
        ->and(
            new Gray(100)->toRgb(),
        )->toEqual(
            new Rgb(255, 255, 255),
        )
        ->and(
            new Gray(50)->toRgb(),
        )->toEqual(
            new Rgb(128, 128, 128),
        )
        ->and(
            new Gray(90)->toRgb(),
        )->toEqual(
            new Rgb(230, 230, 230),
        );
});

it('converts gray to cmyk', function (): void {
    expect(
        new Gray(0)->toCmyk(),
    )->toEqual(
        new Cmyk(0, 0, 0, 100),
    )
        ->and(
            new Gray(100)->toCmyk(),
        )->toEqual(
            new Cmyk(0, 0, 0, 0),
        )
        ->and(
            new Gray(50)->toCmyk(),
        )->toEqual(
            new Cmyk(0, 0, 0, 50),
        );
});

it('returns itself from toGray', function (): void {
    $gray = new Gray(75);

    expect($gray->toGray())->toBe($gray);
});
