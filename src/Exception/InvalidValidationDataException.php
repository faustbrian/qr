<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Exception;

/**
 * Raised when the validation reader decodes a payload different from expected.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class InvalidValidationDataException extends AbstractValidationException
{
    public static function forExpectedAndActual(
        string $expectedData,
        string $actualData,
    ): self {
        return new self(
            'The validation reader read "'.$actualData.'" instead of "'
            .$expectedData
            .'". Adjust your parameters to increase readability or disable '
            .'validation.',
        );
    }
}
