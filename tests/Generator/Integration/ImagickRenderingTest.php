<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Generator\Integration;

use Cline\Qr\Decoder\QrReader;
use Cline\Qr\Generator\Renderer\Color\Rgb;
use Cline\Qr\Generator\Renderer\Eye\PointyEye;
use Cline\Qr\Generator\Renderer\Eye\SquareEye;
use Cline\Qr\Generator\Renderer\Image\ImagickImageBackEnd;
use Cline\Qr\Generator\Renderer\ImageRenderer;
use Cline\Qr\Generator\Renderer\Module\SquareModule;
use Cline\Qr\Generator\Renderer\RendererStyle\EyeFill;
use Cline\Qr\Generator\Renderer\RendererStyle\Fill;
use Cline\Qr\Generator\Renderer\RendererStyle\Gradient;
use Cline\Qr\Generator\Renderer\RendererStyle\GradientType;
use Cline\Qr\Generator\Renderer\RendererStyle\RendererStyle;
use Cline\Qr\Generator\Writer;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Tests\Generator\Support\MatchesQrSnapshots;

use function filesize;
use function getimagesize;
use function md5_file;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

/**
 * @author Brian Faust <brian@cline.sh>
 * @internal
 */
#[Group('integration')]
final class ImagickRenderingTest extends TestCase
{
    use MatchesQrSnapshots;

    #[RequiresPhpExtension('imagick')]
    public function test_generic_qr_code(): void
    {
        $renderer = new ImageRenderer(
            new RendererStyle(400),
            new ImagickImageBackEnd(),
        );
        $writer = new Writer($renderer);
        $tempName = tempnam(sys_get_temp_dir(), 'test').'.png';
        $writer->writeFile('Hello World!', $tempName);

        $this->assertMatchesImageSnapshot($tempName);
        unlink($tempName);
    }

    #[RequiresPhpExtension('imagick')]
    public function test_issue79(): void
    {
        $eye = SquareEye::instance();
        $squareModule = SquareModule::instance();

        $eyeFill = new EyeFill(
            new Rgb(100, 100, 55),
            new Rgb(100, 100, 255),
        );
        $gradient = new Gradient(
            new Rgb(100, 100, 55),
            new Rgb(100, 100, 255),
            GradientType::HORIZONTAL,
        );

        $renderer = new ImageRenderer(
            new RendererStyle(
                400,
                2,
                $squareModule,
                $eye,
                Fill::withForegroundGradient(
                    new Rgb(255, 255, 255),
                    $gradient,
                    $eyeFill,
                    $eyeFill,
                    $eyeFill,
                ),
            ),
            new ImagickImageBackEnd(),
        );
        $writer = new Writer($renderer);
        $tempName = tempnam(sys_get_temp_dir(), 'test').'.png';
        $writer->writeFile('https://apiroad.net/very-long-url', $tempName);

        $this->assertMatchesImageSnapshot($tempName);
        unlink($tempName);
    }

    #[RequiresPhpExtension('imagick')]
    public function test_issue105(): void
    {
        $squareModule = SquareModule::instance();
        $pointyEye = PointyEye::instance();
        $contentWithoutEyeColor = 'rotation without eye color';
        $contentWithEyeColor = 'rotation with eye color';

        $renderer1 = new ImageRenderer(
            new RendererStyle(
                400,
                2,
                $squareModule,
                $pointyEye,
                Fill::uniformColor(
                    new Rgb(255, 255, 255),
                    new Rgb(0, 0, 255),
                ),
            ),
            new ImagickImageBackEnd(),
        );
        $writer1 = new Writer($renderer1);
        $tempName1 = tempnam(sys_get_temp_dir(), 'test').'.png';
        $writer1->writeFile($contentWithoutEyeColor, $tempName1);

        $eyeFill = new EyeFill(
            new Rgb(255, 0, 0),
            new Rgb(0, 255, 0),
        );

        $renderer2 = new ImageRenderer(
            new RendererStyle(
                400,
                2,
                $squareModule,
                $pointyEye,
                Fill::withForegroundColor(
                    new Rgb(255, 255, 255),
                    new Rgb(0, 0, 255),
                    $eyeFill,
                    $eyeFill,
                    $eyeFill,
                ),
            ),
            new ImagickImageBackEnd(),
        );
        $writer2 = new Writer($renderer2);
        $tempName2 = tempnam(sys_get_temp_dir(), 'test').'.png';
        $writer2->writeFile($contentWithEyeColor, $tempName2);

        $this->assertSame($contentWithoutEyeColor, new QrReader($tempName1)->text());
        $this->assertGreaterThan(0, filesize($tempName2));
        $this->assertNotSame(md5_file($tempName1), md5_file($tempName2));
        $this->assertSame(getimagesize($tempName1)[0] ?? null, getimagesize($tempName2)[0] ?? null);
        $this->assertSame(getimagesize($tempName1)[1] ?? null, getimagesize($tempName2)[1] ?? null);

        unlink($tempName1);
        unlink($tempName2);
    }
}
