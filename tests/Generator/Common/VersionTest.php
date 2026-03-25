<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Generator\Common;

use Cline\Qr\Generator\Common\ErrorCorrectionLevel;
use Cline\Qr\Generator\Common\Version;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function count;

/**
 * @author Brian Faust <brian@cline.sh>
 * @internal
 */
final class VersionTest extends TestCase
{
    #[DataProvider('versions')]
    public function test_version_for_number(int $versionNumber, int $dimension): void
    {
        $version = Version::getVersionForNumber($versionNumber);

        $this->assertNotNull($version);
        $this->assertSame($versionNumber, $version->getVersionNumber());
        $this->assertNotNull($version->getAlignmentPatternCenters());

        if ($versionNumber > 1) {
            $this->assertGreaterThan(0, count($version->getAlignmentPatternCenters()));
        }

        $this->assertSame($dimension, $version->getDimensionForVersion());
        $this->assertNotNull($version->getEcBlocksForLevel(ErrorCorrectionLevel::H));
        $this->assertNotNull($version->getEcBlocksForLevel(ErrorCorrectionLevel::L));
        $this->assertNotNull($version->getEcBlocksForLevel(ErrorCorrectionLevel::M));
        $this->assertNotNull($version->getEcBlocksForLevel(ErrorCorrectionLevel::Q));
        $this->assertNotNull($version->buildFunctionPattern());
    }

    #[DataProvider('versions')]
    public function test_get_provisional_version_for_dimension(int $versionNumber, int $dimension): void
    {
        $this->assertSame(
            $versionNumber,
            Version::getProvisionalVersionForDimension($dimension)->getVersionNumber(),
        );
    }

    public static function versions(): iterable
    {
        $array = [];

        for ($i = 1; $i <= 40; ++$i) {
            $array[] = [$i, 4 * $i + 17];
        }

        return $array;
    }

    #[DataProvider('provideDecode_version_informationCases')]
    public function test_decode_version_information(int $expectedVersion, int $mask): void
    {
        $version = Version::decodeVersionInformation($mask);
        $this->assertInstanceOf(Version::class, $version);
        $this->assertSame($expectedVersion, $version->getVersionNumber());
    }

    public static function provideDecode_version_informationCases(): iterable
    {
        yield [7, 0x0_7C_94];

        yield [12, 0x0_C7_62];

        yield [17, 0x1_14_5D];

        yield [22, 0x1_68_C9];

        yield [27, 0x1_B0_8E];

        yield [32, 0x2_09_D5];
    }
}
