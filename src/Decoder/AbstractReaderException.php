<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Decoder;

use Cline\Qr\Exception\QrExceptionInterface;
use Exception;

/**
 * Base exception for barcode decoding failures.
 *
 * The reader layer throws this family of exceptions for structural problems
 * such as malformed matrices, checksum failures, or missing finder patterns.
 * The implementation keeps stack traces disabled outside tests to reduce the
 * cost of expected decode failures on hot paths.
 *
 * @author Sean Owen
 */
abstract class AbstractReaderException extends Exception implements QrExceptionInterface
{
    // disable stack traces when not running inside test units
    // protected static  $isStackTrace = System.getProperty("surefire.test.class.path") != null;
    protected static bool $isStackTrace = false;

    /**
     * Create the exception with an optional nested cause.
     *
     * @param mixed $cause Optional upstream failure reason.
     */
    public function __construct($cause = null)
    {
        if (!$cause) {
            return;
        }

        parent::__construct($cause);
    }

    // Prevent stack traces from being taken
    // srowen says: huh, my IDE is saying this is not an override. native methods can't be overridden?
    // This, at least, does not hurt. Because we use a singleton pattern here, it doesn't matter anyhow.

    /**
     * Keep decode failures lightweight by skipping stack-trace capture.
     */
    final public function fillInStackTrace()
    {
        return null;
    }
}
