<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Generator\Integration;

use Cline\Qr\Generator\Renderer\Color\Rgb;
use Cline\Qr\Generator\Renderer\Image\SvgImageBackEnd;
use Cline\Qr\Generator\Renderer\ImageRenderer;
use Cline\Qr\Generator\Renderer\RendererStyle\EyeFill;
use Cline\Qr\Generator\Renderer\RendererStyle\Fill;
use Cline\Qr\Generator\Renderer\RendererStyle\Gradient;
use Cline\Qr\Generator\Renderer\RendererStyle\GradientType;
use Cline\Qr\Generator\Renderer\RendererStyle\RendererStyle;
use Cline\Qr\Generator\Writer;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Tests\Generator\Support\MatchesQrSnapshots;

/**
 * @author Brian Faust <brian@cline.sh>
 * @internal
 */
#[Group('integration')]
final class SVGRenderingTest extends TestCase
{
    use MatchesQrSnapshots;

    public function test_generic_qr_code(): void
    {
        $renderer = new ImageRenderer(
            new RendererStyle(400),
            new SvgImageBackEnd(),
        );
        $writer = new Writer($renderer);
        $svg = $writer->writeString('Hello World!');

        $this->assertMatchesXmlSnapshot($svg);
    }

    public function test_qr_with_gradient_generates_different_ids_for_different_gradients(): void
    {
        $types = [GradientType::HORIZONTAL, GradientType::VERTICAL];

        foreach ($types as $type) {
            $gradient = new Gradient(
                new Rgb(0, 0, 0),
                new Rgb(255, 0, 0),
                $type,
            );
            $renderer = new ImageRenderer(
                new RendererStyle(
                    size: 400,
                    fill: Fill::withForegroundGradient(
                        new Rgb(255, 255, 255),
                        $gradient,
                        EyeFill::inherit(),
                        EyeFill::inherit(),
                        EyeFill::inherit(),
                    ),
                ),
                new SvgImageBackEnd(),
            );
            $writer = new Writer($renderer);
            $svg = $writer->writeString('Hello World!');

            $this->assertMatchesXmlSnapshot($svg);
        }
    }
}
