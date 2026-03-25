<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Encoding;

use Cline\Qr\Exception\InvalidArgumentException;
use Cline\Qr\Exception\RuntimeException;
use Stringable;

use function function_exists;
use function implode;
use function in_array;
use function mb_list_encodings;
use function sprintf;
use function throw_unless;

/**
 * Immutable wrapper around a validated character encoding name.
 *
 * The builder and decoder use this type to keep the public API explicit about
 * when encoded text should be interpreted differently from UTF-8. Validation
 * happens at construction time so invalid encodings fail early.
 *
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class Encoding implements EncodingInterface, Stringable
{
    /**
     * @param string $value Encoding identifier accepted by `mb_list_encodings()`.
     */
    public function __construct(
        private string $value,
    ) {
        if ('UTF-8' === $value) {
            return;
        }

        throw_unless(
            function_exists('mb_list_encodings'),
            RuntimeException::withMessage(
                'Unable to validate encoding: make sure the mbstring extension is installed and enabled',
            ),
        );

        if (!in_array($value, mb_list_encodings(), true)) {
            throw InvalidArgumentException::withMessage(
                sprintf(
                    'Invalid encoding "%s": choose one of '.implode(', ', mb_list_encodings()),
                    $value,
                ),
            );
        }
    }

    /**
     * Return the canonical encoding name.
     */
    public function __toString(): string
    {
        return $this->value;
    }
}
