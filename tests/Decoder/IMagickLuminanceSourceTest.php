<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Decoder;

use Cline\Qr\Decoder\IMagickLuminanceSource;
use Imagick;
use PHPUnit\Framework\TestCase;

use function dirname;
use function extension_loaded;
use function ini_get;
use function ini_set;

/**
 * @author Brian Faust <brian@cline.sh>
 * @internal
 */
final class IMagickLuminanceSourceTest extends TestCase
{
    public function test_it_exports_and_crops_luminance_data(): void
    {
        if (!extension_loaded('imagick')) {
            $this->markTestSkipped('The imagick extension is not installed.');
        }

        $originalMemoryLimit = ini_get('memory_limit');
        ini_set('memory_limit', '-1');

        $image = new Imagick(dirname(__DIR__).'/Decoder/fixtures/qrcodes/hello_world.png');

        try {
            $source = new IMagickLuminanceSource($image, $image->getImageWidth(), $image->getImageHeight());

            $this->assertIsArray($source->getMatrix());

            $cropped = $source->crop(0, 0, 1, 1);

            $this->assertInstanceOf(IMagickLuminanceSource::class, $cropped);
            $this->assertSame([0 => $source->getMatrix()[0]], $cropped->getMatrix());
        } finally {
            ini_set('memory_limit', $originalMemoryLimit ?: '128M');
        }
    }
}
