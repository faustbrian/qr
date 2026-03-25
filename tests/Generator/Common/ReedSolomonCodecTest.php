<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Generator\Common;

use Cline\Qr\Generator\Common\ReedSolomonCodec;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SplFixedArray;

use function array_fill;
use function mt_rand;
use function mt_srand;
use function sprintf;

/**
 * @author Brian Faust <brian@cline.sh>
 * @internal
 */
final class ReedSolomonCodecTest extends TestCase
{
    #[DataProvider('provideCodecCases')]
    public function test_codec(int $symbolSize, int $generatorPoly, int $firstRoot, int $primitive, int $numRoots): void
    {
        mt_srand(0xDE_AD_BE_EF);

        $blockSize = (1 << $symbolSize) - 1;
        $dataSize = $blockSize - $numRoots;
        $codec = new ReedSolomonCodec($symbolSize, $generatorPoly, $firstRoot, $primitive, $numRoots, 0);

        for ($errors = 0; $errors <= $numRoots / 2; ++$errors) {
            // Load block with random data and encode
            $block = SplFixedArray::fromArray(array_fill(0, $blockSize, 0), false);

            for ($i = 0; $i < $dataSize; ++$i) {
                $block[$i] = mt_rand(0, $blockSize);
            }

            // Make temporary copy
            $tBlock = clone $block;
            $parity = SplFixedArray::fromArray(array_fill(0, $numRoots, 0), false);
            $errorLocations = SplFixedArray::fromArray(array_fill(0, $blockSize, 0), false);
            $erasures = [];

            // Create parity
            $codec->encode($block, $parity);

            // Copy parity into test blocks
            for ($i = 0; $i < $numRoots; ++$i) {
                $block[$i + $dataSize] = $parity[$i];
                $tBlock[$i + $dataSize] = $parity[$i];
            }

            // Seed with errors
            for ($i = 0; $i < $errors; ++$i) {
                $errorValue = mt_rand(1, $blockSize);

                do {
                    $errorLocation = mt_rand(0, $blockSize - 1);
                } while (0 !== $errorLocations[$errorLocation]);

                $errorLocations[$errorLocation] = 1;

                if (mt_rand(0, 1) !== 0) {
                    $erasures[] = $errorLocation;
                }

                $tBlock[$errorLocation] ^= $errorValue;
            }

            $erasures = SplFixedArray::fromArray($erasures, false);

            // Decode the errored block
            $foundErrors = $codec->decode($tBlock, $erasures);

            if ($errors > 0 && null === $foundErrors) {
                $this->assertSame($block, $tBlock, 'Decoder failed to correct errors');
            }

            $this->assertSame($errors, $foundErrors, 'Found errors do not equal expected errors');

            for ($i = 0; $i < $foundErrors; ++$i) {
                if (0 !== $errorLocations[$erasures[$i]]) {
                    continue;
                }

                $this->fail(sprintf('Decoder indicates error in location %d without error', $erasures[$i]));
            }

            $this->assertEquals($block, $tBlock, 'Decoder did not correct errors');
        }
    }

    public static function provideCodecCases(): iterable
    {
        yield [2, 0x7, 1, 1, 1];

        yield [3, 0xB, 1, 1, 2];

        yield [4, 0x13, 1, 1, 4];

        yield [5, 0x25, 1, 1, 6];

        yield [6, 0x43, 1, 1, 8];

        yield [7, 0x89, 1, 1, 10];

        yield [8, 0x1_1D, 1, 1, 32];
    }
}
