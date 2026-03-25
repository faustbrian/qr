<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Generator\Common;

/**
 * Describes one repeated error-correction block pattern for a symbol version.
 *
 * QR versions often repeat the same block shape several times. This type keeps
 * the repetition count separate from the number of data codewords so the caller
 * can derive both the total payload layout and the per-block parity budget.
 * @author Brian Faust <brian@cline.sh>
 */
final class EcBlock
{
    public function __construct(
        private readonly int $count,
        private readonly int $dataCodewords,
    ) {}

    /**
     * Return how many blocks share this shape.
     */
    public function getCount(): int
    {
        return $this->count;
    }

    /**
     * Return how many data codewords each block carries.
     */
    public function getDataCodewords(): int
    {
        return $this->dataCodewords;
    }
}
