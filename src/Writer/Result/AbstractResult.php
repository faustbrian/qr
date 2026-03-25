<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Writer\Result;

use Cline\Qr\Matrix\MatrixInterface;

use function base64_encode;
use function file_put_contents;

/**
 * Shared base class for writer result objects.
 *
 * Concrete results provide the serialized payload and mime type, while this
 * base class supplies the matrix reference plus common helpers for data URIs
 * and file persistence.
 *
 * @author Brian Faust <brian@cline.sh>
 */
abstract class AbstractResult implements ResultInterface
{
    public function __construct(
        private readonly MatrixInterface $matrix,
    ) {}

    /**
     * Return the matrix that was used to create the result.
     */
    public function getMatrix(): MatrixInterface
    {
        return $this->matrix;
    }

    /**
     * Return the serialized result as a base64 data URI.
     */
    public function getDataUri(): string
    {
        return 'data:'.$this->getMimeType().';base64,'.base64_encode($this->getString());
    }

    /**
     * Persist the serialized result to disk.
     */
    public function saveToFile(string $path): void
    {
        $string = $this->getString();
        file_put_contents($path, $string);
    }
}
