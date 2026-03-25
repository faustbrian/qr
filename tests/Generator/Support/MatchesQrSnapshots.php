<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Generator\Support;

use DOMDocument;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;

use function abs;
use function dirname;
use function file_exists;
use function file_get_contents;
use function imagecolorat;
use function imagecreatefromstring;
use function imagesx;
use function imagesy;
use function mb_strlen;
use function mb_substr;
use function preg_replace;
use function sprintf;
use function str_replace;
use function str_starts_with;
use function throw_if;
use function throw_unless;
use function ucwords;

/**
 * @author Brian Faust <brian@cline.sh>
 */
trait MatchesQrSnapshots
{
    private const CHANNEL_TOLERANCE = 48;

    /** @var array<string, int> */
    private array $snapshotAssertions = [];

    protected function assertMatchesImageSnapshot(string $actualPath): void
    {
        $expectedPath = $this->snapshotPath('png');

        $actual = imagecreatefromstring((string) file_get_contents($actualPath));
        $expected = imagecreatefromstring((string) file_get_contents($expectedPath));

        throw_if($actual === false || $expected === false, RuntimeException::class, 'Failed to load PNG snapshot images.');

        Assert::assertSame(imagesx($expected), imagesx($actual));
        Assert::assertSame(imagesy($expected), imagesy($actual));

        for ($y = 0; $y < imagesy($expected); ++$y) {
            for ($x = 0; $x < imagesx($expected); ++$x) {
                $expectedColor = $this->normalizeColor(imagecolorat($expected, $x, $y));
                $actualColor = $this->normalizeColor(imagecolorat($actual, $x, $y));

                Assert::assertLessThanOrEqual(
                    self::CHANNEL_TOLERANCE,
                    abs($expectedColor['red'] - $actualColor['red']),
                    sprintf('Red channel mismatch at (%d, %d)', $x, $y),
                );
                Assert::assertLessThanOrEqual(
                    self::CHANNEL_TOLERANCE,
                    abs($expectedColor['green'] - $actualColor['green']),
                    sprintf('Green channel mismatch at (%d, %d)', $x, $y),
                );
                Assert::assertLessThanOrEqual(
                    self::CHANNEL_TOLERANCE,
                    abs($expectedColor['blue'] - $actualColor['blue']),
                    sprintf('Blue channel mismatch at (%d, %d)', $x, $y),
                );
            }
        }
    }

    protected function assertMatchesXmlSnapshot(string $actualXml): void
    {
        $expectedPath = $this->snapshotPath('xml');

        Assert::assertSame(
            $this->normalizeXml((string) file_get_contents($expectedPath)),
            $this->normalizeXml($actualXml),
        );
    }

    private function snapshotPath(string $extension): string
    {
        throw_unless($this instanceof TestCase, RuntimeException::class, 'Snapshot assertions require a PHPUnit test case.');

        $testName = $this->name();
        $count = ($this->snapshotAssertions[$testName] ?? 0) + 1;
        $this->snapshotAssertions[$testName] = $count;

        $class = new ReflectionClass($this)->getShortName();

        foreach ($this->snapshotTestNameCandidates($testName) as $candidate) {
            $path = dirname(__DIR__).sprintf(
                '/fixtures/integration/__snapshots__/%s__%s__%d.%s',
                $class,
                $candidate,
                $count,
                $extension,
            );

            if (file_exists($path)) {
                return $path;
            }
        }

        $fallback = dirname(__DIR__).sprintf(
            '/fixtures/integration/__snapshots__/%s__%s__%d.%s',
            $class,
            $testName,
            $count,
            $extension,
        );

        Assert::assertFileExists($fallback);

        return $fallback;
    }

    private function normalizeXml(string $xml): string
    {
        $xml = (string) preg_replace('/g1-[a-f0-9]+/i', 'g1-normalized', $xml);

        $document = new DOMDocument();
        $document->preserveWhiteSpace = false;
        $document->formatOutput = false;
        $document->loadXML($xml);

        return $document->saveXML() ?: $xml;
    }

    /**
     * @return array{red: int, green: int, blue: int}
     */
    private function normalizeColor(int $color): array
    {
        return [
            'red' => ($color >> 16) & 0xFF,
            'green' => ($color >> 8) & 0xFF,
            'blue' => $color & 0xFF,
        ];
    }

    /**
     * @return list<string>
     */
    private function snapshotTestNameCandidates(string $testName): array
    {
        $candidates = [$testName];

        if (str_starts_with($testName, 'test_')) {
            $suffix = mb_substr($testName, mb_strlen('test_'));

            if ($suffix !== false) {
                $candidates[] = 'test'.str_replace(' ', '', ucwords(str_replace('_', ' ', $suffix)));
            }
        }

        return $candidates;
    }
}
