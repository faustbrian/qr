<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Decoder\Common;

use Cline\Qr\Decoder\InvalidArgumentException;
use ReflectionClass;
use Stringable;

use function array_search;
use function in_array;

/**
 * Lightweight reflection-backed enum base for the legacy decoder code.
 *
 * This class predates native backed enums and is retained so the ported decoder
 * decoder types can keep their original constant-driven API. It caches the
 * reflected constant list after first use because these enums are often queried
 * repeatedly during decode operations.
 * @author Brian Faust <brian@cline.sh>
 */
final class AbstractEnum implements Stringable
{
    /**
     * Default value used by legacy enum declarations.
     */
    public const null __default = null;

    /**
     * Current selected enum value.
     *
     * @var mixed
     */
    private $value;

    /**
     * Cached reflection output for the concrete enum class.
     *
     * @var null|array<string, mixed>
     */
    private ?array $constants = null;

    /**
     * Create a new enum instance.
     *
     * The initial value is validated against the constant list immediately, so
     * invalid state cannot be constructed and then discovered later in decoding.
     *
     * @param mixed $initialValue Initial enum value.
     * @param bool  $strict       Whether comparisons should be strict.
     */
    public function __construct(
        $initialValue = null,
        private $strict = false,
    ) {
        $this->change($initialValue);
    }

    /**
     * Return the constant name for the current value.
     *
     * When multiple constants map to the same value, the first matching name is
     * returned, mirroring the original decoder utility behavior.
     */
    public function __toString(): string
    {
        return (string) array_search($this->value, $this->getConstList(), true);
    }

    /**
     * Change the current enum value.
     *
     * The assignment is validated against the reflected constant list before the
     * value is stored, which keeps the object in a known-good state.
     *
     * @param mixed $value New enum value.
     */
    public function change($value): void
    {
        if (!in_array($value, $this->getConstList(), $this->strict)) {
            throw InvalidArgumentException::withMessage(
                'Value not a const in enum '.$this::class,
            );
        }
        $this->value = $value;
    }

    /**
     * Return the concrete enum's constants.
     *
     * The reflection result is cached so repeated validity checks do not incur
     * repeated introspection cost.
     *
     * @return array<string, mixed>
     */
    public function getConstList(bool $includeDefault = true)
    {
        $constants = [];

        if ($this->constants === null) {
            $reflection = new ReflectionClass($this);
            $this->constants = $reflection->getConstants();
        }

        if ($includeDefault) {
            return $this->constants;
        }
        $constants = $this->constants;
        unset($constants['__default']);

        return $constants;
    }

    /**
     * Return the current enum value.
     *
     * @return mixed
     */
    public function get()
    {
        return $this->value;
    }
}
