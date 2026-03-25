<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\CodingStandard\EasyCodingStandard\Factory;
use PhpCsFixer\Fixer\Alias\MbStrFunctionsFixer;
use PhpCsFixerCustomFixers\Fixer\ReadonlyPromotedPropertiesFixer;

return Factory::create(
    paths: [
        __DIR__.'/src',
        __DIR__.'/tests',
    ],
    skip: [
        MbStrFunctionsFixer::class => [
            __DIR__.'/src/Generator/Encoder/Encoder.php',
        ],
        ReadonlyPromotedPropertiesFixer::class => [
            __DIR__.'/src/Generator/Common/BitArray.php',
        ],
    ],
);
