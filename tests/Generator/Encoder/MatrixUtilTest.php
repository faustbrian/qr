<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Qr\Generator\Common\BitArray;
use Cline\Qr\Generator\Common\ErrorCorrectionLevel;
use Cline\Qr\Generator\Common\Version;
use Cline\Qr\Generator\Encoder\ByteMatrix;
use Cline\Qr\Generator\Encoder\MatrixUtil;

/**
 * @author Brian Faust <brian@cline.sh>
 * @internal
 */
function matrixUtilMethods(): array
{
    $methods = [];
    $reflection = new ReflectionClass(MatrixUtil::class);

    foreach ($reflection->getMethods(ReflectionMethod::IS_STATIC) as $method) {
        $methods[$method->getName()] = $method;
    }

    return $methods;
}

$methods = matrixUtilMethods();

test('test_to_string', function (): void {
    $matrix = new ByteMatrix(3, 3);
    $matrix->set(0, 0, 0);
    $matrix->set(1, 0, 1);
    $matrix->set(2, 0, 0);
    $matrix->set(0, 1, 1);
    $matrix->set(1, 1, 0);
    $matrix->set(2, 1, 1);
    $matrix->set(0, 2, -1);
    $matrix->set(1, 2, -1);
    $matrix->set(2, 2, -1);

    $expected = " 0 1 0\n 1 0 1\n      \n";
    $this->assertSame($expected, (string) $matrix);
});

test('test_clear_matrix', function (): void {
    $matrix = new ByteMatrix(2, 2);
    MatrixUtil::clearMatrix($matrix);

    $this->assertSame(-1, $matrix->get(0, 0));
    $this->assertSame(-1, $matrix->get(1, 0));
    $this->assertSame(-1, $matrix->get(0, 1));
    $this->assertSame(-1, $matrix->get(1, 1));
});

test('test_embed_basic_patterns1', function () use ($methods): void {
    $matrix = new ByteMatrix(21, 21);
    MatrixUtil::clearMatrix($matrix);
    $methods['embedBasicPatterns']->invoke(
        null,
        Version::getVersionForNumber(1),
        $matrix,
    );
    $expected = " 1 1 1 1 1 1 1 0           0 1 1 1 1 1 1 1\n"
              ." 1 0 0 0 0 0 1 0           0 1 0 0 0 0 0 1\n"
              ." 1 0 1 1 1 0 1 0           0 1 0 1 1 1 0 1\n"
              ." 1 0 1 1 1 0 1 0           0 1 0 1 1 1 0 1\n"
              ." 1 0 1 1 1 0 1 0           0 1 0 1 1 1 0 1\n"
              ." 1 0 0 0 0 0 1 0           0 1 0 0 0 0 0 1\n"
              ." 1 1 1 1 1 1 1 0 1 0 1 0 1 0 1 1 1 1 1 1 1\n"
              ." 0 0 0 0 0 0 0 0           0 0 0 0 0 0 0 0\n"
              ."             1                            \n"
              ."             0                            \n"
              ."             1                            \n"
              ."             0                            \n"
              ."             1                            \n"
              ." 0 0 0 0 0 0 0 0 1                        \n"
              ." 1 1 1 1 1 1 1 0                          \n"
              ." 1 0 0 0 0 0 1 0                          \n"
              ." 1 0 1 1 1 0 1 0                          \n"
              ." 1 0 1 1 1 0 1 0                          \n"
              ." 1 0 1 1 1 0 1 0                          \n"
              ." 1 0 0 0 0 0 1 0                          \n"
              ." 1 1 1 1 1 1 1 0                          \n";

    $this->assertSame($expected, (string) $matrix);
});

