<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Qr\Generator\Common\ErrorCorrectionLevel;
use Cline\Qr\Generator\Internal\Exception\OutOfBoundsException;

test('bits match constants', function (): void {
    $this->assertSame(0x0, ErrorCorrectionLevel::M->getBits());
    $this->assertSame(0x1, ErrorCorrectionLevel::L->getBits());
    $this->assertSame(0x2, ErrorCorrectionLevel::H->getBits());
    $this->assertSame(0x3, ErrorCorrectionLevel::Q->getBits());
});

test('invalid error correction level throws exception', function (): void {
    $this->expectException(OutOfBoundsException::class);
    ErrorCorrectionLevel::forBits(4);
});
