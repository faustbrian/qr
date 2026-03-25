<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Generator\Encoder;

use Cline\Qr\Generator\Common\ErrorCorrectionLevel;
use Cline\Qr\Generator\Common\Mode;
use Cline\Qr\Generator\Common\Version;
use Stringable;

/**
 * Encoded QR symbol metadata plus the final module matrix.
 *
 * This is the encoder's output object: it keeps the chosen mode, error
 * correction level, version, and mask pattern together with the generated
 * matrix so downstream writers can render the symbol without repeating any
 * layout decisions.
 * @author Brian Faust <brian@cline.sh>
 */
final class QrCode implements Stringable
{
    /**
     * Number of data-mask candidates defined by the QR specification.
     */
    public const int NUM_MASK_PATTERNS = 8;

    public function __construct(
        private readonly Mode $mode,
        private readonly ErrorCorrectionLevel $errorCorrectionLevel,
        private readonly Version $version,
        /**
         * Mask pattern selected during encoding.
         */
        private readonly int $maskPattern,
        /**
         * Final QR matrix ready for rendering.
         */
        private readonly ByteMatrix $matrix,
    ) {}

    /**
     * Render the symbol metadata and matrix for debugging output.
     */
    public function __toString(): string
    {
        $result = "<<\n"
                .' mode: '.$this->mode->name."\n"
                .' ecLevel: '.$this->errorCorrectionLevel->name."\n"
                .' version: '.$this->version."\n"
                .' maskPattern: '.$this->maskPattern."\n";

        if ($this->matrix === null) {
            $result .= " matrix: null\n";
        } else {
            $result .= " matrix:\n";
            $result .= $this->matrix;
        }

        $result .= ">>\n";

        return $result;
    }

    /**
     * Validate whether a mask pattern is within the QR range.
     */
    public static function isValidMaskPattern(int $maskPattern): bool
    {
        return $maskPattern > 0 && $maskPattern < self::NUM_MASK_PATTERNS;
    }

    /**
     * Return the mode used to encode the payload.
     */
    public function getMode(): Mode
    {
        return $this->mode;
    }

    /**
     * Return the error-correction level used for the symbol.
     */
    public function getErrorCorrectionLevel(): ErrorCorrectionLevel
    {
        return $this->errorCorrectionLevel;
    }

    /**
     * Return the QR version that was selected for the payload.
     */
    public function getVersion(): Version
    {
        return $this->version;
    }

    /**
     * Return the mask pattern chosen during scoring.
     */
    public function getMaskPattern(): int
    {
        return $this->maskPattern;
    }

    public function getMatrix(): ByteMatrix
    {
        return $this->matrix;
    }
}
