<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Generator\Encoder;

use Cline\Qr\Generator\Common\BitArray;
use Cline\Qr\Generator\Common\ErrorCorrectionLevel;
use Cline\Qr\Generator\Common\Mode;
use Cline\Qr\Generator\Common\Version;
use Cline\Qr\Generator\Encoder\Encoder;
use Cline\Qr\Generator\Internal\Exception\WriterException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use SplFixedArray;

use function count;
use function ord;

/**
 * @author Brian Faust <brian@cline.sh>
 * @internal
 */
final class EncoderTest extends TestCase
{
    /** @var array<ReflectionMethod> */
    private array $methods = [];

    protected function setUp(): void
    {
        // Hack to be able to test protected methods
        $reflection = new ReflectionClass(Encoder::class);

        foreach ($reflection->getMethods(ReflectionMethod::IS_STATIC) as $method) {
            $this->methods[$method->getName()] = $method;
        }
    }

    public function test_get_alphanumeric_code(): void
    {
        // The first ten code points are numbers.
        for ($i = 0; $i < 10; ++$i) {
            $this->assertSame($i, $this->methods['getAlphanumericCode']->invoke(null, ord('0') + $i));
        }

        // The next 26 code points are capital alphabet letters.
        for ($i = 10; $i < 36; ++$i) {
            // The first ten code points are numbers
            $this->assertSame($i, $this->methods['getAlphanumericCode']->invoke(null, ord('A') + $i - 10));
        }

        // Others are symbol letters.
        $this->assertSame(36, $this->methods['getAlphanumericCode']->invoke(null, ord(' ')));
        $this->assertSame(37, $this->methods['getAlphanumericCode']->invoke(null, ord('$')));
        $this->assertSame(38, $this->methods['getAlphanumericCode']->invoke(null, ord('%')));
        $this->assertSame(39, $this->methods['getAlphanumericCode']->invoke(null, ord('*')));
        $this->assertSame(40, $this->methods['getAlphanumericCode']->invoke(null, ord('+')));
        $this->assertSame(41, $this->methods['getAlphanumericCode']->invoke(null, ord('-')));
        $this->assertSame(42, $this->methods['getAlphanumericCode']->invoke(null, ord('.')));
        $this->assertSame(43, $this->methods['getAlphanumericCode']->invoke(null, ord('/')));
        $this->assertSame(44, $this->methods['getAlphanumericCode']->invoke(null, ord(':')));

        // Should return -1 for other letters.
        $this->assertSame(-1, $this->methods['getAlphanumericCode']->invoke(null, ord('a')));
        $this->assertSame(-1, $this->methods['getAlphanumericCode']->invoke(null, ord('#')));
        $this->assertSame(-1, $this->methods['getAlphanumericCode']->invoke(null, ord("\0")));
    }

    public function test_choose_mode(): void
    {
        // Empty string
        $this->assertSame(Mode::BYTE, $this->methods['chooseMode']->invoke(null, ''));
        $this->assertSame(Mode::BYTE, $this->methods['chooseMode']->invoke(null, '', 'SHIFT-JIS'));

        // Numeric mode
        $this->assertSame(Mode::NUMERIC, $this->methods['chooseMode']->invoke(null, '0'));
        $this->assertSame(Mode::NUMERIC, $this->methods['chooseMode']->invoke(null, '0123456789'));

        // Alphanumeric mode
        $this->assertSame(Mode::ALPHANUMERIC, $this->methods['chooseMode']->invoke(null, 'A'));
        $this->assertSame(
            Mode::ALPHANUMERIC,
            $this->methods['chooseMode']->invoke(null, '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ $%*+-./:'),
        );

        // 8-bit byte mode
        $this->assertSame(Mode::BYTE, $this->methods['chooseMode']->invoke(null, 'a'));
        $this->assertSame(Mode::BYTE, $this->methods['chooseMode']->invoke(null, '#'));

        // AIUE in Hiragana in SHIFT-JIS
        $this->assertSame(Mode::BYTE, $this->methods['chooseMode']->invoke(null, "\x8\xa\x8\xa\x8\xa\x8\xa6"));

        // Nihon in Kanji in SHIFT-JIS
        $this->assertSame(Mode::BYTE, $this->methods['chooseMode']->invoke(null, "\x9\xf\x9\x7b"));

        // Sou-Utso-Byou in Kanji in SHIFT-JIS
        $this->assertSame(Mode::BYTE, $this->methods['chooseMode']->invoke(null, "\xe\x4\x9\x5\x9\x61"));

        // SHIFT-JIS encoding, content only consists of double-byte kanji characters
        $this->assertSame(Mode::KANJI, $this->methods['chooseMode']->invoke(null, 'あいうえお', 'SHIFT-JIS'));

        // SHIFT-JIS encoding, but content doesn't exclusively consist of kanji characters
        $this->assertSame(Mode::BYTE, $this->methods['chooseMode']->invoke(null, 'あいうえお123', 'SHIFT-JIS'));
    }

