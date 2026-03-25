<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Decoder\Common;

/**
 * Decoded barcode payload and the metadata produced alongside it.
 *
 * The decoder populates this object once bitstream parsing succeeds. It keeps
 * the raw bytes, text representation, byte segments, error-correction level,
 * and any structured-append metadata together so callers can inspect the full
 * decoding outcome without re-parsing the input matrix.
 *
 * @author Sean Owen
 */
final class DecoderResult
{
    /** @var null|mixed */
    private $errorsCorrected;

    /** @var null|mixed */
    private $erasures;

    /**
     * Opaque parser-specific metadata attached during decode.
     *
     * @var null|mixed
     */
    private $other;

    /**
     * Capture the full result of QR bitstream decoding.
     *
     * @param mixed $rawBytes                       decoded byte payload
     * @param mixed $text                           decoded text representation
     * @param mixed $byteSegments                   segmented byte payloads, if any
     * @param mixed $ecLevel                        error-correction level used while decoding
     * @param int   $structuredAppendSequenceNumber structured-append sequence number, or `-1`
     * @param int   $structuredAppendParity         structured-append parity, or `-1`
     */
    public function __construct(
        private $rawBytes,
        private $text,
        private $byteSegments,
        private $ecLevel,
        private $structuredAppendSequenceNumber = -1,
        private $structuredAppendParity = -1,
    ) {}

    /**
     * Return the raw bytes that were recovered from the barcode.
     */
    public function getRawBytes()
    {
        return $this->rawBytes;
    }

    /**
     * Return the decoded text payload, if the format exposes one.
     */
    public function getText()
    {
        return $this->text;
    }

    /**
     * Return any decoded byte segments captured during parsing.
     */
    public function getByteSegments()
    {
        return $this->byteSegments;
    }

    /**
     * Return the error-correction level associated with the decode.
     */
    public function getECLevel()
    {
        return $this->ecLevel;
    }

    /**
     * Return the count of corrected errors recorded by the parser, if any.
     */
    public function getErrorsCorrected()
    {
        return $this->errorsCorrected;
    }

    /**
     * Record how many errors were corrected while decoding.
     * @param mixed $errorsCorrected
     */
    public function setErrorsCorrected($errorsCorrected): void
    {
        $this->errorsCorrected = $errorsCorrected;
    }

    /**
     * Return the number of erasures reported by the parser, if any.
     */
    public function getErasures()
    {
        return $this->erasures;
    }

    /**
     * Record how many erasures were encountered while decoding.
     * @param mixed $erasures
     */
    public function setErasures($erasures): void
    {
        $this->erasures = $erasures;
    }

    /**
     * Return the optional parser-specific metadata object.
     */
    public function getOther()
    {
        return $this->other;
    }

    /**
     * Attach parser-specific metadata to the decode result.
     */
    public function setOther(\Cline\Qr\Decoder\Qrcode\Decoder\QRCodeDecoderMetaData $other): void
    {
        $this->other = $other;
    }

    /**
     * Determine whether structured-append metadata is present.
     */
    public function hasStructuredAppend(): bool
    {
        return $this->structuredAppendParity >= 0 && $this->structuredAppendSequenceNumber >= 0;
    }

    /**
     * Return the structured-append parity value.
     */
    public function getStructuredAppendParity()
    {
        return $this->structuredAppendParity;
    }

    /**
     * Return the structured-append sequence number.
     */
    public function getStructuredAppendSequenceNumber()
    {
        return $this->structuredAppendSequenceNumber;
    }
}
