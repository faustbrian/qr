<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Exception;

use function sprintf;

/**
 * Raised when validation dependencies are missing at runtime.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class MissingValidationPackageException extends AbstractValidationException
{
    public static function forPackage(string $packageName): self
    {
        return new self(
            sprintf(
                'Please install "%s" or disable image validation',
                $packageName,
            ),
        );
    }
}