    public function test_encode(): void
    {
        $qrCode = Encoder::encode('ABCDEF', ErrorCorrectionLevel::H);
        $expected = "<<\n"
            ." mode: ALPHANUMERIC\n"
            ." ecLevel: H\n"
            ." version: 1\n"
            ." maskPattern: 0\n"
            ." matrix:\n"
            ." 1 1 1 1 1 1 1 0 1 1 1 1 0 0 1 1 1 1 1 1 1\n"
            ." 1 0 0 0 0 0 1 0 0 1 1 1 0 0 1 0 0 0 0 0 1\n"
            ." 1 0 1 1 1 0 1 0 0 1 0 1 1 0 1 0 1 1 1 0 1\n"
            ." 1 0 1 1 1 0 1 0 1 1 1 0 1 0 1 0 1 1 1 0 1\n"
            ." 1 0 1 1 1 0 1 0 0 1 1 1 0 0 1 0 1 1 1 0 1\n"
            ." 1 0 0 0 0 0 1 0 0 1 0 0 0 0 1 0 0 0 0 0 1\n"
            ." 1 1 1 1 1 1 1 0 1 0 1 0 1 0 1 1 1 1 1 1 1\n"
            ." 0 0 0 0 0 0 0 0 0 0 1 0 1 0 0 0 0 0 0 0 0\n"
            ." 0 0 1 0 1 1 1 0 1 1 0 0 1 1 0 0 0 1 0 0 1\n"
            ." 1 0 1 1 1 0 0 1 0 0 0 1 0 1 0 0 0 0 0 0 0\n"
            ." 0 0 1 1 0 0 1 0 1 0 0 0 1 0 1 0 1 0 1 1 0\n"
            ." 1 1 0 1 0 1 0 1 1 1 0 1 0 1 0 0 0 0 0 1 0\n"
            ." 0 0 1 1 0 1 1 1 1 0 0 0 1 0 1 0 1 1 1 1 0\n"
            ." 0 0 0 0 0 0 0 0 1 0 0 1 1 1 0 1 0 1 0 0 0\n"
            ." 1 1 1 1 1 1 1 0 0 0 1 0 1 0 1 1 0 0 0 0 1\n"
            ." 1 0 0 0 0 0 1 0 1 1 1 1 0 1 0 1 1 1 1 0 1\n"
            ." 1 0 1 1 1 0 1 0 1 0 1 1 0 1 0 1 0 0 0 0 1\n"
            ." 1 0 1 1 1 0 1 0 0 1 1 0 1 1 1 1 0 1 0 1 0\n"
            ." 1 0 1 1 1 0 1 0 1 0 0 0 1 0 1 0 1 1 1 0 1\n"
            ." 1 0 0 0 0 0 1 0 0 1 1 0 1 1 0 1 0 0 0 1 1\n"
            ." 1 1 1 1 1 1 1 0 0 0 0 0 0 0 0 0 1 0 1 0 1\n"
            .">>\n";

        $this->assertSame($expected, (string) $qrCode);
    }

