<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Exception;

use Exception;

/**
 * Base exception for result validation failures.
 *
 * @author Brian Faust <brian@cline.sh>
 */
abstract class AbstractValidationException extends Exception implements QrExceptionInterface {}
