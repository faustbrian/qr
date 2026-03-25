<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Qr\Generator\Internal\Exception\InvalidArgumentException;
use Cline\Qr\Generator\Renderer\Color\Percentage;

it('validates and exposes percentage behavior', function (): void {
    $percentage = new Percentage(25);

    expect($percentage->value())->toBe(25)
        ->and($percentage->asFraction())->toEqualWithDelta(0.25, \PHP_FLOAT_EPSILON)
        ->and($percentage->complement()->value())->toBe(75)
        ->and($percentage->equals(
            new Percentage(25),
        ))->toBeTrue()
        ->and($percentage->equals(
            new Percentage(20),
        ))->toBeFalse();
});

it('rejects percentages outside the supported range', function (): void {
    expect(static fn (): Percentage => new Percentage(101))
        ->toThrow(InvalidArgumentException::class, 'Percentage must be between 0 and 100');
});
