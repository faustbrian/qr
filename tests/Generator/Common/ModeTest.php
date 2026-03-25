<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Qr\Generator\Common\Mode;

test('bits match constants', function (): void {
    $this->assertSame(0x0, Mode::TERMINATOR->getBits());
    $this->assertSame(0x1, Mode::NUMERIC->getBits());
    $this->assertSame(0x2, Mode::ALPHANUMERIC->getBits());
    $this->assertSame(0x4, Mode::BYTE->getBits());
    $this->assertSame(0x8, Mode::KANJI->getBits());
});