    public function test_simple_utf8_eci(): void
    {
        $qrCode = Encoder::encode('hello', ErrorCorrectionLevel::H, 'utf-8');
        $expected = "<<\n"
            ." mode: BYTE\n"
            ." ecLevel: H\n"
            ." version: 1\n"
            ." maskPattern: 3\n"
            ." matrix:\n"
            ." 1 1 1 1 1 1 1 0 0 0 0 0 0 0 1 1 1 1 1 1 1\n"
            ." 1 0 0 0 0 0 1 0 0 0 1 0 1 0 1 0 0 0 0 0 1\n"
            ." 1 0 1 1 1 0 1 0 0 1 0 1 0 0 1 0 1 1 1 0 1\n"
            ." 1 0 1 1 1 0 1 0 0 1 1 0 1 0 1 0 1 1 1 0 1\n"
            ." 1 0 1 1 1 0 1 0 1 0 1 0 1 0 1 0 1 1 1 0 1\n"
            ." 1 0 0 0 0 0 1 0 0 0 0 0 1 0 1 0 0 0 0 0 1\n"
            ." 1 1 1 1 1 1 1 0 1 0 1 0 1 0 1 1 1 1 1 1 1\n"
            ." 0 0 0 0 0 0 0 0 1 1 1 0 0 0 0 0 0 0 0 0 0\n"
            ." 0 0 1 1 0 0 1 1 1 1 0 0 0 1 1 0 1 0 0 0 0\n"
            ." 0 0 1 1 1 0 0 0 0 0 1 1 0 0 0 1 0 1 1 1 0\n"
            ." 0 1 0 1 0 1 1 1 0 1 0 1 0 0 0 0 0 1 1 1 1\n"
            ." 1 1 0 0 1 0 0 1 1 0 0 1 1 1 1 0 1 0 1 1 0\n"
            ." 0 0 0 0 1 0 1 1 1 1 0 0 0 0 0 1 0 0 1 0 0\n"
            ." 0 0 0 0 0 0 0 0 1 1 1 1 0 0 1 1 1 0 0 0 1\n"
            ." 1 1 1 1 1 1 1 0 1 1 1 0 1 0 1 1 0 0 1 0 0\n"
            ." 1 0 0 0 0 0 1 0 0 0 1 0 0 1 1 1 1 1 1 0 1\n"
            ." 1 0 1 1 1 0 1 0 0 1 0 0 0 0 1 1 0 0 0 0 0\n"
            ." 1 0 1 1 1 0 1 0 1 1 1 0 1 0 0 0 1 1 0 0 0\n"
            ." 1 0 1 1 1 0 1 0 1 1 0 0 0 1 0 0 1 0 0 0 0\n"
            ." 1 0 0 0 0 0 1 0 0 0 0 1 1 0 1 0 1 0 1 1 0\n"
            ." 1 1 1 1 1 1 1 0 0 1 0 1 1 1 0 1 1 0 0 0 0\n"
            .">>\n";

        $this->assertSame($expected, (string) $qrCode);
    }

