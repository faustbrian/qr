<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Generator\Integration;

use Cline\Qr\Generator\Internal\Exception\InvalidArgumentException;
use Cline\Qr\Generator\Renderer\Color\Alpha;
use Cline\Qr\Generator\Renderer\Color\Rgb;
use Cline\Qr\Generator\Renderer\GDLibRenderer;
use Cline\Qr\Generator\Renderer\RendererStyle\EyeFill;
use Cline\Qr\Generator\Renderer\RendererStyle\Fill;
use Cline\Qr\Generator\Renderer\RendererStyle\Gradient;
use Cline\Qr\Generator\Renderer\RendererStyle\GradientType;
use Cline\Qr\Generator\Writer;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Tests\Generator\Support\MatchesQrSnapshots;

use function sys_get_temp_dir;
use function tempnam;
use function unlink;

/**
 * @author Brian Faust <brian@cline.sh>
 * @internal
 */
#[Group('integration')]
final class GDLibRenderingTest extends TestCase
{
    use MatchesQrSnapshots;

    #[RequiresPhpExtension('gd')]
    public function test_generic_qr_code(): void
    {
        $renderer = new GDLibRenderer(400);
        $writer = new Writer($renderer);
        $tempName = tempnam(sys_get_temp_dir(), 'test').'.png';
        $writer->writeFile('Hello World!', $tempName);

        $this->assertMatchesImageSnapshot($tempName);
        unlink($tempName);
    }

    #[RequiresPhpExtension('gd')]
    public function test_different_colors_qr_code(): void
    {
        $renderer = new GDLibRenderer(
            400,
            10,
            'png',
            9,
            Fill::withForegroundColor(
                new Alpha(25, new Rgb(0, 0, 0)),
                new Rgb(0, 0, 0),
                new EyeFill(
                    new Rgb(220, 50, 50),
                    new Alpha(50, new Rgb(220, 50, 50)),
                ),
                new EyeFill(
                    new Rgb(50, 220, 50),
                    new Alpha(50, new Rgb(50, 220, 50)),
                ),
                new EyeFill(
                    new Rgb(50, 50, 220),
                    new Alpha(50, new Rgb(50, 50, 220)),
                ),
            ),
        );
        $writer = new Writer($renderer);
        $tempName = tempnam(sys_get_temp_dir(), 'test').'.png';
        $writer->writeFile('Hello World!', $tempName);

        $this->assertMatchesImageSnapshot($tempName);
        unlink($tempName);
    }

    #[RequiresPhpExtension('gd')]
    public function test_fails_on_gradient(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('GDLibRenderer does not support gradients');

        new GDLibRenderer(
            400,
            10,
            'png',
            9,
            Fill::withForegroundGradient(
                new Alpha(25, new Rgb(0, 0, 0)),
                new Gradient(
                    new Rgb(255, 255, 0),
                    new Rgb(255, 0, 255),
                    GradientType::DIAGONAL,
                ),
                new EyeFill(
                    new Rgb(220, 50, 50),
                    new Alpha(50, new Rgb(220, 50, 50)),
                ),
                new EyeFill(
                    new Rgb(50, 220, 50),
                    new Alpha(50, new Rgb(50, 220, 50)),
                ),
                new EyeFill(
                    new Rgb(50, 50, 220),
                    new Alpha(50, new Rgb(50, 50, 220)),
                ),
            ),
        );
    }

    #[RequiresPhpExtension('gd')]
    public function test_fails_on_invalid_format(): void
    {
        $renderer = new GDLibRenderer(400, 4, 'tiff');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Supported image formats are jpeg, png and gif, got: tiff');

        $writer = new Writer($renderer);
        $tempName = tempnam(sys_get_temp_dir(), 'test').'.png';
        $writer->writeFile('Hello World!', $tempName);
    }
}
