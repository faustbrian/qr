<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Generator\Common;

use Cline\Qr\Generator\Common\BitArray;
use PHPUnit\Framework\TestCase;

use function mt_rand;
use function mt_srand;

/**
 * @author Brian Faust <brian@cline.sh>
 * @internal
 */
final class BitArrayTest extends TestCase
{
    public function test_get_set(): void
    {
        $array = new BitArray(33);

        for ($i = 0; $i < 33; ++$i) {
            $this->assertFalse($array->get($i));
            $array->set($i);
            $this->assertTrue($array->get($i));
        }
    }

    public function test_get_next_set1(): void
    {
        $array = new BitArray(32);

        for ($i = 0; $i < $array->getSize(); ++$i) {
            $this->assertEqualsWithDelta(32, $i, $array->getNextSet($i));
        }

        $array = new BitArray(33);

        for ($i = 0; $i < $array->getSize(); ++$i) {
            $this->assertEqualsWithDelta(33, $i, $array->getNextSet($i));
        }
    }

    public function test_get_next_set2(): void
    {
        $array = new BitArray(33);

        for ($i = 0; $i < $array->getSize(); ++$i) {
            $this->assertEqualsWithDelta($i, $i <= 31 ? 31 : 33, $array->getNextSet($i));
        }

        $array = new BitArray(33);

        for ($i = 0; $i < $array->getSize(); ++$i) {
            $this->assertEqualsWithDelta(32, $i, $array->getNextSet($i));
        }
    }

    public function test_get_next_set3(): void
    {
        $array = new BitArray(63);
        $array->set(31);
        $array->set(32);

        for ($i = 0; $i < $array->getSize(); ++$i) {
            if ($i <= 31) {
                $expected = 31;
            } elseif ($i <= 32) {
                $expected = 32;
            } else {
                $expected = 63;
            }

            $this->assertEqualsWithDelta($expected, $i, $array->getNextSet($i));
        }
    }

    public function test_get_next_set4(): void
    {
        $array = new BitArray(63);
        $array->set(33);
        $array->set(40);

        for ($i = 0; $i < $array->getSize(); ++$i) {
            if ($i <= 33) {
                $expected = 33;
            } elseif ($i <= 40) {
                $expected = 40;
            } else {
                $expected = 63;
            }

            $this->assertEqualsWithDelta($expected, $i, $array->getNextSet($i));
        }
    }

    public function test_get_next_set5(): void
    {
        mt_srand(0xDE_AD_BE_EF);

        for ($i = 0; $i < 10; ++$i) {
            $array = new BitArray(mt_rand(1, 100));
            $numSet = mt_rand(0, 19);

            for ($j = 0; $j < $numSet; ++$j) {
                $array->set(mt_rand(0, $array->getSize() - 1));
            }

            $numQueries = mt_rand(0, 19);

            for ($j = 0; $j < $numQueries; ++$j) {
                $query = mt_rand(0, $array->getSize() - 1);
                $expected = $query;

                while ($expected < $array->getSize() && !$array->get($expected)) {
                    ++$expected;
                }

                $actual = $array->getNextSet($query);

                $this->assertSame($expected, $actual);
            }
        }
    }

    public function test_set_bulk(): void
    {
        $array = new BitArray(64);
        $array->setBulk(32, 0xFF_FF_00_00);

        for ($i = 0; $i < 48; ++$i) {
            $this->assertFalse($array->get($i));
        }

        for ($i = 48; $i < 64; ++$i) {
            $this->assertTrue($array->get($i));
        }
    }

    public function test_clear(): void
    {
        $array = new BitArray(32);

        for ($i = 0; $i < 32; ++$i) {
            $array->set($i);
        }

        $array->clear();

        for ($i = 0; $i < 32; ++$i) {
            $this->assertFalse($array->get($i));
        }
    }

    public function test_append_bit_grows_the_logical_size(): void
    {
        $array = new BitArray();

        $array->appendBit(true);
        $array->appendBit(false);

        $this->assertSame(2, $array->getSize());
        $this->assertTrue($array->get(0));
        $this->assertFalse($array->get(1));
    }

    public function test_get_array(): void
    {
        $array = new BitArray(64);
        $array->set(0);
        $array->set(63);

        $ints = $array->getBitArray();

        $this->assertSame(1, $ints[0]);
        $this->assertSame(0x80_00_00_00, $ints[1]);
    }

    public function test_is_range(): void
    {
        $array = new BitArray(64);
        $this->assertTrue($array->isRange(0, 64, false));
        $this->assertFalse($array->isRange(0, 64, true));

        $array->set(32);
        $this->assertTrue($array->isRange(32, 33, true));

        $array->set(31);
        $this->assertTrue($array->isRange(31, 33, true));

        $array->set(34);
        $this->assertFalse($array->isRange(31, 35, true));

        for ($i = 0; $i < 31; ++$i) {
            $array->set($i);
        }

        $this->assertTrue($array->isRange(0, 33, true));

        for ($i = 33; $i < 64; ++$i) {
            $array->set($i);
        }

        $this->assertTrue($array->isRange(0, 64, true));
        $this->assertFalse($array->isRange(0, 64, false));
    }
}
