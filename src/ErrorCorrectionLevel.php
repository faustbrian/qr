<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr;

/**
 * QR code error-correction strength levels.
 *
 * Higher levels improve resilience to damage and obscuration at the cost of
 * usable payload capacity. The enum keeps the public API explicit and prevents
 * callers from passing arbitrary strings into the builder.
 *
 * @author Brian Faust <brian@cline.sh>
 */
enum ErrorCorrectionLevel: string
{
    /** Highest redundancy, lowest data capacity. */
    case High = 'high';

    /** Lowest redundancy, highest data capacity. */
    case Low = 'low';

    /** Balanced redundancy and capacity. */
    case Medium = 'medium';

    /** Higher redundancy for degraded scans. */
    case Quartile = 'quartile';
}
