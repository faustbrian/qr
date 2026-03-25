<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Decoder;

use Cline\Qr\Decoder\AbstractResultPoint;
use Cline\Qr\Decoder\Common\AbstractGlobalHistogramBinarizer;
use Cline\Qr\Decoder\Common\GlobalHistogramBinarizer;
use Cline\Qr\Decoder\Common\HybridBinarizer;
use Cline\Qr\Decoder\Qrcode\Detector\AlignmentPattern;
use Cline\Qr\Decoder\Qrcode\Detector\FinderPattern;
use Cline\Qr\Decoder\ResultPoint;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * @author Brian Faust <brian@cline.sh>
 * @internal
 */
final class BinarizerHierarchyTest extends TestCase
{
    public function test_shared_histogram_layer_is_abstract(): void
    {
        $this->assertTrue(
            new ReflectionClass(AbstractGlobalHistogramBinarizer::class)->isAbstract(),
        );
    }

    public function test_concrete_binarizers_are_final(): void
    {
        $this->assertTrue(
            new ReflectionClass(GlobalHistogramBinarizer::class)->isFinal(),
        );
        $this->assertTrue(
            new ReflectionClass(HybridBinarizer::class)->isFinal(),
        );
    }

    public function test_shared_result_point_layer_is_abstract(): void
    {
        $this->assertTrue(
            new ReflectionClass(AbstractResultPoint::class)->isAbstract(),
        );
    }

    public function test_concrete_result_points_are_final(): void
    {
        $this->assertTrue(
            new ReflectionClass(ResultPoint::class)->isFinal(),
        );
        $this->assertTrue(
            new ReflectionClass(AlignmentPattern::class)->isFinal(),
        );
        $this->assertTrue(
            new ReflectionClass(FinderPattern::class)->isFinal(),
        );
    }
}
