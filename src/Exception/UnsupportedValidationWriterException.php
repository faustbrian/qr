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
 * Raised when validation is requested for a writer without validation support.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class UnsupportedValidationWriterException extends AbstractValidationException
{
    public static function forWriter(string $writerClass): self
    {
        return new self(
            sprintf(
                'Unable to validate the result: "%s" does not support validation',
                $writerClass,
            ),
        );
    }
}
