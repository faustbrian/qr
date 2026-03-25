<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

if (!function_exists('throw_if')) {
    /**
     * @param class-string<Throwable>|Throwable $exception
     */
    function throw_if(bool $condition, Throwable|string $exception, string $message = ''): void
    {
        if ($condition) {
            if ($exception instanceof Throwable) {
                throw $exception;
            }

            throw new $exception($message);
        }
    }
}

if (!function_exists('throw_unless')) {
    /**
     * @param class-string<Throwable>|Throwable $exception
     */
    function throw_unless(bool $condition, Throwable|string $exception, string $message = ''): void
    {
        if (!$condition) {
            if ($exception instanceof Throwable) {
                throw $exception;
            }

            throw new $exception($message);
        }
    }
}
