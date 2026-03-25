<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Generator\Renderer\Color;

use Cline\Qr\Generator\Renderer\Color\Cmyk;
use Cline\Qr\Generator\Renderer\Color\Gray;
use Cline\Qr\Generator\Renderer\Color\Rgb;
use PHPUnit\Framework\TestCase;

/**
 * @author Brian Faust <brian@cline.sh>
 * @internal
 */
final class RgbTest extends TestCase
{
    public function test_to_rgb(): void
    {
        $rgb = new Rgb(10, 20, 30);
        $this->assertSame($rgb, $rgb->toRgb(), 'toRgb should return $this');
    }

    /**
     * Tests RGB to CMYK conversion, focusing on:
     * 1. Handling the special case RGB(0, 0, 0) -> CMYK(0, 0, 0, 100).
     * 2. Correct application of rounding.
     */
    public function test_to_cmyk(): void
    {
        // Special Case: Black (0, 0, 0) -> C:0, M:0, Y:0, K:100
        $rgbBlack = new Rgb(0, 0, 0);
        $this->assertEquals(
            new Cmyk(0, 0, 0, 100),
            $rgbBlack->toCmyk(),
            'RGB Black to CMYK (special case)',
        );

        // White (255, 255, 255) -> C:0, M:0, Y:0, K:0
        $rgbWhite = new Rgb(255, 255, 255);
        $this->assertEquals(
            new Cmyk(0, 0, 0, 0),
            $rgbWhite->toCmyk(),
            'RGB White to CMYK',
        );

        // Pure Red (255, 0, 0) -> C:0, M:100, Y:100, K:0
        $rgbRed = new Rgb(255, 0, 0);
        $this->assertEquals(
            new Cmyk(0, 100, 100, 0),
            $rgbRed->toCmyk(),
            'RGB Red to CMYK',
        );

        // Complex Color checking rounding: RGB(100, 150, 200)
        // K=22 (round(100 * 0.2156))
        // C=50 (round(100 * 0.3922 / 0.7844))
        // M=25 (round(100 * 0.1961 / 0.7844))
        // Y=0
        $rgbCustom = new Rgb(100, 150, 200);
        $this->assertEquals(
            new Cmyk(50, 25, 0, 22),
            $rgbCustom->toCmyk(),
            'RGB Custom to CMYK (rounding check)',
        );
    }

    /**
     * Tests RGB to Gray conversion, focusing on:
     * 1. Correct luminance coefficients (0.2126, 0.7152, 0.0722).
     * 2. Integer-based calculation to avoid floating-point precision loss.
     * 3. Correct application of rounding.
     */
    public function test_to_gray(): void
    {
        // Black (0, 0, 0) -> Gray(0)
        $rgbBlack = new Rgb(0, 0, 0);
        $this->assertEquals(
            new Gray(0),
            $rgbBlack->toGray(),
            'RGB Black to Gray',
        );

        // White (255, 255, 255) -> Gray(100)
        $rgbWhite = new Rgb(255, 255, 255);
        $this->assertEquals(
            new Gray(100),
            $rgbWhite->toGray(),
            'RGB White to Gray',
        );

        // Pure Red (255, 0, 0)
        // round((255 * 2126) / 25500) = round(21.26) = 21
        $rgbRed = new Rgb(255, 0, 0);
        $this->assertEquals(
            new Gray(21),
            $rgbRed->toGray(),
            'RGB Red to Gray',
        );

        // Pure Green (0, 255, 0)
        // round((255 * 7152) / 25500) = round(71.52) = 72
        $rgbGreen = new Rgb(0, 255, 0);
        $this->assertEquals(
            new Gray(72),
            $rgbGreen->toGray(),
            'RGB Green to Gray',
        );

        // Complex Color checking rounding: RGB(100, 150, 200)
        // round((100*2126 + 150*7152 + 200*722) / 25500) = round(56.07...) = 56
        $rgbCustom = new Rgb(100, 150, 200);
        $this->assertEquals(
            new Gray(56),
            $rgbCustom->toGray(),
            'RGB Custom to Gray (rounding check)',
        );
    }
}
