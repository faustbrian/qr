<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Generator\Encoder;

use Cline\Qr\Generator\Common\BitArray;
use Cline\Qr\Generator\Common\CharacterSetEci;
use Cline\Qr\Generator\Common\ErrorCorrectionLevel;
use Cline\Qr\Generator\Common\Mode;
use Cline\Qr\Generator\Common\ReedSolomonCodec;
use Cline\Qr\Generator\Common\Version;
use Cline\Qr\Generator\Internal\Exception\WriterException;
use SplFixedArray;

use const PHP_INT_MAX;

use function count;
use function ctype_digit;
use function iconv;
use function iconv_strlen;
use function intdiv;
use function max;
use function mb_strlen;
use function ord;
use function strcasecmp;
use function strlen;
use function strspn;

/**
 * Orchestrates QR symbol generation from content to final matrix.
 *
 * The encoder selects the best mode, version, and mask pattern for the input,
 * then assembles the header, payload, parity bytes, and final module matrix.
 * It caches Reed-Solomon codecs because the field layout only depends on the
 * data/parity block sizes, which repeat across many inputs.
 * @author Brian Faust <brian@cline.sh>
 */
final class Encoder
{
    /**
     * Default byte encoding used when callers do not request another charset.
     */
    public const string DEFAULT_BYTE_MODE_ENCODING = 'ISO-8859-1';

    /**
     * @deprecated use DEFAULT_BYTE_MODE_ENCODING
     */
    public const mixed DEFAULT_BYTE_MODE_ECODING = self::DEFAULT_BYTE_MODE_ENCODING;

    /**
     * Allowed characters for the Alphanumeric Mode.
     */
    private const string ALPHANUMERIC_CHARS = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ $%*+-./:';

    /**
     * The original table is defined in the table 5 of JISX0510:2004 (p.19).
     */
    private const array ALPHANUMERIC_TABLE = [
        -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1,  // 0x00-0x0f
        -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1,  // 0x10-0x1f
        36, -1, -1, -1, 37, 38, -1, -1, -1, -1, 39, 40, -1, 41, 42, 43,  // 0x20-0x2f
        0,   1,  2,  3,  4,  5,  6,  7,  8,  9, 44, -1, -1, -1, -1, -1,  // 0x30-0x3f
        -1, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24,  // 0x40-0x4f
        25, 26, 27, 28, 29, 30, 31, 32, 33, 34, 35, -1, -1, -1, -1, -1,  // 0x50-0x5f
    ];

    /**
     * Codec cache.
     *
     * @var array<string, ReedSolomonCodec>
     */
    private static array $codecs = [];

