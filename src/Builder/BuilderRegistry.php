<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Builder;

use Cline\Qr\Exception\RuntimeException;

use function sprintf;

/**
 * In-memory registry for named builders.
 *
 * The registry lets the package expose multiple rendering presets without
 * forcing callers to keep references to each builder instance themselves.
 * Registration is append-only at the API level: setting the same name again
 * replaces the previous mapping, while lookups fail fast when a requested name
 * has not been registered.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class BuilderRegistry implements BuilderRegistryInterface
{
    /** @var array<BuilderInterface> */
    private array $builders = [];

    /**
     * Register or replace a builder under a stable name.
     *
     * Later calls with the same name intentionally overwrite the previous
     * instance so packages can swap presets during bootstrap without having to
     * clear the registry first.
     */
    public function set(string $name, BuilderInterface $builder): void
    {
        $this->builders[$name] = $builder;
    }

    /**
     * Retrieve a previously registered builder.
     *
     * The registry does not create builders on demand. Missing names raise a
     * generic exception so callers can surface configuration errors early in the
     * composition flow instead of silently falling back to an unintended preset.
     */
    public function get(string $name): BuilderInterface
    {
        if (!isset($this->builders[$name])) {
            throw RuntimeException::withMessage(
                sprintf('Builder with name "%s" not available from registry', $name),
            );
        }

        return $this->builders[$name];
    }
}
