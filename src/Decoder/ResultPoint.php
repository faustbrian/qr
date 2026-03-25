<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Decoder;

/**
 * Geometric point detected while locating a barcode.
 *
 * Result points are used by detector and decoder stages to describe the
 * barcode's position, orientation, and geometric correction inputs. For QR
 * codes they usually correspond to finder patterns or reconstructed corners.
 *
 * @author Sean Owen
 */
final class ResultPoint extends AbstractResultPoint {}
