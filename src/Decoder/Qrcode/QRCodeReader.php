<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Decoder\Qrcode;

use Cline\Qr\Decoder\BinaryBitmap;
use Cline\Qr\Decoder\ChecksumException;
use Cline\Qr\Decoder\Common\BitMatrix;
use Cline\Qr\Decoder\FormatException;
use Cline\Qr\Decoder\NotFoundException;
use Cline\Qr\Decoder\Qrcode\Decoder\Decoder;
use Cline\Qr\Decoder\Qrcode\Detector\Detector;
use Cline\Qr\Decoder\ReaderInterface;
use Cline\Qr\Decoder\Result;

use function array_key_exists;
use function round;

/**
 * High-level QR code reader that coordinates detection and decoding.
 *
 * The reader first tries the fast pure-barcode path when the caller knows the
 * image contains only a clean QR code. Otherwise it runs the full detector
 * pipeline, which locates finder patterns, corrects perspective, and hands the
 * sampled bit matrix to the decoder.
 *
 * @author Sean Owen
 */
final class QRCodeReader implements ReaderInterface
{
    private static array $NO_POINTS = [];

    private readonly Decoder $decoder;

    public function __construct()
    {
        $this->decoder = new Decoder();
    }

    /**
     * Decode a QR code from the supplied bitmap.
     *
     * When the `PURE_BARCODE` hint is present and truthy, the reader skips the
     * detector and samples the matrix directly. Otherwise it performs full QR
     * detection before decoding. The returned result includes decoded text,
     * raw bytes, result points, and any metadata exposed by the decoder.
     *
     * @param null|array<string, mixed> $hints Optional decoder hints.
     *
     * @throws ChecksumException When the decoder cannot correct the sampled data.
     * @throws FormatException   When the sampled matrix is structurally invalid.
     * @throws NotFoundException When no QR code can be located in the image.
     * @return Result
     */
    public function decode(BinaryBitmap $image, $hints = null)
    {
        $decoderResult = null;

        if ($hints !== null && array_key_exists('PURE_BARCODE', $hints) && $hints['PURE_BARCODE']) {
            $bits = self::extractPureBits($image->getBlackMatrix());
            $decoderResult = $this->decoder->decode($bits, $hints);
            $points = self::$NO_POINTS;
        } else {
            $detector = new Detector($image->getBlackMatrix());
            $detectorResult = $detector->detect($hints);

            $decoderResult = $this->decoder->decode($detectorResult->getBits(), $hints);
            $points = $detectorResult->getPoints();
        }
        $result = new Result($decoderResult->getText(), $decoderResult->getRawBytes(), $points, 'QR_CODE'); // BarcodeFormat.QR_CODE

        $byteSegments = $decoderResult->getByteSegments();

        if ($byteSegments !== null) {
            $result->putMetadata('BYTE_SEGMENTS', $byteSegments); // ResultMetadataType.BYTE_SEGMENTS
        }
        $ecLevel = $decoderResult->getECLevel();

        if ($ecLevel !== null) {
            $result->putMetadata('ERROR_CORRECTION_LEVEL', $ecLevel); // ResultMetadataType.ERROR_CORRECTION_LEVEL
        }

        if ($decoderResult->hasStructuredAppend()) {
            $result->putMetadata(
                'STRUCTURED_APPEND_SEQUENCE',// ResultMetadataType.STRUCTURED_APPEND_SEQUENCE
                $decoderResult->getStructuredAppendSequenceNumber(),
            );
            $result->putMetadata(
                'STRUCTURED_APPEND_PARITY',// ResultMetadataType.STRUCTURED_APPEND_PARITY
                $decoderResult->getStructuredAppendParity(),
            );
        }

        return $result;
    }

    public function reset(): void
    {
        // do nothing
    }

    /**
     * Expose the underlying decoder for subclasses and tests.
     *
     * The reader keeps a single decoder instance because the decode pipeline is
     * stateless between invocations.
     */
    protected function getDecoder(): Decoder
    {
        return $this->decoder;
    }