test('test_embed_basic_patterns2', function () use ($methods): void {
    $matrix = new ByteMatrix(25, 25);
    MatrixUtil::clearMatrix($matrix);
    $methods['embedBasicPatterns']->invoke(
        null,
        Version::getVersionForNumber(2),
        $matrix,
    );
    $expected = " 1 1 1 1 1 1 1 0                   0 1 1 1 1 1 1 1\n"
              ." 1 0 0 0 0 0 1 0                   0 1 0 0 0 0 0 1\n"
              ." 1 0 1 1 1 0 1 0                   0 1 0 1 1 1 0 1\n"
              ." 1 0 1 1 1 0 1 0                   0 1 0 1 1 1 0 1\n"
              ." 1 0 1 1 1 0 1 0                   0 1 0 1 1 1 0 1\n"
              ." 1 0 0 0 0 0 1 0                   0 1 0 0 0 0 0 1\n"
              ." 1 1 1 1 1 1 1 0 1 0 1 0 1 0 1 0 1 0 1 1 1 1 1 1 1\n"
              ." 0 0 0 0 0 0 0 0                   0 0 0 0 0 0 0 0\n"
              ."             1                                    \n"
              ."             0                                    \n"
              ."             1                                    \n"
              ."             0                                    \n"
              ."             1                                    \n"
              ."             0                                    \n"
              ."             1                                    \n"
              ."             0                                    \n"
              ."             1                   1 1 1 1 1        \n"
              ." 0 0 0 0 0 0 0 0 1               1 0 0 0 1        \n"
              ." 1 1 1 1 1 1 1 0                 1 0 1 0 1        \n"
              ." 1 0 0 0 0 0 1 0                 1 0 0 0 1        \n"
              ." 1 0 1 1 1 0 1 0                 1 1 1 1 1        \n"
              ." 1 0 1 1 1 0 1 0                                  \n"
              ." 1 0 1 1 1 0 1 0                                  \n"
              ." 1 0 0 0 0 0 1 0                                  \n"
              ." 1 1 1 1 1 1 1 0                                  \n";

    $this->assertSame($expected, (string) $matrix);
});

test('test_embed_type_info', function () use ($methods): void {
    $matrix = new ByteMatrix(21, 21);
    MatrixUtil::clearMatrix($matrix);
    $methods['embedTypeInfo']->invoke(
        null,
        ErrorCorrectionLevel::M,
        5,
        $matrix,
    );
    $expected = "                 0                        \n"
              ."                 1                        \n"
              ."                 1                        \n"
              ."                 1                        \n"
              ."                 0                        \n"
              ."                 0                        \n"
              ."                                          \n"
              ."                 1                        \n"
              ." 1 0 0 0 0 0   0 1         1 1 0 0 1 1 1 0\n"
              ."                                          \n"
              ."                                          \n"
              ."                                          \n"
              ."                                          \n"
              ."                                          \n"
              ."                 0                        \n"
              ."                 0                        \n"
              ."                 0                        \n"
              ."                 0                        \n"
              ."                 0                        \n"
              ."                 0                        \n"
              ."                 1                        \n";

    $this->assertSame($expected, (string) $matrix);
});

test('test_embed_version_info', function () use ($methods): void {
    $matrix = new ByteMatrix(21, 21);
    MatrixUtil::clearMatrix($matrix);
    $methods['maybeEmbedVersionInfo']->invoke(
        null,
        Version::getVersionForNumber(7),
        $matrix,
    );
    $expected = "                     0 0 1                \n"
              ."                     0 1 0                \n"
              ."                     0 1 0                \n"
              ."                     0 1 1                \n"
              ."                     1 1 1                \n"
              ."                     0 0 0                \n"
              ."                                          \n"
              ."                                          \n"
              ."                                          \n"
              ."                                          \n"
              ." 0 0 0 0 1 0                              \n"
              ." 0 1 1 1 1 0                              \n"
              ." 1 0 0 1 1 0                              \n"
              ."                                          \n"
              ."                                          \n"
              ."                                          \n"
              ."                                          \n"
              ."                                          \n"
              ."                                          \n"
              ."                                          \n"
              ."                                          \n";

    $this->assertSame($expected, (string) $matrix);
});

