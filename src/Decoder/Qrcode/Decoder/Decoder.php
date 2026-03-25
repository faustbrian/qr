<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Decoder\Qrcode\Decoder;

use Cline\Qr\Decoder\ChecksumException;
use Cline\Qr\Decoder\Common\BitMatrix;
use Cline\Qr\Decoder\Common\DecoderResult;
use Cline\Qr\Decoder\Common\Reedsolomon\GenericGF;
use Cline\Qr\Decoder\Common\Reedsolomon\ReedSolomonDecoder;
use Cline\Qr\Decoder\Common\Reedsolomon\ReedSolomonException;
use Cline\Qr\Decoder\FormatException;

use function count;
use function fill_array;
use function is_array;
use function is_countable;

/**
 * Top-level QR decoder.
 *
 * This class turns image-like inputs into QR payload results by delegating to
 * the bit-matrix parser, Reed-Solomon correction, and bit-stream decoder. It
 * also owns the mirrored-read recovery path used when a symbol is captured
 * upside down or reflected.
 *
 * @author Sean Owen
 */
final class Decoder
{
    private readonly ReedSolomonDecoder $rsDecoder;

    public function __construct()
    {
        $this->rsDecoder = new ReedSolomonDecoder(GenericGF::$QR_CODE_FIELD_256);
    }

    /**
     * Dispatch decoding based on the input representation.
     *
     * Arrays are treated as boolean module grids, `BitMatrix` instances are
     * decoded directly, and `BitMatrixParser` instances are used when the caller
     * has already parsed the QR metadata.
     *
     * @param array<int, array<int, bool>>|BitMatrix|BitMatrixParser $variable Input QR representation.
     * @param null|array<string, mixed>                              $hints    Optional decoder hints.
     *
     * @throws ChecksumException    If Reed-Solomon correction fails.
     * @throws FormatException      If the symbol cannot be parsed.
     * @return DecoderResult|string Decoded payload.
     */
    public function decode(BitMatrix|BitMatrixParser $variable, ?array $hints = null): string|DecoderResult
    {
        if (is_array($variable)) {
            return $this->decodeImage($variable, $hints);
        }

        if ($variable instanceof BitMatrix) {
            return $this->decodeBits($variable, $hints);
        }

        if ($variable instanceof BitMatrixParser) {
            return $this->decodeParser($variable, $hints);
        }

        exit('decode error Decoder.php');
    }

    /**
     * Decode a QR code represented as a two-dimensional boolean grid.
     *
     * `true` means a black module, `false` means a white module.
     *
     * @param array<int, array<int, bool>> $image Boolean QR module grid.
     * @param null|array<string, mixed>    $hints Optional decoding hints.
     *
     * @throws ChecksumException    if error correction fails
     * @throws FormatException      if the QR Code cannot be decoded
     * @return DecoderResult|string Decoded payload.
     */
    public function decodeImage(array $image, $hints = null): string|DecoderResult
    {
        $dimension = is_countable($image) ? count($image) : 0;
        $bits = new BitMatrix($dimension);

        for ($i = 0; $i < $dimension; ++$i) {
            for ($j = 0; $j < $dimension; ++$j) {
                if (!$image[$i][$j]) {
                    continue;
                }

                $bits->set($j, $i);
            }
        }

        return $this->decode($bits, $hints);
    }

