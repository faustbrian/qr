<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Decoder\Qrcode\Decoder;

/**
 * Extra metadata produced by the QR decoder after a successful read.
 *
 * At present this object only records whether the symbol was mirrored during
 * detection. That flag is used by callers that need to reason about the
 * orientation of the decoded matrix after the reader has corrected it.
 * @author Brian Faust <brian@cline.sh>
 */
final class QRCodeDecoderMetaData
{
    /**
     * @param bool $mirrored True when the detector recovered the symbol by
     *                       mirroring it before decode.
     */
    public function __construct(
        private readonly bool $mirrored,
    ) {}

    /**
     * Report whether the source QR Code was mirrored before decoding.
     */
    public function isMirrored(): bool
    {
        return $this->mirrored;
    }
}
