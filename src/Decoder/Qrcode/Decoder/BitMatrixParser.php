<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Decoder\Qrcode\Decoder;

use Cline\Qr\Decoder\Common\BitMatrix;
use Cline\Qr\Decoder\FormatException;

use function fill_array;

/**
 * Parser for QR function patterns, version bits, and raw codewords.
 *
 * The parser owns the bit-matrix traversal rules for QR symbols. It reads
 * format and version metadata, removes the active data mask in place, and
 * then walks the matrix in the QR-specific zig-zag order to reconstruct the
 * interleaved codeword stream.
 *
 * @author Sean Owen
 */
final class BitMatrixParser
{
    /** @var BitMatrix */
    private $bitMatrix;

    /**
     * Cached version metadata parsed from the symbol.
     *
     * @var null|mixed
     */
    private $parsedVersion;

    /** @var null|mixed */
    private $parsedFormatInfo;

    /** @var null|bool */
    private $mirror;

    /**
     * Prepare a parser for a QR bit matrix.
     *
     * @param BitMatrix $bitMatrix Matrix representation of the QR code.
     *
     * @throws FormatException If the matrix dimension is not valid for a QR code.
     */
    public function __construct($bitMatrix)
    {
        $dimension = $bitMatrix->getHeight();

        if ($dimension < 21 || ($dimension & 0x03) !== 1) {
            throw FormatException::getFormatInstance();
        }
        $this->bitMatrix = $bitMatrix;
    }

    /**
     * Read the raw codewords from the matrix.
     *
     * The method first resolves format and version information, unmasking the
     * matrix before it walks the data region in the QR-defined column pairs.
     * The returned byte stream is still interleaved exactly as stored in the
     * symbol; later stages split it into data blocks.
     *
     * @throws FormatException If the matrix cannot be parsed cleanly.
     * @return array<int, int> Raw codewords encoded within the QR code.
     */
    public function readCodewords()
    {
        $formatInfo = $this->readFormatInformation();
        $version = $this->readVersion();

        // Get the data mask for the format used in this QR Code. This will exclude
        // some bits from reading as we wind through the bit matrix.
        $dataMask = AbstractDataMask::forReference($formatInfo->getDataMask());
        $dimension = $this->bitMatrix->getHeight();
        $dataMask->unmaskBitMatrix($this->bitMatrix, $dimension);

        $functionPattern = $version->buildFunctionPattern();

        $readingUp = true;

        if ($version->getTotalCodewords()) {
            $result = fill_array(0, $version->getTotalCodewords(), 0);
        } else {
            $result = [];
        }
        $resultOffset = 0;
        $currentByte = 0;
        $bitsRead = 0;

        // Read columns in pairs, from right to left
        for ($j = $dimension - 1; $j > 0; $j -= 2) {
            if ($j === 6) {
                // Skip whole column with vertical alignment pattern;
                // saves time and makes the other code proceed more cleanly
                --$j;
            }

            // Read alternatingly from bottom to top then top to bottom
            for ($count = 0; $count < $dimension; ++$count) {
                $i = $readingUp ? $dimension - 1 - $count : $count;

                for ($col = 0; $col < 2; ++$col) {
                    // Ignore bits covered by the function pattern
                    if ($functionPattern->get($j - $col, $i)) {
                        continue;
                    }

                    // Read a bit
                    ++$bitsRead;
                    $currentByte <<= 1;

                    if ($this->bitMatrix->get($j - $col, $i)) {
                        $currentByte |= 1;
                    }

                    // If we've made a whole byte, save it off
                    if ($bitsRead !== 8) {
                        continue;
                    }

                    $result[$resultOffset++] = $currentByte; // (byte)
                    $bitsRead = 0;
                    $currentByte = 0;
                }
            }
            $readingUp ^= true; // readingUp = !readingUp; // switch directions
        }

        if ($resultOffset !== $version->getTotalCodewords()) {
            throw FormatException::getFormatInstance();
        }

        return $result;
    }

    /**
     * Read and cache the QR format information.
     *
     * The format bits are duplicated in two locations in the symbol. The parser
     * reads both copies and uses whichever decodes successfully.
     *
     * @throws FormatException   If neither copy can be decoded.
     * @return FormatInformation Encapsulated format metadata.
     */
    public function readFormatInformation()
    {
        if ($this->parsedFormatInfo !== null) {
            return $this->parsedFormatInfo;
        }

        // Read top-left format info bits
        $formatInfoBits1 = 0;

        for ($i = 0; $i < 6; ++$i) {
            $formatInfoBits1 = $this->copyBit($i, 8, $formatInfoBits1);
        }
        // .. and skip a bit in the timing pattern ...
        $formatInfoBits1 = $this->copyBit(7, 8, $formatInfoBits1);
        $formatInfoBits1 = $this->copyBit(8, 8, $formatInfoBits1);
        $formatInfoBits1 = $this->copyBit(8, 7, $formatInfoBits1);

        // .. and skip a bit in the timing pattern ...
        for ($j = 5; $j >= 0; --$j) {
            $formatInfoBits1 = $this->copyBit(8, $j, $formatInfoBits1);
        }

        // Read the top-right/bottom-left pattern too
        $dimension = $this->bitMatrix->getHeight();
        $formatInfoBits2 = 0;
        $jMin = $dimension - 7;

        for ($j = $dimension - 1; $j >= $jMin; --$j) {
            $formatInfoBits2 = $this->copyBit(8, $j, $formatInfoBits2);
        }

        for ($i = $dimension - 8; $i < $dimension; ++$i) {
            $formatInfoBits2 = $this->copyBit($i, 8, $formatInfoBits2);
        }

        $parsedFormatInfo = FormatInformation::decodeFormatInformation($formatInfoBits1, $formatInfoBits2);

        if ($parsedFormatInfo !== null) {
            return $parsedFormatInfo;
        }

        throw FormatException::getFormatInstance();
    }

