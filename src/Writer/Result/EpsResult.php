<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Writer\Result;

use Cline\Qr\Matrix\MatrixInterface;

use function implode;

/**
 * Result wrapper for generated EPS line content.
 *
 * The writer stores the EPS program as prebuilt lines, and this result joins
 * them into the final textual document on demand.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class EpsResult extends AbstractResult
{
    public function __construct(
        MatrixInterface $matrix,
        /** @var array<string> $lines */
        private readonly array $lines,
    ) {
        parent::__construct($matrix);
    }

    /**
     * Serialize the EPS lines into one document string.
     */
    public function getString(): string
    {
        return implode("\n", $this->lines);
    }

    /**
     * Return the EPS mime type.
     */
    public function getMimeType(): string
    {
        return 'image/eps';
    }
}
