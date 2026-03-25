<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Decoder\Qrcode\Decoder;

use InvalidArgumentException;

use function count;
use function is_countable;

/**
 * Encodes the QR Code error-correction tier selected from the format bits.
 *
 * The decoder keeps the original decoder ordinal mapping because later stages
 * use that order when indexing version metadata and when round-tripping format
 * information from the symbol. Instances are singletons created during module
 * bootstrap and are never mutated after construction.
 *
 * @author Sean Owen
 */
final class ErrorCorrectionLevel
{
    /** @var null|array<self> */
    private static ?array $FOR_BITS = null;

    public function __construct(
        private $bits,
        private $ordinal = 0,
    ) {}

    /**
     * Initialize the lookup table used to decode the 2-bit format field.
     *
     * The table order is intentionally keyed by the raw bit pattern rather than
     * the semantic strength of the correction level. This preserves the QR Code
     * specification's ordinal mapping for later consumers of the enum.
     */
    public static function Init(): void
    {
        self::$FOR_BITS = [
            new self(0x00, 1), // M
            new self(0x01, 0), // L
            new self(0x02, 3), // H
            new self(0x03, 2), // Q
        ];
    }

    /** L = ~7% correction */
    //  self::$L = new ErrorCorrectionLevel(0x01);
    /** M = ~15% correction */
    // self::$M = new ErrorCorrectionLevel(0x00);
    /** Q = ~25% correction */
    // self::$Q = new ErrorCorrectionLevel(0x03);
    /** H = ~30% correction */
    // self::$H = new ErrorCorrectionLevel(0x02);
    /**
     * Resolve the correction level represented by the 2-bit format field.
     *
     * @param int $bits Raw two-bit QR Code format value.
     *
     * @throws InvalidArgumentException When the supplied bits fall outside the
     *                                  QR Code format field range.
     * @return self                     Decoded error-correction level instance.
     */
    public static function forBits(int $bits): ?self
    {
        if ($bits < 0 || $bits >= (is_countable(self::$FOR_BITS) ? count(self::$FOR_BITS) : 0)) {
            throw \Cline\Qr\Decoder\InvalidArgumentException::withMessage();
        }

        return self::$FOR_BITS[$bits];
        // $lev = self::$$bit;
    }

    /**
     * Return the raw 2-bit value used in the format information field.
     */
    public function getBits()
    {
        return $this->bits;
    }

    /**
     * Preserve the raw format-bit value in the legacy decoder form.
     */
    public function toString()
    {
        return $this->bits;
    }

    /**
     * Return the ordinal used by version metadata tables.
     */
    public function getOrdinal()
    {
        return $this->ordinal;
    }
}

ErrorCorrectionLevel::Init();
