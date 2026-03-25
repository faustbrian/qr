<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Generator\Common;

use Cline\Qr\Generator\Common\Mode;
use PHPUnit\Framework\TestCase;

/**
 * @author Brian Faust <brian@cline.sh>
 * @internal
 */
final class ModeTest extends TestCase
{
    public function test_bits_match_constants(): void
    {
        $this->assertSame(0x0, Mode::TERMINATOR->getBits());
        $this->assertSame(0x1, Mode::NUMERIC->getBits());
        $this->assertSame(0x2, Mode::ALPHANUMERIC->getBits());
        $this->assertSame(0x4, Mode::BYTE->getBits());
        $this->assertSame(0x8, Mode::KANJI->getBits());
    }
}
