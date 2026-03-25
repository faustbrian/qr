<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Writer\Result;

use Cline\Qr\Exception\RuntimeException;
use Cline\Qr\Matrix\MatrixInterface;
use GdImage;

/**
 * Intermediate result wrapper for GD-backed writers.
 *
 * Concrete raster formats such as PNG, GIF, and WebP extend this concept by
 * serializing the stored `GdImage`. The base GD result itself is intentionally
 * not directly serializable.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class GdResult extends AbstractResult
{
    public function __construct(
        MatrixInterface $matrix,
        protected readonly GdImage $image,
    ) {
        parent::__construct($matrix);
    }

    /**
     * Return the underlying GD image resource.
     */
    public function getImage(): GdImage
    {
        return $this->image;
    }

    /**
     * Prevent direct serialization from the intermediate GD result type.
     *
     * @throws RuntimeException always, because only concrete GD result
     *                          subclasses can serialize themselves
     */
    public function getString(): string
    {
        throw RuntimeException::withMessage(
            'You can only use this method in a concrete implementation',
        );
    }

    /**
     * Prevent direct mime-type access from the intermediate GD result type.
     *
     * @throws RuntimeException always, because only concrete GD result
     *                          subclasses know their output mime type
     */
    public function getMimeType(): string
    {
        throw RuntimeException::withMessage(
            'You can only use this method in a concrete implementation',
        );
    }
}
