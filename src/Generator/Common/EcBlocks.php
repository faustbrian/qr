<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Generator\Common;

/**
 * Describes the error-correction layout for one QR version and level.
 *
 * Versions can mix repeated block shapes, but the number of error-correction
 * codewords per block stays constant within a given version and correction
 * level. This class groups those pieces together so the encoder can calculate
 * block counts, parity, and payload capacity from one object.
 * @author Brian Faust <brian@cline.sh>
 */
final class EcBlocks
{
    /**
     * Repeated block shapes for this version.
     *
     * @var array<EcBlock>
     */
    private array $ecBlocks;

    public function __construct(
        private readonly int $ecCodewordsPerBlock,
        EcBlock ...$ecBlocks,
    ) {
        $this->ecBlocks = $ecBlocks;
    }

    /**
     * Return the parity codewords attached to each block.
     */
    public function getEcCodewordsPerBlock(): int
    {
        return $this->ecCodewordsPerBlock;
    }

    /**
     * Return the total number of block instances represented here.
     */
    public function getNumBlocks(): int
    {
        $total = 0;

        foreach ($this->ecBlocks as $ecBlock) {
            $total += $ecBlock->getCount();
        }

        return $total;
    }

    /**
     * Return the total parity codewords across all block instances.
     */
    public function getTotalEcCodewords(): int
    {
        return $this->ecCodewordsPerBlock * $this->getNumBlocks();
    }

    /**
     * Return the block shapes that make up this layout.
     *
     * @return array<EcBlock>
     */
    public function getEcBlocks(): array
    {
        return $this->ecBlocks;
    }
}
