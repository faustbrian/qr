<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr;

use Cline\Qr\Color\ColorInterface;
use Cline\Qr\Encoding\EncodingInterface;

/**
 * Contract for public QR configuration objects.
 *
 * Writers and matrix factories depend on this interface so callers can provide
 * alternative immutable QR configuration implementations when needed.
 *
 * @author Brian Faust <brian@cline.sh>
 */
interface QrCodeInterface
{
    /**
     * Return the payload string to encode.
     */
    public function getData(): string;

    /**
     * Return the payload encoding.
     */
    public function getEncoding(): EncodingInterface;

    /**
     * Return the requested error-correction level.
     */
    public function getErrorCorrectionLevel(): ErrorCorrectionLevel;

    /**
     * Return the target output size.
     */
    public function getSize(): int;

    /**
     * Return the requested quiet-zone margin.
     */
    public function getMargin(): int;

    /**
     * Return the block-size rounding strategy.
     */
    public function getRoundBlockSizeMode(): RoundBlockSizeMode;

    /**
     * Return the foreground color.
     */
    public function getForegroundColor(): ColorInterface;

    /**
     * Return the background color.
     */
    public function getBackgroundColor(): ColorInterface;
}
