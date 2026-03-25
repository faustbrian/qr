<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Qr\Decoder\Common\Reedsolomon\ReedSolomonException;
use Cline\Qr\Decoder\InvalidArgumentException as DecoderInvalidArgumentException;
use Cline\Qr\Decoder\RuntimeException as DecoderRuntimeException;
use Cline\Qr\Exception\BlockSizeTooSmallException;
use Cline\Qr\Exception\InvalidArgumentException;
use Cline\Qr\Exception\InvalidValidationDataException;
use Cline\Qr\Exception\MissingValidationPackageException;
use Cline\Qr\Exception\RuntimeException;
use Cline\Qr\Exception\UnsupportedValidationWriterException;
use Cline\Qr\Generator\Internal\Exception\InvalidArgumentException as GeneratorInvalidArgumentException;
use Cline\Qr\Generator\Internal\Exception\OutOfBoundsException;
use Cline\Qr\Generator\Internal\Exception\RuntimeException as GeneratorRuntimeException;
use Cline\Qr\Generator\Internal\Exception\UnexpectedValueException;
use Cline\Qr\Generator\Internal\Exception\WriterException;

it('builds package exception instances from factory methods', function (): void {
    expect(BlockSizeTooSmallException::dueToDataDensity()->getMessage())
        ->toBe('Too much data: increase image dimensions or lower error correction level');

    expect(RuntimeException::withMessage('runtime failure'))
        ->toBeInstanceOf(RuntimeException::class)
        ->and(RuntimeException::withMessage('runtime failure')->getMessage())
        ->toBe('runtime failure');

    expect(InvalidArgumentException::withMessage('invalid argument')->getMessage())
        ->toBe('invalid argument');

    expect(UnsupportedValidationWriterException::forWriter('Writer')->getMessage())
        ->toBe('Unable to validate the result: "Writer" does not support validation');

    expect(MissingValidationPackageException::forPackage('vendor/package')->getMessage())
        ->toBe('Please install "vendor/package" or disable image validation');

    expect(
        InvalidValidationDataException::forExpectedAndActual('expected', 'actual')
            ->getMessage(),
    )->toContain('expected')
        ->toContain('actual');
});

it('builds decoder and generator exception instances from factory methods', function (): void {
    expect(DecoderInvalidArgumentException::withMessage('bad decode input')->getMessage())
        ->toBe('bad decode input');

    expect(DecoderRuntimeException::withMessage('decode runtime')->getMessage())
        ->toBe('decode runtime');

    expect(ReedSolomonException::withMessage('reed-solomon failed')->getMessage())
        ->toBe('reed-solomon failed');

    expect(GeneratorInvalidArgumentException::withMessage('bad generator input')->getMessage())
        ->toBe('bad generator input');

    expect(GeneratorRuntimeException::withMessage('generator runtime')->getMessage())
        ->toBe('generator runtime');

    expect(OutOfBoundsException::withMessage('out of bounds')->getMessage())
        ->toBe('out of bounds');

    expect(UnexpectedValueException::withMessage('unexpected value')->getMessage())
        ->toBe('unexpected value');

    expect(WriterException::withMessage('writer failed')->getMessage())
        ->toBe('writer failed');
});

it('supports throwable instances and class strings in throw helpers', function (): void {
    expect(fn (): null => throw_if(
        true,
        RuntimeException::withMessage('instance throw_if'),
    ))->toThrow(RuntimeException::class, 'instance throw_if');

    expect(fn (): null => throw_unless(
        false,
        InvalidArgumentException::withMessage('instance throw_unless'),
    ))->toThrow(InvalidArgumentException::class, 'instance throw_unless');

    expect(fn (): null => throw_if(
        true,
        RuntimeException::class,
        'class-string throw_if',
    ))->toThrow(RuntimeException::class, 'class-string throw_if');

    expect(fn (): null => throw_unless(
        false,
        InvalidArgumentException::class,
        'class-string throw_unless',
    ))->toThrow(InvalidArgumentException::class, 'class-string throw_unless');
});
