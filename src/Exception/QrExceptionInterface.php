<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Exception;

use Throwable;

/**
 * Marker interface for every package-level exception.
 *
 * Consumers may catch this interface to handle any exception thrown by the QR
 * package without broad-catching unrelated runtime failures.
 *
 * @author Brian Faust <brian@cline.sh>
 */
interface QrExceptionInterface extends Throwable {}
