<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Generator\Renderer\Path;

/**
 * Contract for immutable vector path operations.
 *
 * Every operation must be able to produce a transformed copy of itself so
 * higher-level paths can be rotated or translated without mutating existing
 * instances.
 * @author Brian Faust <brian@cline.sh>
 */
interface OperationInterface
{
    /**
     * Return a translated copy of this operation.
     */
    public function translate(float $x, float $y): self;

    /**
     * Return a rotated copy of this operation around the origin.
     */
    public function rotate(int $degrees): self;
}