    /**
     * Decode a QR code represented as a bit matrix.
     *
     * The method retries with mirrored coordinates when the first parse fails
     * in a way that suggests the symbol may have been mirrored.
     *
     * @param BitMatrix                 $bits  Matrix of white/black QR modules.
     * @param null|array<string, mixed> $hints Optional decoding hints.
     *
     * @throws ChecksumException    if error correction fails
     * @throws FormatException      if the QR Code cannot be decoded
     * @return DecoderResult|string Decoded payload.
     */
    public function decodeBits(BitMatrix $bits, $hints = null): string|DecoderResult
    {
        // Construct a parser and read version, error-correction level
        $parser = new BitMatrixParser($bits);
        $fe = null;
        $ce = null;

        try {
            return $this->decode($parser, $hints);
        } catch (FormatException $e) {
            $fe = $e;
        } catch (ChecksumException $e) {
            $ce = $e;
        }

        try {
            // Revert the bit matrix
            $parser->remask();

            // Will be attempting a mirrored reading of the version and format info.
            $parser->setMirror(true);

            // Preemptively read the version.
            $parser->readVersion();

            // Preemptively read the format information.
            $parser->readFormatInformation();

            /*
            * Since we're here, this means we have successfully detected some kind
            * of version and format information when mirrored. This is a good sign,
            * that the QR code may be mirrored, and we should try once more with a
            * mirrored content.
            */
            // Prepare for a mirrored reading.
            $parser->mirror();

            $result = $this->decode($parser, $hints);

            // Success! Notify the caller that the code was mirrored.
            $result->setOther(
                new QRCodeDecoderMetaData(true),
            );

            return $result;
        } catch (FormatException $e) { // catch (FormatException | ChecksumException e) {
            // Throw the exception from the original reading
            if ($fe !== null) {
                throw $fe;
            }

            if ($ce !== null) {
                throw $ce;
            }

            throw $e;
        }
    }

    /**
     * Decode a parsed QR symbol and return the structured result.
     *
     * This step performs block separation, Reed-Solomon correction, and final
     * segment decoding into text and metadata.
     *
     * @param  BitMatrixParser           $parser Parsed QR metadata and bit matrix.
     * @param  null|array<string, mixed> $hints  Optional decoding hints.
     * @return DecoderResult             Decoded payload and metadata.
     */
    private function decodeParser(BitMatrixParser $parser, ?array $hints = null): DecoderResult
    {
        $version = $parser->readVersion();
        $ecLevel = $parser->readFormatInformation()->getErrorCorrectionLevel();

        // Read codewords
        $codewords = $parser->readCodewords();
        // Separate into data blocks
        $dataBlocks = DataBlock::getDataBlocks($codewords, $version, $ecLevel);

        // Count total number of data bytes
        $totalBytes = 0;

        foreach ($dataBlocks as $dataBlock) {
            $totalBytes += $dataBlock->getNumDataCodewords();
        }
        $resultBytes = fill_array(0, $totalBytes, 0);
        $resultOffset = 0;

        // Error-correct and copy data blocks together into a stream of bytes
        foreach ($dataBlocks as $dataBlock) {
            $codewordBytes = $dataBlock->getCodewords();
            $numDataCodewords = $dataBlock->getNumDataCodewords();
            $this->correctErrors($codewordBytes, $numDataCodewords);

            for ($i = 0; $i < $numDataCodewords; ++$i) {
                $resultBytes[$resultOffset++] = $codewordBytes[$i];
            }
        }

        // Decode the contents of that stream of bytes
        return DecodedBitStreamParser::decode($resultBytes, $version, $ecLevel, $hints);
    }

    /**
     * Correct a block of codewords in place using Reed-Solomon recovery.
     *
     * The data bytes are copied into a scratch integer buffer, corrected, and
     * then written back only for the data portion of the block.
     *
     * @param  array<int, int>   $codewordBytes    Data and error-correction codewords.
     * @param  int               $numDataCodewords Number of leading data bytes in the block.
     * @throws ChecksumException If correction fails.
     */
    private function correctErrors($codewordBytes, int $numDataCodewords): void
    {
        $numCodewords = is_countable($codewordBytes) ? count($codewordBytes) : 0;
        // First read into an array of ints
        $codewordsInts = fill_array(0, $numCodewords, 0);

        for ($i = 0; $i < $numCodewords; ++$i) {
            $codewordsInts[$i] = $codewordBytes[$i] & 0xFF;
        }
        $numECCodewords = (is_countable($codewordBytes) ? count($codewordBytes) : 0) - $numDataCodewords;

        try {
            $this->rsDecoder->decode($codewordsInts, $numECCodewords);
        } catch (ReedSolomonException) {
            throw ChecksumException::getChecksumInstance();
        }

        // Copy back into array of bytes -- only need to worry about the bytes that were data
        // We don't care about errors in the error-correction codewords
        for ($i = 0; $i < $numDataCodewords; ++$i) {
            $codewordBytes[$i] = $codewordsInts[$i];
        }
    }
}
