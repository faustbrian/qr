<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Writer;

/**
 * Deprecated base class for GD-backed writers.
 *
 * The package now prefers `GdTrait`, but this class remains as a compatibility
 * wrapper for consumers still typehinting or extending the old abstraction.
 *
 * @author Brian Faust <brian@cline.sh>
 * @deprecated since 6.0, use GdTrait instead. This class will be removed in 7.0.
 * @psalm-immutable
 */
abstract readonly class AbstractGdWriter implements ValidatingWriterInterface, WriterInterface
{
    use GdTrait;
}