    public function test_simple_utf8_without_eci(): void
    {
        $qrCode = Encoder::encode('hello', ErrorCorrectionLevel::H, 'utf-8', null, false);
        $expected = "<<\n"
            ." mode: BYTE\n"
            ." ecLevel: H\n"
            ." version: 1\n"
            ." maskPattern: 6\n"
            ." matrix:\n"
            ." 1 1 1 1 1 1 1 0 0 0 0 0 1 0 1 1 1 1 1 1 1\n"
            ." 1 0 0 0 0 0 1 0 0 0 0 0 0 0 1 0 0 0 0 0 1\n"
            ." 1 0 1 1 1 0 1 0 1 1 0 1 0 0 1 0 1 1 1 0 1\n"
            ." 1 0 1 1 1 0 1 0 1 0 1 0 0 0 1 0 1 1 1 0 1\n"
            ." 1 0 1 1 1 0 1 0 0 0 1 1 1 0 1 0 1 1 1 0 1\n"
            ." 1 0 0 0 0 0 1 0 0 0 1 0 0 0 1 0 0 0 0 0 1\n"
            ." 1 1 1 1 1 1 1 0 1 0 1 0 1 0 1 1 1 1 1 1 1\n"
            ." 0 0 0 0 0 0 0 0 0 1 1 1 0 0 0 0 0 0 0 0 0\n"
            ." 0 0 0 1 1 0 1 1 0 1 1 1 0 0 0 0 0 1 1 0 0\n"
            ." 1 0 1 0 0 1 0 1 0 1 1 0 0 0 1 1 1 1 1 0 0\n"
            ." 1 1 0 1 0 1 1 0 1 1 0 1 0 1 0 1 0 0 1 1 1\n"
            ." 1 1 0 0 1 1 0 1 0 1 0 0 1 1 0 1 1 0 1 0 0\n"
            ." 0 1 0 0 1 1 1 1 1 0 0 0 0 0 1 1 1 1 0 1 0\n"
            ." 0 0 0 0 0 0 0 0 1 0 1 0 1 1 1 0 0 1 0 1 0\n"
            ." 1 1 1 1 1 1 1 0 1 0 1 0 0 0 1 0 0 0 1 0 0\n"
            ." 1 0 0 0 0 0 1 0 0 1 1 1 0 1 0 0 0 1 1 1 1\n"
            ." 1 0 1 1 1 0 1 0 1 0 1 0 1 0 0 0 1 1 0 1 1\n"
            ." 1 0 1 1 1 0 1 0 1 0 1 1 1 1 0 0 1 0 0 0 0\n"
            ." 1 0 1 1 1 0 1 0 0 0 1 1 0 1 1 1 1 1 1 1 1\n"
            ." 1 0 0 0 0 0 1 0 0 1 1 1 1 1 1 1 1 1 1 1 1\n"
            ." 1 1 1 1 1 1 1 0 0 0 1 0 0 1 0 0 0 0 0 0 0\n"
            .">>\n";

        $this->assertSame($expected, (string) $qrCode);
    }

    public function test_append_mode_info(): void
    {
        $bits = new BitArray();
        $this->methods['appendModeInfo']->invoke(null, Mode::NUMERIC, $bits);
        $this->assertSame(' ...X', (string) $bits);
    }

    public function test_append_length_info(): void
    {
        // 1 letter (1/1), 10 bits.
        $bits = new BitArray();
        $this->methods['appendLengthInfo']->invoke(
            null,
            1,
            Version::getVersionForNumber(1),
            Mode::NUMERIC,
            $bits,
        );
        $this->assertSame(' ........ .X', (string) $bits);

        // 2 letters (2/1), 11 bits.
        $bits = new BitArray();
        $this->methods['appendLengthInfo']->invoke(
            null,
            2,
            Version::getVersionForNumber(10),
            Mode::ALPHANUMERIC,
            $bits,
        );
        $this->assertSame(' ........ .X.', (string) $bits);

        // 255 letters (255/1), 16 bits.
        $bits = new BitArray();
        $this->methods['appendLengthInfo']->invoke(
            null,
            255,
            Version::getVersionForNumber(27),
            Mode::BYTE,
            $bits,
        );
        $this->assertSame(' ........ XXXXXXXX', (string) $bits);

        // 512 letters (1024/2), 12 bits.
        $bits = new BitArray();
        $this->methods['appendLengthInfo']->invoke(
            null,
            512,
            Version::getVersionForNumber(40),
            Mode::KANJI,
            $bits,
        );
        $this->assertSame(' ..X..... ....', (string) $bits);
    }

