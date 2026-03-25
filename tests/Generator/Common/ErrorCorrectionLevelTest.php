<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Generator\Common;

use Cline\Qr\Generator\Common\ErrorCorrectionLevel;
use Cline\Qr\Generator\Internal\Exception\OutOfBoundsException;
use PHPUnit\Framework\TestCase;

/**
 * @author Brian Faust <brian@cline.sh>
 * @internal
 */
final class ErrorCorrectionLevelTest extends TestCase
{
    public function test_bits_match_constants(): void
    {
        $this->assertSame(0x0, ErrorCorrectionLevel::M->getBits());
        $this->assertSame(0x1, ErrorCorrectionLevel::L->getBits());
        $this->assertSame(0x2, ErrorCorrectionLevel::H->getBits());
        $this->assertSame(0x3, ErrorCorrectionLevel::Q->getBits());
    }

    public function test_invalid_error_correction_level_throws_exception(): void
    {
        $this->expectException(OutOfBoundsException::class);
        ErrorCorrectionLevel::forBits(4);
    }
}
