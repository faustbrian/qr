<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Generator\Renderer\Color;

use Cline\Qr\Generator\Internal\Exception\InvalidArgumentException;

/**
 * Immutable percentage value shared by renderer color models.
 *
 * Several renderer color types use normalized `0..100` percentages for channel
 * values or opacity. Centralizing that concept keeps validation, comparison,
 * and simple percentage math in one place instead of repeating it per color
 * class.
 *
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class Percentage
{
    public function __construct(
        private int $value,
    ) {
        if ($value < 0 || $value > 100) {
            throw InvalidArgumentException::withMessage('Percentage must be between 0 and 100');
        }
    }

    public function value(): int
    {
        return $this->value;
    }

    public function asFraction(): float
    {
        return $this->value / 100;
    }

    public function complement(): self
    {
        return new self(100 - $this->value);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
