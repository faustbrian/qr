<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Decoder;

/**
 * Bridges legacy callback objects into the explicit callback contract.
 * @psalm-immutable
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class ResultPointCallbackAdapter implements ResultPointCallback
{
    public function __construct(
        private object $callback,
    ) {}

    public function foundPossibleResultPoint(object $point): void
    {
        $this->callback->foundPossibleResultPoint($point);
    }
}
