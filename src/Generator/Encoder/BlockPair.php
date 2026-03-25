<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Generator\Encoder;

use SplFixedArray;

/**
 * One interleaved data block and its companion parity bytes.
 *
 * The encoder constructs these pairs before the final interleave step so each
 * block can be tracked independently while Reed-Solomon bytes are generated.
 * @author Brian Faust <brian@cline.sh>
 */
final class BlockPair
{
    /**
     * Create a block pair from the already-split data and parity buffers.
     *
     * Both buffers are treated as immutable snapshots of the block layout.
     *
     * @param SplFixedArray<int> $dataBytes            Data bytes in the block.
     * @param SplFixedArray<int> $errorCorrectionBytes Error correction bytes in
     *                                                 the block.
     */
    public function __construct(
        private readonly SplFixedArray $dataBytes,
        private readonly SplFixedArray $errorCorrectionBytes,
    ) {}

    /**
     * Return the block's data bytes.
     *
     * @return SplFixedArray<int>
     */
    public function getDataBytes(): SplFixedArray
    {
        return $this->dataBytes;
    }

    /**
     * Return the block's error-correction bytes.
     *
     * @return SplFixedArray<int>
     */
    public function getErrorCorrectionBytes(): SplFixedArray
    {
        return $this->errorCorrectionBytes;
    }
}
