<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Decoder;

/**
 * Receives intermediate detector points while a QR code is being located.
 * @author Brian Faust <brian@cline.sh>
 */
interface ResultPointCallback
{
    /**
     * Report a possible finder or alignment point observed during detection.
     */
    public function foundPossibleResultPoint(object $point): void;
}