    /**
     * Encode content into a QR matrix.
     *
     * The method resolves mode, version, and mask pattern automatically unless
     * a version is forced. It also prefixes an ECI segment when needed so the
     * output remains readable by scanners that support the requested charset.
     */
    public static function encode(
        string $content,
        ErrorCorrectionLevel $ecLevel,
        string $encoding = self::DEFAULT_BYTE_MODE_ENCODING,
        ?Version $forcedVersion = null,
        // Barcode scanner might not be able to read the encoded message of the QR code with the prefix ECI of UTF-8
        bool $prefixEci = true,
    ): QrCode {
        // Pick an encoding mode appropriate for the content. Note that this
        // will not attempt to use multiple modes / segments even if that were
        // more efficient. Would be nice.
        $mode = self::chooseMode($content, $encoding);

        // This will store the header information, like mode and length, as well
        // as "header" segments like an ECI segment.
        $headerBits = new BitArray();

        // Append ECI segment if applicable
        if ($prefixEci && Mode::BYTE === $mode && self::DEFAULT_BYTE_MODE_ENCODING !== $encoding) {
            $eci = CharacterSetEci::getCharacterSetEciByName($encoding);

            if (null !== $eci) {
                self::appendEci($eci, $headerBits);
            }
        }

        // (With ECI in place,) Write the mode marker
        self::appendModeInfo($mode, $headerBits);

        // Collect data within the main segment, separately, to count its size
        // if needed. Don't add it to main payload yet.
        $dataBits = new BitArray();
        self::appendBytes($content, $mode, $dataBits, $encoding);

        // Hard part: need to know version to know how many bits length takes.
        // But need to know how many bits it takes to know version. First we
        // take a guess at version by assuming version will be the minimum, 1:
        $provisionalBitsNeeded = $headerBits->getSize()
            + $mode->getCharacterCountBits(Version::getVersionForNumber(1))
            + $dataBits->getSize();
        $provisionalVersion = self::chooseVersion($provisionalBitsNeeded, $ecLevel);

        // Use that guess to calculate the right version. I am still not sure
        // this works in 100% of cases.
        $bitsNeeded = $headerBits->getSize()
            + $mode->getCharacterCountBits($provisionalVersion)
            + $dataBits->getSize();
        $version = self::chooseVersion($bitsNeeded, $ecLevel);

        if (null !== $forcedVersion) {
            // Forced version check
            if ($version->getVersionNumber() > $forcedVersion->getVersionNumber()) {
                throw WriterException::withMessage(
                    'Invalid version! Calculated version: '
                    .$version->getVersionNumber()
                    .', requested version: '
                    .$forcedVersion->getVersionNumber(),
                );
            }

            // Calculated minimum version is same or equal as forced version
            $version = $forcedVersion;
        }

        $headerAndDataBits = new BitArray();
        $headerAndDataBits->appendBitArray($headerBits);

        // Find "length" of main segment and write it.
        $numLetters = match ($mode) {
            Mode::BYTE => $dataBits->getSizeInBytes(),
            Mode::NUMERIC, Mode::ALPHANUMERIC => mb_strlen($content),
            Mode::KANJI => iconv_strlen($content, 'utf-8'),
        };
        self::appendLengthInfo($numLetters, $version, $mode, $headerAndDataBits);

        // Put data together into the overall payload.
        $headerAndDataBits->appendBitArray($dataBits);
        $ecBlocks = $version->getEcBlocksForLevel($ecLevel);
        $numDataBytes = $version->getTotalCodewords() - $ecBlocks->getTotalEcCodewords();

        // Terminate the bits properly.
        self::terminateBits($numDataBytes, $headerAndDataBits);

        // Interleave data bits with error correction code.
        $finalBits = self::interleaveWithEcBytes(
            $headerAndDataBits,
            $version->getTotalCodewords(),
            $numDataBytes,
            $ecBlocks->getNumBlocks(),
        );

        // Choose the mask pattern.
        $dimension = $version->getDimensionForVersion();
        $matrix = new ByteMatrix($dimension, $dimension);
        $maskPattern = self::chooseMaskPattern($finalBits, $ecLevel, $version, $matrix);

        // Build the matrix.
        MatrixUtil::buildMatrix($finalBits, $ecLevel, $version, $maskPattern, $matrix);

        return new QrCode($mode, $ecLevel, $version, $maskPattern, $matrix);
    }

    /**
     * Map one ASCII byte to its QR alphanumeric code.
     */
    private static function getAlphanumericCode(int $byte): int
    {
        return self::ALPHANUMERIC_TABLE[$byte] ?? -1;
    }

    /**
     * Select the most compact mode that can represent the content.
     */
    private static function chooseMode(string $content, ?string $encoding = null): Mode
    {
        if ('' === $content) {
            return Mode::BYTE;
        }

        if (null !== $encoding && 0 === strcasecmp($encoding, 'SHIFT-JIS')) {
            return self::isOnlyDoubleByteKanji($content) ? Mode::KANJI : Mode::BYTE;
        }

        if (ctype_digit($content)) {
            return Mode::NUMERIC;
        }

        if (self::isOnlyAlphanumeric($content)) {
            return Mode::ALPHANUMERIC;
        }

        return Mode::BYTE;
    }

