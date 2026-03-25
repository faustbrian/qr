<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Decoder;

/**
 * Indicates that detection succeeded but the payload failed format validation.
 *
 * The decoder uses this exception to distinguish a structurally detected QR
 * code from one whose contents cannot be parsed into a valid payload.
 *
 * @author Sean Owen
 */
final class FormatException extends AbstractReaderException
{
    /**
     * Shared singleton instance used when stack traces are disabled.
     */
    private static ?FormatException $instance = null;

    /**
     * @param mixed $cause optional underlying cause used when stack traces are enabled
     */
    public function __construct($cause = null)
    {
        if (!$cause) {
            return;
        }

        parent::__construct($cause);
    }

    /**
     * Returns the canonical format exception instance.
     *
     * When stack traces are enabled, a fresh exception is returned so callers get
     * the cause chain. Otherwise the cached singleton is reused to avoid repeated
     * allocations in the common decode-failure path.
     *
     * @param mixed $cause optional underlying cause
     */
    public static function getFormatInstance($cause = null): self
    {
        if (!self::$instance) {
            self::$instance = new self();
        }

        if (self::$isStackTrace) {
            return new self($cause);
        }

        return self::$instance;
    }
}
