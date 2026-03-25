<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Generator\Common;

use Cline\Qr\Generator\Common\BitUtils;
use PHPUnit\Framework\TestCase;

/**
 * @author Brian Faust <brian@cline.sh>
 * @internal
 */
final class BitUtilsTest extends TestCase
{
    public function test_unsigned_right_shift(): void
    {
        $this->assertSame(1, BitUtils::unsignedRightShift(1, 0));
        $this->assertSame(1, BitUtils::unsignedRightShift(10, 3));
        $this->assertSame(536_870_910, BitUtils::unsignedRightShift(-10, 3));
    }

    public function test_number_of_trailing_zeros(): void
    {
        $this->assertSame(32, BitUtils::numberOfTrailingZeros(0));
        $this->assertSame(1, BitUtils::numberOfTrailingZeros(10));
        $this->assertSame(0, BitUtils::numberOfTrailingZeros(15));
        $this->assertSame(2, BitUtils::numberOfTrailingZeros(20));
    }
}