test('test_embed_data_bits', function () use ($methods): void {
    $matrix = new ByteMatrix(21, 21);
    MatrixUtil::clearMatrix($matrix);
    $methods['embedBasicPatterns']->invoke(
        null,
        Version::getVersionForNumber(1),
        $matrix,
    );

    $bits = new BitArray();
    $methods['embedDataBits']->invoke(
        null,
        $bits,
        -1,
        $matrix,
    );

    $expected = " 1 1 1 1 1 1 1 0 0 0 0 0 0 0 1 1 1 1 1 1 1\n"
              ." 1 0 0 0 0 0 1 0 0 0 0 0 0 0 1 0 0 0 0 0 1\n"
              ." 1 0 1 1 1 0 1 0 0 0 0 0 0 0 1 0 1 1 1 0 1\n"
              ." 1 0 1 1 1 0 1 0 0 0 0 0 0 0 1 0 1 1 1 0 1\n"
              ." 1 0 1 1 1 0 1 0 0 0 0 0 0 0 1 0 1 1 1 0 1\n"
              ." 1 0 0 0 0 0 1 0 0 0 0 0 0 0 1 0 0 0 0 0 1\n"
              ." 1 1 1 1 1 1 1 0 1 0 1 0 1 0 1 1 1 1 1 1 1\n"
              ." 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0\n"
              ." 0 0 0 0 0 0 1 0 0 0 0 0 0 0 0 0 0 0 0 0 0\n"
              ." 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0\n"
              ." 0 0 0 0 0 0 1 0 0 0 0 0 0 0 0 0 0 0 0 0 0\n"
              ." 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0\n"
              ." 0 0 0 0 0 0 1 0 0 0 0 0 0 0 0 0 0 0 0 0 0\n"
              ." 0 0 0 0 0 0 0 0 1 0 0 0 0 0 0 0 0 0 0 0 0\n"
              ." 1 1 1 1 1 1 1 0 0 0 0 0 0 0 0 0 0 0 0 0 0\n"
              ." 1 0 0 0 0 0 1 0 0 0 0 0 0 0 0 0 0 0 0 0 0\n"
              ." 1 0 1 1 1 0 1 0 0 0 0 0 0 0 0 0 0 0 0 0 0\n"
              ." 1 0 1 1 1 0 1 0 0 0 0 0 0 0 0 0 0 0 0 0 0\n"
              ." 1 0 1 1 1 0 1 0 0 0 0 0 0 0 0 0 0 0 0 0 0\n"
              ." 1 0 0 0 0 0 1 0 0 0 0 0 0 0 0 0 0 0 0 0 0\n"
              ." 1 1 1 1 1 1 1 0 0 0 0 0 0 0 0 0 0 0 0 0 0\n";

    $this->assertSame($expected, (string) $matrix);
});

test('test_build_matrix', function (): void {
    $bytes = [
        32, 65, 205, 69, 41, 220, 46, 128, 236, 42, 159, 74, 221, 244, 169,
        239, 150, 138, 70, 237, 85, 224, 96, 74, 219, 61,
    ];
    $bits = new BitArray();

    foreach ($bytes as $byte) {
        $bits->appendBits($byte, 8);
    }

    $matrix = new ByteMatrix(21, 21);
    MatrixUtil::buildMatrix(
        $bits,
        ErrorCorrectionLevel::H,
        Version::getVersionForNumber(1),
        3,
        $matrix,
    );

    $expected = " 1 1 1 1 1 1 1 0 0 1 1 0 0 0 1 1 1 1 1 1 1\n"
              ." 1 0 0 0 0 0 1 0 0 0 0 0 0 0 1 0 0 0 0 0 1\n"
              ." 1 0 1 1 1 0 1 0 0 0 0 1 0 0 1 0 1 1 1 0 1\n"
              ." 1 0 1 1 1 0 1 0 0 1 1 0 0 0 1 0 1 1 1 0 1\n"
              ." 1 0 1 1 1 0 1 0 1 1 0 0 1 0 1 0 1 1 1 0 1\n"
              ." 1 0 0 0 0 0 1 0 0 0 1 1 1 0 1 0 0 0 0 0 1\n"
              ." 1 1 1 1 1 1 1 0 1 0 1 0 1 0 1 1 1 1 1 1 1\n"
              ." 0 0 0 0 0 0 0 0 1 1 0 1 1 0 0 0 0 0 0 0 0\n"
              ." 0 0 1 1 0 0 1 1 1 0 0 1 1 1 1 0 1 0 0 0 0\n"
              ." 1 0 1 0 1 0 0 0 0 0 1 1 1 0 0 1 0 1 1 1 0\n"
              ." 1 1 1 1 0 1 1 0 1 0 1 1 1 0 0 1 1 1 0 1 0\n"
              ." 1 0 1 0 1 1 0 1 1 1 0 0 1 1 1 0 0 1 0 1 0\n"
              ." 0 0 1 0 0 1 1 1 0 0 0 0 0 0 1 0 1 1 1 1 1\n"
              ." 0 0 0 0 0 0 0 0 1 1 0 1 0 0 0 0 0 1 0 1 1\n"
              ." 1 1 1 1 1 1 1 0 1 1 1 1 0 0 0 0 1 0 1 1 0\n"
              ." 1 0 0 0 0 0 1 0 0 0 0 1 0 1 1 1 0 0 0 0 0\n"
              ." 1 0 1 1 1 0 1 0 0 1 0 0 1 1 0 0 1 0 0 1 1\n"
              ." 1 0 1 1 1 0 1 0 1 1 0 1 0 0 0 0 0 1 1 1 0\n"
              ." 1 0 1 1 1 0 1 0 1 1 1 1 0 0 0 0 1 1 1 0 0\n"
              ." 1 0 0 0 0 0 1 0 0 0 0 0 0 0 0 0 1 0 1 0 0\n"
              ." 1 1 1 1 1 1 1 0 0 0 1 1 1 1 1 0 1 0 0 1 0\n";

    $this->assertSame($expected, (string) $matrix);
});

