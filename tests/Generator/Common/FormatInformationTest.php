<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Generator\Common;

use Cline\Qr\Generator\Common\ErrorCorrectionLevel;
use Cline\Qr\Generator\Common\FormatInformation;
use PHPUnit\Framework\TestCase;

/**
 * @author Brian Faust <brian@cline.sh>
 * @internal
 */
final class FormatInformationTest extends TestCase
{
    private const int MASKED_TEST_FORMAT_INFO = 0x2B_ED;

    private const mixed UNMAKSED_TEST_FORMAT_INFO = self::MASKED_TEST_FORMAT_INFO ^ 0x54_12;

    public function test_bits_differing(): void
    {
        $this->assertSame(0, FormatInformation::numBitsDiffering(1, 1));
        $this->assertSame(1, FormatInformation::numBitsDiffering(0, 2));
        $this->assertSame(2, FormatInformation::numBitsDiffering(1, 2));
        $this->assertSame(32, FormatInformation::numBitsDiffering(-1, 0));
    }

    public function test_decode(): void
    {
        $expected = FormatInformation::decodeFormatInformation(
            self::MASKED_TEST_FORMAT_INFO,
            self::MASKED_TEST_FORMAT_INFO,
        );

        $this->assertInstanceOf(FormatInformation::class, $expected);
        $this->assertSame(7, $expected->getDataMask());
        $this->assertSame(ErrorCorrectionLevel::Q, $expected->getErrorCorrectionLevel());

        $this->assertEquals(
            $expected,
            FormatInformation::decodeFormatInformation(
                self::UNMAKSED_TEST_FORMAT_INFO,
                self::MASKED_TEST_FORMAT_INFO,
            ),
        );
    }

    public function test_decode_with_bit_difference(): void
    {
        $expected = FormatInformation::decodeFormatInformation(
            self::MASKED_TEST_FORMAT_INFO,
            self::MASKED_TEST_FORMAT_INFO,
        );

        $this->assertEquals(
            $expected,
            FormatInformation::decodeFormatInformation(
                self::MASKED_TEST_FORMAT_INFO ^ 0x1,
                self::MASKED_TEST_FORMAT_INFO ^ 0x1,
            ),
        );
        $this->assertEquals(
            $expected,
            FormatInformation::decodeFormatInformation(
                self::MASKED_TEST_FORMAT_INFO ^ 0x3,
                self::MASKED_TEST_FORMAT_INFO ^ 0x3,
            ),
        );
        $this->assertEquals(
            $expected,
            FormatInformation::decodeFormatInformation(
                self::MASKED_TEST_FORMAT_INFO ^ 0x7,
                self::MASKED_TEST_FORMAT_INFO ^ 0x7,
            ),
        );
        $this->assertNotInstanceOf(
            FormatInformation::class,
            FormatInformation::decodeFormatInformation(
                self::MASKED_TEST_FORMAT_INFO ^ 0xF,
                self::MASKED_TEST_FORMAT_INFO ^ 0xF,
            ),
        );
    }

    public function test_decode_with_mis_read(): void
    {
        $expected = FormatInformation::decodeFormatInformation(
            self::MASKED_TEST_FORMAT_INFO,
            self::MASKED_TEST_FORMAT_INFO,
        );

        $this->assertEquals(
            $expected,
            FormatInformation::decodeFormatInformation(
                self::MASKED_TEST_FORMAT_INFO ^ 0x3,
                self::MASKED_TEST_FORMAT_INFO ^ 0xF,
            ),
        );
    }
}
