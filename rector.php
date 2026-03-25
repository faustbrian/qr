<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
use Cline\CodingStandard\Rector\Factory;
use Rector\DeadCode\Rector\Stmt\RemoveUnreachableStatementRector;
use Rector\CodingStyle\Rector\ClassLike\NewlineBetweenClassLikeStmtsRector;
use Rector\Php84\Rector\Param\ExplicitNullableParamTypeRector;
use Rector\Privatization\Rector\Property\PrivatizeFinalClassPropertyRector;
use RectorLaravel\Rector\If_\ThrowIfRector;

return Factory::create(
    paths: [
        __DIR__.'/src/Builder',
        __DIR__.'/src/Color',
        __DIR__.'/src/Encoding',
        __DIR__.'/src/Exception',
        __DIR__.'/src/Generator/ErrorCorrectionLevelConverter.php',
        __DIR__.'/src/Generator/MatrixFactory.php',
        __DIR__.'/src/ImageData',
        __DIR__.'/src/Label',
        __DIR__.'/src/Logo',
        __DIR__.'/src/Matrix',
        __DIR__.'/src/Writer',
        __DIR__.'/src/ErrorCorrectionLevel.php',
        __DIR__.'/src/QrCode.php',
        __DIR__.'/src/QrCodeInterface.php',
        __DIR__.'/src/RoundBlockSizeMode.php',
        __DIR__.'/tests',
    ],
    skip: [
        RemoveUnreachableStatementRector::class => [__DIR__.'/tests'],
        NewlineBetweenClassLikeStmtsRector::class,
        ExplicitNullableParamTypeRector::class => [
            __DIR__.'/src/Builder/Builder.php',
            __DIR__.'/src/Builder/BuilderInterface.php',
        ],
        PrivatizeFinalClassPropertyRector::class => [
            __DIR__.'/src/Writer/Result/GdResult.php',
        ],
        ThrowIfRector::class => [
            __DIR__.'/tests/Support/helpers.php',
        ],
    ],
);
