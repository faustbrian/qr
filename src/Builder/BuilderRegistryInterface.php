<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Builder;

/**
 * Registry contract for named QR builders.
 *
 * Implementations are expected to behave like a small service locator for
 * rendering presets: callers can publish a builder under a stable name and
 * resolve it later in the request or boot lifecycle.
 *
 * @author Brian Faust <brian@cline.sh>
 */
interface BuilderRegistryInterface
{
    /**
     * Store a builder under the given name, replacing any existing entry.
     */
    public function set(string $name, BuilderInterface $builder): void;

    /**
     * Resolve a named builder or fail if the registry has no matching entry.
     */
    public function get(string $name): BuilderInterface;
}
