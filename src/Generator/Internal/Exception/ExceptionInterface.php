<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Generator\Internal\Exception;

use Cline\Qr\Exception\QrExceptionInterface;

/**
 * Marker interface for generator-internal failures.
 *
 * The low-level encoder and renderer code throws SPL-based exceptions, but this
 * contract lets package code catch and distinguish failures originating from
 * the internal QR engine without broad-catching unrelated runtime exceptions.
 * @author Brian Faust <brian@cline.sh>
 */
interface ExceptionInterface extends QrExceptionInterface {}