    /**
     * Sample the QR code matrix from an already-clean image.
     *
     * This fast path assumes the caller has provided a monochrome, unrotated,
     * unskewed code with a quiet zone around it. The method estimates the module
     * size from the finder pattern and then samples the matrix at the center of
     * each module. If the geometry does not fit those assumptions, decoding is
     * aborted with a not-found error rather than guessing.
     *
     * @throws ChecksumException If matrix sampling fails validation.
     * @throws FormatException   If the image geometry cannot be interpreted.
     * @throws NotFoundException If the QR code bounds or module size cannot be found.
     * @return BitMatrix         The sampled QR code bits.
     */
    private static function extractPureBits(BitMatrix $image): BitMatrix
    {
        $leftTopBlack = $image->getTopLeftOnBit();
        $rightBottomBlack = $image->getBottomRightOnBit();

        if ($leftTopBlack === null || $rightBottomBlack === null) {
            throw NotFoundException::getNotFoundInstance('Top left or bottom right on bit not found');
        }

        $moduleSize = self::moduleSize($leftTopBlack, $image);

        $top = $leftTopBlack[1];
        $bottom = $rightBottomBlack[1];
        $left = $leftTopBlack[0];
        $right = $rightBottomBlack[0];

        // Sanity check!
        if ($left >= $right || $top >= $bottom) {
            throw NotFoundException::getNotFoundInstance("Left vs. right ({$left} >= {$right}) or top vs. bottom ({$top} >= {$bottom}) sanity violated.");
        }

        if ($bottom - $top !== $right - $left) {
            // Special case, where bottom-right module wasn't black so we found something else in the last row
            // Assume it's a square, so use height as the width
            $right = $left + ($bottom - $top);
        }

        $matrixWidth = round(($right - $left + 1) / $moduleSize);
        $matrixHeight = round(($bottom - $top + 1) / $moduleSize);

        if ($matrixWidth <= 0 || $matrixHeight <= 0) {
            throw NotFoundException::getNotFoundInstance("Matrix dimensions <= 0 ({$matrixWidth}, {$matrixHeight})");
        }

        if ($matrixHeight !== $matrixWidth) {
            // Only possibly decode square regions
            throw NotFoundException::getNotFoundInstance("Matrix height  {$matrixHeight} != matrix width {$matrixWidth}");
        }

        // Push in the "border" by half the module width so that we start
        // sampling in the middle of the module. Just in case the image is a
        // little off, this will help recover.
        $nudge = (int) ($moduleSize / 2.0); // $nudge = (int) ($moduleSize / 2.0f);
        $top += $nudge;
        $left += $nudge;

        // But careful that this does not sample off the edge
        // "right" is the farthest-right valid pixel location -- right+1 is not necessarily
        // This is positive by how much the inner x loop below would be too large
        $nudgedTooFarRight = $left + (int) (($matrixWidth - 1) * $moduleSize) - $right;

        if ($nudgedTooFarRight > 0) {
            if ($nudgedTooFarRight > $nudge) {
                // Neither way fits; abort
                throw NotFoundException::getNotFoundInstance("Nudge too far right ({$nudgedTooFarRight} > {$nudge}), no fit found");
            }
            $left -= $nudgedTooFarRight;
        }
        // See logic above
        $nudgedTooFarDown = $top + (int) (($matrixHeight - 1) * $moduleSize) - $bottom;

        if ($nudgedTooFarDown > 0) {
            if ($nudgedTooFarDown > $nudge) {
                // Neither way fits; abort
                throw NotFoundException::getNotFoundInstance("Nudge too far down ({$nudgedTooFarDown} > {$nudge}), no fit found");
            }
            $top -= $nudgedTooFarDown;
        }

        // Now just read off the bits
        $bits = new BitMatrix($matrixWidth, $matrixHeight);

        for ($y = 0; $y < $matrixHeight; ++$y) {
            $iOffset = (int) ($top + (int) ($y * $moduleSize));

            for ($x = 0; $x < $matrixWidth; ++$x) {
                if (!$image->get((int) ($left + (int) ($x * $moduleSize)), $iOffset)) {
                    continue;
                }

                $bits->set($x, $y);
            }
        }

        return $bits;
    }

    /**
     * Estimate the module size from the first black-to-white transition.
     *
     * The search walks diagonally from the top-left black pixel until it sees
     * five transitions, which corresponds to the finder pattern timing model.
     * The result is used by the pure-barcode extractor to choose sampling
     * points.
     *
     * @psalm-param array{0: mixed, 1: mixed} $leftTopBlack Top-left black pixel.
     *
     * @throws NotFoundException If the edge of the image is reached too early.
     * @return float             Estimated module width in pixels.
     */
    private static function moduleSize(array $leftTopBlack, BitMatrix $image)
    {
        $height = $image->getHeight();
        $width = $image->getWidth();
        $x = $leftTopBlack[0];
        $y = $leftTopBlack[1];
        /*
        * $x           = $leftTopBlack[0];
        $y           = $leftTopBlack[1];
        */
        $inBlack = true;
        $transitions = 0;

        while ($x < $width && $y < $height) {
            if ($inBlack !== $image->get((int) round($x), (int) round($y))) {
                if (++$transitions === 5) {
                    break;
                }
                $inBlack = !$inBlack;
            }
            ++$x;
            ++$y;
        }

        if ($x === $width || $y === $height) {
            throw NotFoundException::getNotFoundInstance("{$x} == {$width} || {$y} == {$height}");
        }

        return ($x - $leftTopBlack[0]) / 7.0; // return ($x - $leftTopBlack[0]) / 7.0f;
    }
}