    public function test_append_bytes(): void
    {
        // Should use appendNumericBytes.
        // 1 = 01 = 0001 in 4 bits.
        $bits = new BitArray();
        $this->methods['appendBytes']->invoke(
            null,
            '1',
            Mode::NUMERIC,
            $bits,
            Encoder::DEFAULT_BYTE_MODE_ENCODING,
        );
        $this->assertSame(' ...X', (string) $bits);

        // Should use appendAlphaNumericBytes.
        // A = 10 = 0xa = 001010 in 6 bits.
        $bits = new BitArray();
        $this->methods['appendBytes']->invoke(
            null,
            'A',
            Mode::ALPHANUMERIC,
            $bits,
            Encoder::DEFAULT_BYTE_MODE_ENCODING,
        );
        $this->assertSame(' ..X.X.', (string) $bits);

        // Should use append8BitBytes.
        // 0x61, 0x62, 0x63
        $bits = new BitArray();
        $this->methods['appendBytes']->invoke(
            null,
            'abc',
            Mode::BYTE,
            $bits,
            Encoder::DEFAULT_BYTE_MODE_ENCODING,
        );
        $this->assertSame(' .XX....X .XX...X. .XX...XX', (string) $bits);

        // Should use appendKanjiBytes.
        // 0x93, 0x5f :点
        $bits = new BitArray();
        $this->methods['appendBytes']->invoke(
            null,
            '点',
            Mode::KANJI,
            $bits,
            Encoder::DEFAULT_BYTE_MODE_ENCODING,
        );
        $this->assertSame(' .XX.XX.. XXXXX', (string) $bits);

        // Lower letters such as 'a' cannot be encoded in alphanumeric mode.
        $this->expectException(WriterException::class);
        $this->methods['appendBytes']->invoke(
            null,
            'a',
            Mode::ALPHANUMERIC,
            $bits,
            Encoder::DEFAULT_BYTE_MODE_ENCODING,
        );
    }

    public function test_terminate_bits(): void
    {
        $bits = new BitArray();
        $this->methods['terminateBits']->invoke(null, 0, $bits);
        $this->assertSame('', (string) $bits);

        $bits = new BitArray();
        $this->methods['terminateBits']->invoke(null, 1, $bits);
        $this->assertSame(' ........', (string) $bits);

        $bits = new BitArray();
        $bits->appendBits(0, 3);
        $this->methods['terminateBits']->invoke(null, 1, $bits);
        $this->assertSame(' ........', (string) $bits);

        $bits = new BitArray();
        $bits->appendBits(0, 5);
        $this->methods['terminateBits']->invoke(null, 1, $bits);
        $this->assertSame(' ........', (string) $bits);

        $bits = new BitArray();
        $bits->appendBits(0, 8);
        $this->methods['terminateBits']->invoke(null, 1, $bits);
        $this->assertSame(' ........', (string) $bits);

        $bits = new BitArray();
        $this->methods['terminateBits']->invoke(null, 2, $bits);
        $this->assertSame(' ........ XXX.XX..', (string) $bits);

        $bits = new BitArray();
        $bits->appendBits(0, 1);
        $this->methods['terminateBits']->invoke(null, 3, $bits);
        $this->assertSame(' ........ XXX.XX.. ...X...X', (string) $bits);
    }

