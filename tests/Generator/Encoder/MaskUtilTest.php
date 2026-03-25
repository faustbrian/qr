<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Qr\Generator\Encoder\ByteMatrix;
use Cline\Qr\Generator\Encoder\MaskUtil;

dataset('mask bit cases', [
    [0, [
        [1, 0, 1, 0, 1, 0],
        [0, 1, 0, 1, 0, 1],
        [1, 0, 1, 0, 1, 0],
        [0, 1, 0, 1, 0, 1],
        [1, 0, 1, 0, 1, 0],
        [0, 1, 0, 1, 0, 1],
    ]],
    [1, [
        [1, 1, 1, 1, 1, 1],
        [0, 0, 0, 0, 0, 0],
        [1, 1, 1, 1, 1, 1],
        [0, 0, 0, 0, 0, 0],
        [1, 1, 1, 1, 1, 1],
        [0, 0, 0, 0, 0, 0],
    ]],
    [2, [
        [1, 0, 0, 1, 0, 0],
        [1, 0, 0, 1, 0, 0],
        [1, 0, 0, 1, 0, 0],
        [1, 0, 0, 1, 0, 0],
        [1, 0, 0, 1, 0, 0],
        [1, 0, 0, 1, 0, 0],
    ]],
    [3, [
        [1, 0, 0, 1, 0, 0],
        [0, 0, 1, 0, 0, 1],
        [0, 1, 0, 0, 1, 0],
        [1, 0, 0, 1, 0, 0],
        [0, 0, 1, 0, 0, 1],
        [0, 1, 0, 0, 1, 0],
    ]],
    [4, [
        [1, 1, 1, 0, 0, 0],
        [1, 1, 1, 0, 0, 0],
        [0, 0, 0, 1, 1, 1],
        [0, 0, 0, 1, 1, 1],
        [1, 1, 1, 0, 0, 0],
        [1, 1, 1, 0, 0, 0],
    ]],
    [5, [
        [1, 1, 1, 1, 1, 1],
        [1, 0, 0, 0, 0, 0],
        [1, 0, 0, 1, 0, 0],
        [1, 0, 1, 0, 1, 0],
        [1, 0, 0, 1, 0, 0],
        [1, 0, 0, 0, 0, 0],
    ]],
    [6, [
        [1, 1, 1, 1, 1, 1],
        [1, 1, 1, 0, 0, 0],
        [1, 1, 0, 1, 1, 0],
        [1, 0, 1, 0, 1, 0],
        [1, 0, 1, 1, 0, 1],
        [1, 0, 0, 0, 1, 1],
    ]],
    [7, [
        [1, 0, 1, 0, 1, 0],
        [0, 0, 0, 1, 1, 1],
        [1, 0, 0, 0, 1, 1],
        [0, 1, 0, 1, 0, 1],
        [1, 1, 1, 0, 0, 0],
        [0, 1, 1, 1, 0, 0],
    ]],
]);

test('get dat mask bit', function (int $maskPattern, array $expected): void {
    for ($x = 0; $x < 6; ++$x) {
        for ($y = 0; $y < 6; ++$y) {
            $this->assertSame(
                1 === $expected[$y][$x],
                MaskUtil::getDataMaskBit($maskPattern, $x, $y),
            );
        }
    }
})->with('mask bit cases');

test('apply mask penalty rule1', function (): void {
    $matrix = new ByteMatrix(4, 1);
    $matrix->set(0, 0, 0);
    $matrix->set(1, 0, 0);
    $matrix->set(2, 0, 0);
    $matrix->set(3, 0, 0);

    $this->assertSame(0, MaskUtil::applyMaskPenaltyRule1($matrix));

    $matrix = new ByteMatrix(6, 1);
    $matrix->set(0, 0, 0);
    $matrix->set(1, 0, 0);
    $matrix->set(2, 0, 0);
    $matrix->set(3, 0, 0);
    $matrix->set(4, 0, 0);
    $matrix->set(5, 0, 1);
    $this->assertSame(3, MaskUtil::applyMaskPenaltyRule1($matrix));
    $matrix->set(5, 0, 0);
    $this->assertSame(4, MaskUtil::applyMaskPenaltyRule1($matrix));

    $matrix = new ByteMatrix(1, 6);
    $matrix->set(0, 0, 0);
    $matrix->set(0, 1, 0);
    $matrix->set(0, 2, 0);
    $matrix->set(0, 3, 0);
    $matrix->set(0, 4, 0);
    $matrix->set(0, 5, 1);
    $this->assertSame(3, MaskUtil::applyMaskPenaltyRule1($matrix));
    $matrix->set(0, 5, 0);
    $this->assertSame(4, MaskUtil::applyMaskPenaltyRule1($matrix));
});