test('test_find_msb_set', function () use ($methods): void {
    $this->assertSame(0, $methods['findMsbSet']->invoke(null, 0));
    $this->assertSame(1, $methods['findMsbSet']->invoke(null, 1));
    $this->assertSame(8, $methods['findMsbSet']->invoke(null, 0x80));
    $this->assertSame(32, $methods['findMsbSet']->invoke(null, 0x80_00_00_00));
});

test('test_calculate_bch_code', function () use ($methods): void {
    // Encoding of type information.
    // From Appendix C in JISX0510:2004 (p 65)
    $this->assertSame(0xDC, $methods['calculateBchCode']->invoke(null, 5, 0x5_37));
    // From http://www.swetake.com/qr/qr6.html
    $this->assertSame(0x1_C2, $methods['calculateBchCode']->invoke(null, 0x13, 0x5_37));
    // From http://www.swetake.com/qr/qr11.html
    $this->assertSame(0x2_14, $methods['calculateBchCode']->invoke(null, 0x1B, 0x5_37));

    // Encoding of version information.
    // From Appendix D in JISX0510:2004 (p 68)
    $this->assertSame(0xC_94, $methods['calculateBchCode']->invoke(null, 7, 0x1F_25));
    $this->assertSame(0x5_BC, $methods['calculateBchCode']->invoke(null, 8, 0x1F_25));
    $this->assertSame(0xA_99, $methods['calculateBchCode']->invoke(null, 9, 0x1F_25));
    $this->assertSame(0x4_D3, $methods['calculateBchCode']->invoke(null, 10, 0x1F_25));
    $this->assertSame(0x9_A6, $methods['calculateBchCode']->invoke(null, 20, 0x1F_25));
    $this->assertSame(0xD_75, $methods['calculateBchCode']->invoke(null, 30, 0x1F_25));
    $this->assertSame(0xC_69, $methods['calculateBchCode']->invoke(null, 40, 0x1F_25));
});

test('test_make_version_info_bits', function () use ($methods): void {
    // From Appendix D in JISX0510:2004 (p 68)
    $bits = new BitArray();
    $methods['makeVersionInfoBits']->invoke(null, Version::getVersionForNumber(7), $bits);
    $this->assertSame(' ...XXXXX ..X..X.X ..', (string) $bits);
});

test('test_make_type_info_bits', function () use ($methods): void {
    // From Appendix D in JISX0510:2004 (p 68)
    $bits = new BitArray();
    $methods['makeTypeInfoBits']->invoke(null, ErrorCorrectionLevel::M, 5, $bits);
    $this->assertSame(' X......X X..XXX.', (string) $bits);
});
