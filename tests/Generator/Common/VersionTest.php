<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Qr\Generator\Common\ErrorCorrectionLevel;
use Cline\Qr\Generator\Common\Version;

dataset('versions', function (): iterable {
    $array = [];

    for ($i = 1; $i <= 40; ++$i) {
        $array[] = [$i, 4 * $i + 17];
    }

    return $array;
});

dataset('decode version information cases', [
    [7, 0x0_7C_94],
    [12, 0x0_C7_62],
    [17, 0x1_14_5D],
    [22, 0x1_68_C9],
    [27, 0x1_B0_8E],
    [32, 0x2_09_D5],
]);

test('version for number', function (int $versionNumber, int $dimension): void {
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
})->with('versions');

test('get provisional version for dimension', function (int $versionNumber, int $dimension): void {
    $this->assertSame(
        $versionNumber,
        Version::getProvisionalVersionForDimension($dimension)->getVersionNumber(),
    );
})->with('versions');

test('decode version information', function (int $expectedVersion, int $mask): void {
    $version = Version::decodeVersionInformation($mask);
    $this->assertInstanceOf(Version::class, $version);
    $this->assertSame($expectedVersion, $version->getVersionNumber());
})->with('decode version information cases');