    /**
     * Score a matrix using the QR mask penalty rules.
     */
    private static function calculateMaskPenalty(ByteMatrix $matrix): int
    {
        return
            MaskUtil::applyMaskPenaltyRule1($matrix)
            + MaskUtil::applyMaskPenaltyRule2($matrix)
            + MaskUtil::applyMaskPenaltyRule3($matrix)
            + MaskUtil::applyMaskPenaltyRule4($matrix);
    }

    /**
     * Check whether the payload can be encoded as double-byte Kanji.
     *
     * The method performs a best-effort Shift-JIS round-trip because QR Kanji
     * mode is defined in terms of that encoding.
     */
    private static function isOnlyDoubleByteKanji(string $content): bool
    {
        $bytes = iconv('utf-8', 'SHIFT-JIS', $content);

        if (false === $bytes) {
            return false;
        }

        $length = strlen($bytes);

        if (0 !== $length % 2) {
            return false;
        }

        for ($i = 0; $i < $length; $i += 2) {
            $byte = ord($bytes[$i]);

            if (($byte < 0x81 || $byte > 0x9F) && $byte < 0xE0 || $byte > 0xEB) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check whether the payload fits the QR alphanumeric alphabet.
     */
    private static function isOnlyAlphanumeric(string $content): bool
    {
        return mb_strlen($content) === strspn($content, self::ALPHANUMERIC_CHARS);
    }

    /**
     * Try every mask pattern and return the lowest-penalty candidate.
     */
    private static function chooseMaskPattern(
        BitArray $bits,
        ErrorCorrectionLevel $ecLevel,
        Version $version,
        ByteMatrix $matrix,
    ): int {
        $minPenalty = PHP_INT_MAX;
        $bestMaskPattern = -1;

        for ($maskPattern = 0; $maskPattern < QrCode::NUM_MASK_PATTERNS; ++$maskPattern) {
            MatrixUtil::buildMatrix($bits, $ecLevel, $version, $maskPattern, $matrix);
            $penalty = self::calculateMaskPenalty($matrix);

            if ($penalty >= $minPenalty) {
                continue;
            }

            $minPenalty = $penalty;
            $bestMaskPattern = $maskPattern;
        }

        return $bestMaskPattern;
    }

    /**
     * Choose the smallest version that can hold the requested payload.
     *
     * @throws WriterException if data is too big
     */
    private static function chooseVersion(int $numInputBits, ErrorCorrectionLevel $ecLevel): Version
    {
        for ($versionNum = 1; $versionNum <= 40; ++$versionNum) {
            $version = Version::getVersionForNumber($versionNum);
            $numBytes = $version->getTotalCodewords();

            $ecBlocks = $version->getEcBlocksForLevel($ecLevel);
            $numEcBytes = $ecBlocks->getTotalEcCodewords();

            $numDataBytes = $numBytes - $numEcBytes;
            $totalInputBytes = intdiv($numInputBits + 8, 8);

            if ($numDataBytes >= $totalInputBytes) {
                return $version;
            }
        }

        throw WriterException::withMessage('Data too big');
    }

    /**
     * Pad the payload to the exact data capacity required by the version.
     *
     * @throws WriterException if bits size does not equal the capacity
     * @throws WriterException if data bits cannot fit in the QR code
     */
    private static function terminateBits(int $numDataBytes, BitArray $bits): void
    {
        $capacity = $numDataBytes << 3;

        if ($bits->getSize() > $capacity) {
            throw WriterException::withMessage('Data bits cannot fit in the QR code');
        }

        for ($i = 0; $i < 4 && $bits->getSize() < $capacity; ++$i) {
            $bits->appendBit(false);
        }

        $numBitsInLastByte = $bits->getSize() & 0x7;

        if ($numBitsInLastByte > 0) {
            for ($i = $numBitsInLastByte; $i < 8; ++$i) {
                $bits->appendBit(false);
            }
        }

        $numPaddingBytes = $numDataBytes - $bits->getSizeInBytes();

        for ($i = 0; $i < $numPaddingBytes; ++$i) {
            $bits->appendBits(0 === ($i & 0x1) ? 0xEC : 0x11, 8);
        }

        if ($bits->getSize() !== $capacity) {
            throw WriterException::withMessage('Bits size does not equal capacity');
        }
    }

    /**
     * Resolve the data and parity sizes for one Reed-Solomon block.
     *
     * The QR versions can split blocks into two size groups. This helper keeps
     * the group arithmetic in one place so interleaving can work with a single
     * block layout description.
     *
     * @throws WriterException if block ID is too large
     * @throws WriterException if EC bytes mismatch
     * @throws WriterException if RS blocks mismatch
     * @throws WriterException if total bytes mismatch
     * @return array<int>
     */
    private static function getNumDataBytesAndNumEcBytesForBlockId(
        int $numTotalBytes,
        int $numDataBytes,
        int $numRsBlocks,
        int $blockId,
    ): array {
        if ($blockId >= $numRsBlocks) {
            throw WriterException::withMessage('Block ID too large');
        }

        $numRsBlocksInGroup2 = $numTotalBytes % $numRsBlocks;
        $numRsBlocksInGroup1 = $numRsBlocks - $numRsBlocksInGroup2;
        $numTotalBytesInGroup1 = intdiv($numTotalBytes, $numRsBlocks);
        $numTotalBytesInGroup2 = $numTotalBytesInGroup1 + 1;
        $numDataBytesInGroup1 = intdiv($numDataBytes, $numRsBlocks);
        $numDataBytesInGroup2 = $numDataBytesInGroup1 + 1;
        $numEcBytesInGroup1 = $numTotalBytesInGroup1 - $numDataBytesInGroup1;
        $numEcBytesInGroup2 = $numTotalBytesInGroup2 - $numDataBytesInGroup2;

        if ($numEcBytesInGroup1 !== $numEcBytesInGroup2) {
            throw WriterException::withMessage('EC bytes mismatch');
        }

        if ($numRsBlocks !== $numRsBlocksInGroup1 + $numRsBlocksInGroup2) {
            throw WriterException::withMessage('RS blocks mismatch');
        }

        if ($numTotalBytes !==
            (($numDataBytesInGroup1 + $numEcBytesInGroup1) * $numRsBlocksInGroup1)
            + (($numDataBytesInGroup2 + $numEcBytesInGroup2) * $numRsBlocksInGroup2)
        ) {
            throw WriterException::withMessage('Total bytes mismatch');
        }

        if ($blockId < $numRsBlocksInGroup1) {
            return [$numDataBytesInGroup1, $numEcBytesInGroup1];
        }

        return [$numDataBytesInGroup2, $numEcBytesInGroup2];
    }

    /**
     * Interleave data and parity bytes into the final transmission order.
     *
     * @throws WriterException if an interleaving error occurs
     * @throws WriterException if data bytes does not match offset
     * @throws WriterException if number of bits and data bytes does not match
     */
    private static function interleaveWithEcBytes(
        BitArray $bits,
        int $numTotalBytes,
        int $numDataBytes,
        int $numRsBlocks,
    ): BitArray {
        if ($bits->getSizeInBytes() !== $numDataBytes) {
            throw WriterException::withMessage('Number of bits and data bytes does not match');
        }

        $dataBytesOffset = 0;
        $maxNumDataBytes = 0;
        $maxNumEcBytes = 0;

        $blocks = new SplFixedArray($numRsBlocks);

        for ($i = 0; $i < $numRsBlocks; ++$i) {
            [$numDataBytesInBlock, $numEcBytesInBlock] = self::getNumDataBytesAndNumEcBytesForBlockId(
                $numTotalBytes,
                $numDataBytes,
                $numRsBlocks,
                $i,
            );

            $size = $numDataBytesInBlock;
            $dataBytes = $bits->toBytes(8 * $dataBytesOffset, $size);
            $ecBytes = self::generateEcBytes($dataBytes, $numEcBytesInBlock);
            $blocks[$i] = new BlockPair($dataBytes, $ecBytes);

            $maxNumDataBytes = max($maxNumDataBytes, $size);
            $maxNumEcBytes = max($maxNumEcBytes, count($ecBytes));
            $dataBytesOffset += $numDataBytesInBlock;
        }

        if ($numDataBytes !== $dataBytesOffset) {
            throw WriterException::withMessage('Data bytes does not match offset');
        }

        $result = new BitArray();

        for ($i = 0; $i < $maxNumDataBytes; ++$i) {
            foreach ($blocks as $block) {
                $dataBytes = $block->getDataBytes();

                if ($i >= count($dataBytes)) {
                    continue;
                }

                $result->appendBits($dataBytes[$i], 8);
            }
        }

        for ($i = 0; $i < $maxNumEcBytes; ++$i) {
            foreach ($blocks as $block) {
                $ecBytes = $block->getErrorCorrectionBytes();

                if ($i >= count($ecBytes)) {
                    continue;
                }

                $result->appendBits($ecBytes[$i], 8);
            }
        }

        if ($numTotalBytes !== $result->getSizeInBytes()) {
            throw WriterException::withMessage(
                'Interleaving error: '.$numTotalBytes.' and '.$result->getSizeInBytes().' differ',
            );
        }

        return $result;
    }

    /**
     * Generate Reed-Solomon parity bytes for one block.
     *
     * @param  SplFixedArray<int> $dataBytes
     * @return SplFixedArray<int>
     */
    private static function generateEcBytes(SplFixedArray $dataBytes, int $numEcBytesInBlock): SplFixedArray
    {
        $numDataBytes = count($dataBytes);
        $toEncode = new SplFixedArray($numDataBytes + $numEcBytesInBlock);

        for ($i = 0; $i < $numDataBytes; ++$i) {
            $toEncode[$i] = $dataBytes[$i];
        }

        $ecBytes = new SplFixedArray($numEcBytesInBlock);
        $codec = self::getCodec($numDataBytes, $numEcBytesInBlock);
        $codec->encode($toEncode, $ecBytes);

        return $ecBytes;
    }

    /**
     * Resolve a cached Reed-Solomon codec for a block shape.
     */
    private static function getCodec(int $numDataBytes, int $numEcBytesInBlock): ReedSolomonCodec
    {
        $cacheId = $numDataBytes.'-'.$numEcBytesInBlock;

        if (isset(self::$codecs[$cacheId])) {
            return self::$codecs[$cacheId];
        }

        return self::$codecs[$cacheId] = new ReedSolomonCodec(
            8,
            0x1_1D,
            0,
            1,
            $numEcBytesInBlock,
            255 - $numDataBytes - $numEcBytesInBlock,
        );
    }

    /**
     * Append the 4-bit mode indicator to the header.
     */
    private static function appendModeInfo(Mode $mode, BitArray $bits): void
    {
        $bits->appendBits($mode->getBits(), 4);
    }

    /**
     * Append the character-count field for the current mode and version.
     *
     * @throws WriterException if num letters is bigger than expected
     */
    private static function appendLengthInfo(int $numLetters, Version $version, Mode $mode, BitArray $bits): void
    {
        $numBits = $mode->getCharacterCountBits($version);

        if ($numLetters >= (1 << $numBits)) {
            throw WriterException::withMessage($numLetters.' is bigger than '.((1 << $numBits) - 1));
        }

        $bits->appendBits($numLetters, $numBits);
    }

    /**
     * Append payload bits using the selected QR mode.
     */
    private static function appendBytes(string $content, Mode $mode, BitArray $bits, string $encoding): void
    {
        match ($mode) {
            Mode::NUMERIC => self::appendNumericBytes($content, $bits),
            Mode::ALPHANUMERIC => self::appendAlphanumericBytes($content, $bits),
            Mode::BYTE => self::append8BitBytes($content, $bits, $encoding),
            Mode::KANJI => self::appendKanjiBytes($content, $bits),
        };
    }

    /**
     * Append numeric payload bits using the QR numeric packing rules.
     */
    private static function appendNumericBytes(string $content, BitArray $bits): void
    {
        $length = mb_strlen($content);
        $i = 0;

        while ($i < $length) {
            $num1 = (int) $content[$i];

            if ($i + 2 < $length) {
                // Encode three numeric letters in ten bits.
                $num2 = (int) $content[$i + 1];
                $num3 = (int) $content[$i + 2];
                $bits->appendBits($num1 * 100 + $num2 * 10 + $num3, 10);
                $i += 3;
            } elseif ($i + 1 < $length) {
                // Encode two numeric letters in seven bits.
                $num2 = (int) $content[$i + 1];
                $bits->appendBits($num1 * 10 + $num2, 7);
                $i += 2;
            } else {
                // Encode one numeric letter in four bits.
                $bits->appendBits($num1, 4);
                ++$i;
            }
        }
    }

    /**
     * Append alphanumeric payload bits using the QR pair packing rules.
     *
     * @throws WriterException if an invalid alphanumeric code was found
     */
    private static function appendAlphanumericBytes(string $content, BitArray $bits): void
    {
        $length = mb_strlen($content);
        $i = 0;

        while ($i < $length) {
            $code1 = self::getAlphanumericCode(ord($content[$i]));

            if (-1 === $code1) {
                throw WriterException::withMessage('Invalid alphanumeric code');
            }

            if ($i + 1 < $length) {
                $code2 = self::getAlphanumericCode(ord($content[$i + 1]));

                if (-1 === $code2) {
                    throw WriterException::withMessage('Invalid alphanumeric code');
                }

                // Encode two alphanumeric letters in 11 bits.
                $bits->appendBits($code1 * 45 + $code2, 11);
                $i += 2;
            } else {
                // Encode one alphanumeric letter in six bits.
                $bits->appendBits($code1, 6);
                ++$i;
            }
        }
    }

    /**
     * Append byte-mode payload bits after transcoding the content.
     *
     * @throws WriterException if content cannot be encoded to target encoding
     */
    private static function append8BitBytes(string $content, BitArray $bits, string $encoding): void
    {
        $bytes = iconv('utf-8', $encoding, $content);

        if (false === $bytes) {
            throw WriterException::withMessage('Could not encode content to '.$encoding);
        }

        $length = strlen($bytes);

        for ($i = 0; $i < $length; ++$i) {
            $bits->appendBits(ord($bytes[$i]), 8);
        }
    }

    /**
     * Append Kanji-mode payload bits using the Shift-JIS-based QR encoding.
     *
     * @throws WriterException if an invalid byte sequence occurs
     * @throws WriterException if content does not seem to be encoded in SHIFT-JIS
     */
    private static function appendKanjiBytes(string $content, BitArray $bits): void
    {
        $bytes = iconv('utf-8', 'SHIFT-JIS', $content);

        if (false === $bytes) {
            throw WriterException::withMessage('Content could not be converted to SHIFT-JIS');
        }

        if (strlen($bytes) % 2 > 0) {
            // We just do a simple length check here. The for loop will check
            // individual characters.
            throw WriterException::withMessage('Content does not seem to be encoded in SHIFT-JIS');
        }

        $length = strlen($bytes);

        for ($i = 0; $i < $length; $i += 2) {
            $byte1 = ord($bytes[$i]);
            $byte2 = ord($bytes[$i + 1]);
            $code = ($byte1 << 8) | $byte2;

            if ($code >= 0x81_40 && $code <= 0x9F_FC) {
                $subtracted = $code - 0x81_40;
            } elseif ($code >= 0xE0_40 && $code <= 0xEB_BF) {
                $subtracted = $code - 0xC1_40;
            } else {
                throw WriterException::withMessage('Invalid byte sequence');
            }

            $encoded = (($subtracted >> 8) * 0xC0) + ($subtracted & 0xFF);

            $bits->appendBits($encoded, 13);
        }
    }

    /**
     * Prefix the payload with an ECI segment for non-default encodings.
     */
    private static function appendEci(CharacterSetEci $eci, BitArray $bits): void
    {
        $mode = Mode::ECI;
        $bits->appendBits($mode->getBits(), 4);
        $bits->appendBits($eci->getValue(), 8);
    }
}
