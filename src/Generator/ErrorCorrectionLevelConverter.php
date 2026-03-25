<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Generator;

use Cline\Qr\ErrorCorrectionLevel;
use Cline\Qr\Generator\Common\ErrorCorrectionLevel as GeneratorErrorCorrectionLevel;

/**
 * Bridge the public error-correction enum to the generator's internal enum.
 *
 * The package exposes one enum at the public API boundary and reuses the
 * generator-side enum inside the low-level encoder. This converter keeps those
 * layers aligned without leaking the internal type into user-facing code.
 *
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class ErrorCorrectionLevelConverter
{
    /**
     * Convert the public API error-correction level into the generator enum.
     */
    public static function convertToGeneratorErrorCorrectionLevel(ErrorCorrectionLevel $errorCorrectionLevel): GeneratorErrorCorrectionLevel
    {
        return match ($errorCorrectionLevel) {
            ErrorCorrectionLevel::Low => GeneratorErrorCorrectionLevel::L,
            ErrorCorrectionLevel::Medium => GeneratorErrorCorrectionLevel::M,
            ErrorCorrectionLevel::Quartile => GeneratorErrorCorrectionLevel::Q,
            ErrorCorrectionLevel::High => GeneratorErrorCorrectionLevel::H,
        };
    }
}
