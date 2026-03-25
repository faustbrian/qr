<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Decoder;

use function array_merge;
use function arraycopy;
use function count;
use function fill_array;
use function is_countable;
use function time;

/**
 * Carries the decoded payload and metadata for a barcode scan.
 *
 * The result object is the handoff point between the decoder and the caller.
 * It preserves the raw text, raw bytes, geometric result points, barcode
 * format, and optional metadata such as orientation or error-correction data.
 *
 * @author Sean Owen
 */
final class Result
{
    /** @var array<mixed>|mixed */
    private $resultMetadata;

    private $timestamp;

    /**
     * @param mixed $text         Decoded text payload.
     * @param mixed $rawBytes     Raw bytes from the decoder, when available.
     * @param mixed $resultPoints Points that locate the barcode in the image.
     * @param mixed $format       Barcode format identifier.
     * @param mixed $timestamp    Optional decode timestamp; defaults to now.
     */
    public function __construct(
        private $text,
        private $rawBytes,
        private $resultPoints,
        private $format,
        $timestamp = '',
    ) {
        $this->timestamp = $timestamp ?: time();
    }

    /**
     * Return the decoded text payload.
     *
     * @return string
     */
    public function getText()
    {
        return $this->text;
    }

    /**
     * Return the raw decoded bytes, if the barcode type exposes them.
     *
     * @return array|string
     */
    public function getRawBytes()
    {
        return $this->rawBytes;
    }

    /**
     * Return the points used to localize the barcode in the source image.
     *
     * These are typically finder patterns or corners and are format-specific.
     *
     * @return array
     */
    public function getResultPoints()
    {
        return $this->resultPoints;
    }

    /**
     * Return the barcode format reported by the decoder.
     *
     * @return mixed
     */
    public function getBarcodeFormat()
    {
        return $this->format;
    }

    /**
     * Return any optional decoder metadata attached to the result.
     *
     * The metadata map is populated only when the decoder has something extra
     * to report, such as byte segments or error-correction level.
     *
     * @return null|array<mixed>|mixed
     */
    public function getResultMetadata()
    {
        return $this->resultMetadata;
    }

    /**
     * Attach a single metadata value to the result.
     *
     * @param string $type  Metadata key.
     * @param mixed  $value Metadata value.
     */
    public function putMetadata(string $type, $value): void
    {
        $resultMetadata = [];

        if ($this->resultMetadata === null) {
            $this->resultMetadata = [];
        }
        $resultMetadata[$type] = $value;
    }

    /**
     * Merge a metadata map into the result.
     *
     * Existing keys are preserved unless the incoming map overwrites them.
     *
     * @param null|array<mixed> $metadata Metadata map to merge.
     */
    public function putAllMetadata($metadata): void
    {
        if ($metadata === null) {
            return;
        }

        if ($this->resultMetadata === null) {
            $this->resultMetadata = $metadata;
        } else {
            $this->resultMetadata = array_merge($this->resultMetadata, $metadata);
        }
    }

    /**
     * Append more result points to the existing set.
     *
     * @param null|array<int, mixed> $newPoints Additional localized points.
     */
    public function addResultPoints($newPoints): void
    {
        $oldPoints = $this->resultPoints;

        if ($oldPoints === null) {
            $this->resultPoints = $newPoints;
        } elseif ($newPoints !== null && (is_countable($newPoints) ? count($newPoints) : 0) > 0) {
            $allPoints = fill_array(0, (is_countable($oldPoints) ? count($oldPoints) : 0) + (is_countable($newPoints) ? count($newPoints) : 0), 0);
            $allPoints = arraycopy($oldPoints, 0, $allPoints, 0, is_countable($oldPoints) ? count($oldPoints) : 0);
            $allPoints = arraycopy($newPoints, 0, $allPoints, is_countable($oldPoints) ? count($oldPoints) : 0, is_countable($newPoints) ? count($newPoints) : 0);
            $this->resultPoints = $allPoints;
        }
    }

    public function getTimestamp()
    {
        return $this->timestamp;
    }

    /**
     * Return the textual representation of the decoded result.
     *
     * @return string
     */
    public function toString()
    {
        return $this->text;
    }
}