    /**
     * Read and cache the QR version information.
     *
     * Versions 1 through 6 are inferred from the symbol dimension; larger
     * symbols carry explicit version bits in two mirrored locations.
     *
     * @throws FormatException If no valid version encoding can be resolved.
     * @return Version         Encapsulated version metadata.
     */
    public function readVersion()
    {
        if ($this->parsedVersion !== null) {
            return $this->parsedVersion;
        }

        $dimension = $this->bitMatrix->getHeight();

        $provisionalVersion = ($dimension - 17) / 4;

        if ($provisionalVersion <= 6) {
            return Version::getVersionForNumber($provisionalVersion);
        }

        // Read top-right version info: 3 wide by 6 tall
        $versionBits = 0;
        $ijMin = $dimension - 11;

        for ($j = 5; $j >= 0; --$j) {
            for ($i = $dimension - 9; $i >= $ijMin; --$i) {
                $versionBits = $this->copyBit($i, $j, $versionBits);
            }
        }

        $theParsedVersion = Version::decodeVersionInformation($versionBits);

        if ($theParsedVersion !== null && $theParsedVersion->getDimensionForVersion() === $dimension) {
            $this->parsedVersion = $theParsedVersion;

            return $theParsedVersion;
        }

        // Hmm, failed. Try bottom left: 6 wide by 3 tall
        $versionBits = 0;

        for ($i = 5; $i >= 0; --$i) {
            for ($j = $dimension - 9; $j >= $ijMin; --$j) {
                $versionBits = $this->copyBit($i, $j, $versionBits);
            }
        }

        $theParsedVersion = Version::decodeVersionInformation($versionBits);

        if ($theParsedVersion !== null && $theParsedVersion->getDimensionForVersion() === $dimension) {
            $this->parsedVersion = $theParsedVersion;

            return $theParsedVersion;
        }

        throw FormatException::getFormatInstance('both version information locations cannot be parsed as the valid encoding of version information');
    }

    /**
     * Reapply the active mask so the matrix returns to its pre-read state.
     *
     * This is used when the decoder needs to retry with mirrored coordinates.
     */
    public function remask(): void
    {
        if ($this->parsedFormatInfo === null) {
            return; // We have no format information, and have no data mask
        }
        $dataMask = AbstractDataMask::forReference($this->parsedFormatInfo->getDataMask());
        $dimension = $this->bitMatrix->getHeight();
        $dataMask->unmaskBitMatrix($this->bitMatrix, $dimension);
    }

    /**
     * Enable mirrored interpretation for subsequent metadata reads.
     *
     * @param bool $mirror Whether reads should use mirrored coordinates.
     */
    public function setMirror($mirror): void
    {
        $parsedVersion = null;
        $parsedFormatInfo = null;
        $this->mirror = $mirror;
    }

    /**
     * Mirror the bit matrix in place.
     *
     * The decoder uses this after a failed first pass to retry as if the
     * symbol had been captured mirrored across the main diagonal.
     */
    public function mirror(): void
    {
        for ($x = 0; $x < $this->bitMatrix->getWidth(); ++$x) {
            for ($y = $x + 1; $y < $this->bitMatrix->getHeight(); ++$y) {
                if ($this->bitMatrix->get($x, $y) === $this->bitMatrix->get($y, $x)) {
                    continue;
                }

                $this->bitMatrix->flip($y, $x);
                $this->bitMatrix->flip($x, $y);
            }
        }
    }

    /**
     * Append one bit from the matrix to an integer accumulator.
     *
     * @param  float|int $i           Row or column coordinate depending on mirror mode.
     * @param  float|int $j           Row or column coordinate depending on mirror mode.
     * @param  int       $versionBits Current bit accumulator.
     * @return int       Updated accumulator.
     */
    private function copyBit(int|float $i, int|float $j, int $versionBits): int
    {
        $bit = $this->mirror ? $this->bitMatrix->get($j, $i) : $this->bitMatrix->get($i, $j);

        return $bit ? ($versionBits << 1) | 0x1 : $versionBits << 1;
    }
}
