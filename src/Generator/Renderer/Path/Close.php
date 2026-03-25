<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Generator\Renderer\Path;

/**
 * Path operation that closes the current subpath.
 *
 * The operation carries no coordinates, so translation and rotation leave it
 * unchanged. It is exposed as a singleton because every close command is
 * identical.
 * @author Brian Faust <brian@cline.sh>
 */
final class Close implements OperationInterface
{
    private static ?Close $instance = null;

    private function __construct() {}

    /**
     * Return the shared close-path operation instance.
     */
    public static function instance(): self
    {
        return self::$instance ?: self::$instance = new self();
    }

    /**
     * Return the same instance because close operations have no coordinates.
     *
     * @return self
     */
    public function translate(float $x, float $y): OperationInterface
    {
        return $this;
    }

    /**
     * Return the same instance because close operations have no coordinates.
     *
     * @return self
     */
    public function rotate(int $degrees): OperationInterface
    {
        return $this;
    }
}
