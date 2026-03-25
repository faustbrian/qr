<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Qr\Generator\Common\ErrorCorrectionLevel;
use Cline\Qr\Generator\Common\FormatInformation;

const MASKED_TEST_FORMAT_INFO = 0x2B_ED;
const UNMASKED_TEST_FORMAT_INFO = MASKED_TEST_FORMAT_INFO ^ 0x54_12;

test('bits differing', function (): void {
    $this->assertSame(0, FormatInformation::numBitsDiffering(1, 1));
    $this->assertSame(1, FormatInformation::numBitsDiffering(0, 2));
    $this->assertSame(2, FormatInformation::numBitsDiffering(1, 2));
    $this->assertSame(32, FormatInformation::numBitsDiffering(-1, 0));
});

test('decode', function (): void {
    $expected = FormatInformation::decodeFormatInformation(
        MASKED_TEST_FORMAT_INFO,
        MASKED_TEST_FORMAT_INFO,
    );

    $this->assertInstanceOf(FormatInformation::class, $expected);
    $this->assertSame(7, $expected->getDataMask());
    $this->assertSame(ErrorCorrectionLevel::Q, $expected->getErrorCorrectionLevel());

    $this->assertEquals(
        $expected,
        FormatInformation::decodeFormatInformation(
            UNMASKED_TEST_FORMAT_INFO,
            MASKED_TEST_FORMAT_INFO,
        ),
    );
});

test('decode with bit difference', function (): void {
    $expected = FormatInformation::decodeFormatInformation(
        MASKED_TEST_FORMAT_INFO,
        MASKED_TEST_FORMAT_INFO,
    );

    $this->assertEquals(
        $expected,
        FormatInformation::decodeFormatInformation(
            MASKED_TEST_FORMAT_INFO ^ 0x1,
            MASKED_TEST_FORMAT_INFO ^ 0x1,
        ),
    );
    $this->assertEquals(
        $expected,
        FormatInformation::decodeFormatInformation(
            MASKED_TEST_FORMAT_INFO ^ 0x3,
            MASKED_TEST_FORMAT_INFO ^ 0x3,
        ),
    );
    $this->assertEquals(
        $expected,
        FormatInformation::decodeFormatInformation(
            MASKED_TEST_FORMAT_INFO ^ 0x7,
            MASKED_TEST_FORMAT_INFO ^ 0x7,
        ),
    );
    $this->assertNotInstanceOf(
        FormatInformation::class,
        FormatInformation::decodeFormatInformation(
            MASKED_TEST_FORMAT_INFO ^ 0xF,
            MASKED_TEST_FORMAT_INFO ^ 0xF,
        ),
    );
});

test('decode with mis read', function (): void {
    $expected = FormatInformation::decodeFormatInformation(
        MASKED_TEST_FORMAT_INFO,
        MASKED_TEST_FORMAT_INFO,
    );

    $this->assertEquals(
        $expected,
        FormatInformation::decodeFormatInformation(
            MASKED_TEST_FORMAT_INFO ^ 0x3,
            MASKED_TEST_FORMAT_INFO ^ 0xF,
        ),
    );
});