    public function test_get_num_data_bytes_and_num_ec_bytes_for_block_id(): void
    {
        // Version 1-H.
        [$numDataBytes, $numEcBytes] = $this->methods['getNumDataBytesAndNumEcBytesForBlockId']
            ->invoke(null, 26, 9, 1, 0);
        $this->assertSame(9, $numDataBytes);
        $this->assertSame(17, $numEcBytes);

        // Version 3-H.  2 blocks.
        [$numDataBytes, $numEcBytes] = $this->methods['getNumDataBytesAndNumEcBytesForBlockId']
            ->invoke(null, 70, 26, 2, 0);
        $this->assertSame(13, $numDataBytes);
        $this->assertSame(22, $numEcBytes);
        [$numDataBytes, $numEcBytes] = $this->methods['getNumDataBytesAndNumEcBytesForBlockId']
            ->invoke(null, 70, 26, 2, 1);
        $this->assertSame(13, $numDataBytes);
        $this->assertSame(22, $numEcBytes);

        // Version 7-H. (4 + 1) blocks.
        [$numDataBytes, $numEcBytes] = $this->methods['getNumDataBytesAndNumEcBytesForBlockId']
            ->invoke(null, 196, 66, 5, 0);
        $this->assertSame(13, $numDataBytes);
        $this->assertSame(26, $numEcBytes);
        [$numDataBytes, $numEcBytes] = $this->methods['getNumDataBytesAndNumEcBytesForBlockId']
            ->invoke(null, 196, 66, 5, 4);
        $this->assertSame(14, $numDataBytes);
        $this->assertSame(26, $numEcBytes);

        // Version 40-H. (20 + 61) blocks.
        [$numDataBytes, $numEcBytes] = $this->methods['getNumDataBytesAndNumEcBytesForBlockId']
            ->invoke(null, 3_706, 1_276, 81, 0);
        $this->assertSame(15, $numDataBytes);
        $this->assertSame(30, $numEcBytes);
        [$numDataBytes, $numEcBytes] = $this->methods['getNumDataBytesAndNumEcBytesForBlockId']
            ->invoke(null, 3_706, 1_276, 81, 20);
        $this->assertSame(16, $numDataBytes);
        $this->assertSame(30, $numEcBytes);
        [$numDataBytes, $numEcBytes] = $this->methods['getNumDataBytesAndNumEcBytesForBlockId']
            ->invoke(null, 3_706, 1_276, 81, 80);
        $this->assertSame(16, $numDataBytes);
        $this->assertSame(30, $numEcBytes);
    }

    public function test_interleave_with_ec_bytes(): void
    {
        $dataBytes = SplFixedArray::fromArray([32, 65, 205, 69, 41, 220, 46, 128, 236], false);
        $in = new BitArray();

        foreach ($dataBytes as $dataByte) {
            $in->appendBits($dataByte, 8);
        }

        $outBits = $this->methods['interleaveWithEcBytes']->invoke(null, $in, 26, 9, 1);
        $expected = SplFixedArray::fromArray([
            // Data bytes.
            32, 65, 205, 69, 41, 220, 46, 128, 236,
            // Error correction bytes.
            42, 159, 74, 221, 244, 169, 239, 150, 138, 70, 237, 85, 224, 96, 74, 219, 61,
        ], false);

        $out = $outBits->toBytes(0, count($expected));

        $this->assertEquals($expected, $out);
    }

    public function test_append_numeric_bytes(): void
    {
        // 1 = 01 = 0001 in 4 bits.
        $bits = new BitArray();
        $this->methods['appendNumericBytes']->invoke(null, '1', $bits);
        $this->assertSame(' ...X', (string) $bits);

        // 12 = 0xc = 0001100 in 7 bits.
        $bits = new BitArray();
        $this->methods['appendNumericBytes']->invoke(null, '12', $bits);
        $this->assertSame(' ...XX..', (string) $bits);

        // 123 = 0x7b = 0001111011 in 10 bits.
        $bits = new BitArray();
        $this->methods['appendNumericBytes']->invoke(null, '123', $bits);
        $this->assertSame(' ...XXXX. XX', (string) $bits);

        // 1234 = "123" + "4" = 0001111011 + 0100 in 14 bits.
        $bits = new BitArray();
        $this->methods['appendNumericBytes']->invoke(null, '1234', $bits);
        $this->assertSame(' ...XXXX. XX.X..', (string) $bits);

        // Empty
        $bits = new BitArray();
        $this->methods['appendNumericBytes']->invoke(null, '', $bits);
        $this->assertSame('', (string) $bits);
    }

