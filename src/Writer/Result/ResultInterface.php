<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Writer\Result;

use Cline\Qr\Matrix\MatrixInterface;

/**
 * Common contract for all writer results.
 *
 * Result objects preserve the matrix used to render the output and expose
 * helpers for retrieving the serialized payload, data URI, mime type, and file
 * persistence behavior.
 *
 * @author Brian Faust <brian@cline.sh>
 */
interface ResultInterface
{
    /**
     * Return the matrix used to create the result.
     */
    public function getMatrix(): MatrixInterface;

    /**
     * Return the serialized output payload.
     */
    public function getString(): string;

    /**
     * Return the serialized output as a base64 data URI.
     */
    public function getDataUri(): string;

    /**
     * Persist the serialized output to disk.
     */
    public function saveToFile(string $path): void;

    /**
     * Return the mime type for the serialized output.
     */
    public function getMimeType(): string;
}