test('apply mask penalty rule2', function (): void {
    $matrix = new ByteMatrix(1, 1);
    $matrix->set(0, 0, 0);
    $this->assertSame(0, MaskUtil::applyMaskPenaltyRule2($matrix));

    $matrix = new ByteMatrix(2, 2);
    $matrix->set(0, 0, 0);
    $matrix->set(1, 0, 0);
    $matrix->set(0, 1, 0);
    $matrix->set(1, 1, 1);
    $this->assertSame(0, MaskUtil::applyMaskPenaltyRule2($matrix));

    $matrix = new ByteMatrix(2, 2);
    $matrix->set(0, 0, 0);
    $matrix->set(1, 0, 0);
    $matrix->set(0, 1, 0);
    $matrix->set(1, 1, 0);
    $this->assertSame(3, MaskUtil::applyMaskPenaltyRule2($matrix));

    $matrix = new ByteMatrix(3, 3);
    $matrix->set(0, 0, 0);
    $matrix->set(1, 0, 0);
    $matrix->set(2, 0, 0);
    $matrix->set(0, 1, 0);
    $matrix->set(1, 1, 0);
    $matrix->set(2, 1, 0);
    $matrix->set(0, 2, 0);
    $matrix->set(1, 2, 0);
    $matrix->set(2, 2, 0);
    $this->assertSame(3 * 4, MaskUtil::applyMaskPenaltyRule2($matrix));
});

test('apply mask penalty3', function (): void {
    $matrix = new ByteMatrix(11, 1);
    $matrix->set(0, 0, 0);
    $matrix->set(1, 0, 0);
    $matrix->set(2, 0, 0);
    $matrix->set(3, 0, 0);
    $matrix->set(4, 0, 1);
    $matrix->set(5, 0, 0);
    $matrix->set(6, 0, 1);
    $matrix->set(7, 0, 1);
    $matrix->set(8, 0, 1);
    $matrix->set(9, 0, 0);
    $matrix->set(10, 0, 1);
    $this->assertSame(40, MaskUtil::applyMaskPenaltyRule3($matrix));

    $matrix = new ByteMatrix(11, 1);
    $matrix->set(0, 0, 1);
    $matrix->set(1, 0, 0);
    $matrix->set(2, 0, 1);
    $matrix->set(3, 0, 1);
    $matrix->set(4, 0, 1);
    $matrix->set(5, 0, 0);
    $matrix->set(6, 0, 1);
    $matrix->set(7, 0, 0);
    $matrix->set(8, 0, 0);
    $matrix->set(9, 0, 0);
    $matrix->set(10, 0, 0);
    $this->assertSame(40, MaskUtil::applyMaskPenaltyRule3($matrix));

    $matrix = new ByteMatrix(1, 11);
    $matrix->set(0, 0, 0);
    $matrix->set(0, 1, 0);
    $matrix->set(0, 2, 0);
    $matrix->set(0, 3, 0);
    $matrix->set(0, 4, 1);
    $matrix->set(0, 5, 0);
    $matrix->set(0, 6, 1);
    $matrix->set(0, 7, 1);
    $matrix->set(0, 8, 1);
    $matrix->set(0, 9, 0);
    $matrix->set(0, 10, 1);
    $this->assertSame(40, MaskUtil::applyMaskPenaltyRule3($matrix));

    $matrix = new ByteMatrix(1, 11);
    $matrix->set(0, 0, 1);
    $matrix->set(0, 1, 0);
    $matrix->set(0, 2, 1);
    $matrix->set(0, 3, 1);
    $matrix->set(0, 4, 1);
    $matrix->set(0, 5, 0);
    $matrix->set(0, 6, 1);
    $matrix->set(0, 7, 0);
    $matrix->set(0, 8, 0);
    $matrix->set(0, 9, 0);
    $matrix->set(0, 10, 0);
    $this->assertSame(40, MaskUtil::applyMaskPenaltyRule3($matrix));
});

test('apply mask penalty rule4', function (): void {
    $matrix = new ByteMatrix(1, 1);
    $matrix->set(0, 0, 0);
    $this->assertSame(100, MaskUtil::applyMaskPenaltyRule4($matrix));

    $matrix = new ByteMatrix(2, 1);
    $matrix->set(0, 0, 0);
    $matrix->set(0, 0, 1);
    $this->assertSame(0, MaskUtil::applyMaskPenaltyRule4($matrix));

    $matrix = new ByteMatrix(6, 1);
    $matrix->set(0, 0, 0);
    $matrix->set(1, 0, 1);
    $matrix->set(2, 0, 1);
    $matrix->set(3, 0, 1);
    $matrix->set(4, 0, 1);
    $matrix->set(5, 0, 0);
    $this->assertSame(30, MaskUtil::applyMaskPenaltyRule4($matrix));
});