    public function test_append_alphanumeric_bytes(): void
    {
        $bits = new BitArray();
        $this->methods['appendAlphanumericBytes']->invoke(null, 'A', $bits);
        $this->assertSame(' ..X.X.', (string) $bits);

        $bits = new BitArray();
        $this->methods['appendAlphanumericBytes']->invoke(null, 'AB', $bits);
        $this->assertSame(' ..XXX..X X.X', (string) $bits);

        $bits = new BitArray();
        $this->methods['appendAlphanumericBytes']->invoke(null, 'ABC', $bits);
        $this->assertSame(' ..XXX..X X.X..XX. .', (string) $bits);

        // Empty
        $bits = new BitArray();
        $this->methods['appendAlphanumericBytes']->invoke(null, '', $bits);
        $this->assertSame('', (string) $bits);

        // Invalid data
        $this->expectException(WriterException::class);
        $bits = new BitArray();
        $this->methods['appendAlphanumericBytes']->invoke(null, 'abc', $bits);
    }

    public function test_append8_bit_bytes(): void
    {
        // 0x61, 0x62, 0x63
        $bits = new BitArray();
        $this->methods['append8BitBytes']->invoke(null, 'abc', $bits, Encoder::DEFAULT_BYTE_MODE_ENCODING);
        $this->assertSame(' .XX....X .XX...X. .XX...XX', (string) $bits);

        // Empty
        $bits = new BitArray();
        $this->methods['append8BitBytes']->invoke(null, '', $bits, Encoder::DEFAULT_BYTE_MODE_ENCODING);
        $this->assertSame('', (string) $bits);
    }

    public function test_append_kanji_bytes(): void
    {
        // Numbers are from page 21 of JISX0510:2004 点 and 茗
        $bits = new BitArray();
        $this->methods['appendKanjiBytes']->invoke(null, '点', $bits);
        $this->assertSame(' .XX.XX.. XXXXX', (string) $bits);

        $this->methods['appendKanjiBytes']->invoke(null, '茗', $bits);
        $this->assertSame(' .XX.XX.. XXXXXXX. X.X.X.X. X.', (string) $bits);
    }

    public function test_generate_ec_bytes(): void
    {
        // Numbers are from http://www.swetake.com/qr/qr3.html and
        // http://www.swetake.com/qr/qr9.html
        $dataBytes = SplFixedArray::fromArray([32, 65, 205, 69, 41, 220, 46, 128, 236], false);
        $ecBytes = $this->methods['generateEcBytes']->invoke(null, $dataBytes, 17);
        $expected = SplFixedArray::fromArray(
            [42, 159, 74, 221, 244, 169, 239, 150, 138, 70, 237, 85, 224, 96, 74, 219, 61],
            false,
        );
        $this->assertEquals($expected, $ecBytes);

        $dataBytes = SplFixedArray::fromArray(
            [67, 70, 22, 38, 54, 70, 86, 102, 118, 134, 150, 166, 182, 198, 214],
            false,
        );
        $ecBytes = $this->methods['generateEcBytes']->invoke(null, $dataBytes, 18);
        $expected = SplFixedArray::fromArray(
            [175, 80, 155, 64, 178, 45, 214, 233, 65, 209, 12, 155, 117, 31, 140, 214, 27, 187],
            false,
        );
        $this->assertEquals($expected, $ecBytes);

        // High-order zero coefficient case.
        $dataBytes = SplFixedArray::fromArray([32, 49, 205, 69, 42, 20, 0, 236, 17], false);
        $ecBytes = $this->methods['generateEcBytes']->invoke(null, $dataBytes, 17);
        $expected = SplFixedArray::fromArray(
            [0, 3, 130, 179, 194, 0, 55, 211, 110, 79, 98, 72, 170, 96, 211, 137, 213],
            false,
        );
        $this->assertEquals($expected, $ecBytes);
    }
}
