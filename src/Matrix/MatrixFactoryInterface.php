<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Matrix;

use Cline\Qr\QrCodeInterface;

/**
 * Contract for creating render-ready matrices from public QR value objects.
 *
 * Implementations bridge the public `QrCodeInterface` configuration into the
 * lower-level matrix representation consumed by writers.
 *
 * @author Brian Faust <brian@cline.sh>
 */
interface MatrixFactoryInterface
{
    /**
     * Encode the QR payload and adapt it to the package matrix abstraction.
     */
    public function create(QrCodeInterface $qrCode): MatrixInterface;
}
