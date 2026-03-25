<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Encoding;

use Stringable;

/**
 * Stringable contract for validated encoding identifiers.
 *
 * The interface keeps encoding handling decoupled from the concrete wrapper
 * so callers can depend on the semantic type rather than a raw string.
 *
 * @author Brian Faust <brian@cline.sh>
 */
interface EncodingInterface extends Stringable
{
    /**
     * Return the encoding identifier as a string.
     */
    public function __toString(): string;
}
