<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Generator\Renderer\Eye;

use Cline\Qr\Generator\Renderer\Path\Path;

/**
 * Combine different presets for the outer and inner finder-eye shapes.
 *
 * This is used when a renderer wants to mix two independent eye styles without
 * having to define a dedicated concrete eye class for every possible pairing.
 * @author Brian Faust <brian@cline.sh>
 */
final class CompositeEye implements EyeInterface
{
    public function __construct(
        private readonly EyeInterface $externalEye,
        private readonly EyeInterface $internalEye,
    ) {}

    /**
     * Return the outer finder-eye path from the configured external eye preset.
     */
    public function getExternalPath(): Path
    {
        return $this->externalEye->getExternalPath();
    }

    /**
     * Return the inner finder-eye path from the configured internal eye preset.
     */
    public function getInternalPath(): Path
    {
        return $this->internalEye->getInternalPath();
    }
}
