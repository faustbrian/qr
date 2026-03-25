<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Qr\Generator\Internal\Exception\InvalidArgumentException;
use Cline\Qr\Generator\Renderer\Color\Alpha;
use Cline\Qr\Generator\Renderer\Color\Gray;
use Cline\Qr\Generator\Renderer\Color\Rgb;

it('wraps a base color and preserves alpha', function (): void {
    $baseColor = new Gray(35);
    $alpha = new Alpha(25, $baseColor);

    expect($alpha->getAlpha())->toBe(25)
        ->and($alpha->getBaseColor())->toBe($baseColor)
        ->and($alpha->toRgb())->toEqual(
            new Rgb(89, 89, 89),
        );
});

it('rejects alpha outside the supported range', function (): void {
    expect(static fn (): Alpha => new Alpha(-1, new Rgb(0, 0, 0)))
        ->toThrow(InvalidArgumentException::class, 'Percentage must be between 0 and 100');
});
