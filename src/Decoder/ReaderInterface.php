<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Decoder;

/**
 * Contract for barcode readers that operate on a binary bitmap.
 *
 * Implementations may decode directly from a pure matrix or may run a full
 * detector pipeline first. The interface keeps the orchestration layer simple
 * while allowing different barcode formats to share the same calling shape.
 * @author Brian Faust <brian@cline.sh>
 */
interface ReaderInterface
{
    /**
     * Decode the supplied bitmap into a reader-specific result object.
     *
     * @return Result Reader output when decoding succeeds.
     */
    public function decode(BinaryBitmap $image);

    /**
     * Reset any reader-specific caches or mutable state.
     */
    public function reset();
}
