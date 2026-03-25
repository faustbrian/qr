<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Writer;

use Cline\Qr\Exception\InvalidArgumentException;
use Cline\Qr\Label\LabelInterface;
use Cline\Qr\Logo\LogoInterface;
use Cline\Qr\QrCodeInterface;
use Cline\Qr\Writer\Result\PngResult;
use Cline\Qr\Writer\Result\ResultInterface;

use function array_key_exists;
use function is_numeric;
use function sprintf;

/**
 * PNG writer built on the shared GD pipeline.
 *
 * Besides the core GD rendering flow, this writer manages PNG-specific options
 * such as compression level and optional palette reduction.
 *
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class PngWriter implements ValidatingWriterInterface, WriterInterface
{
    use GdTrait;

    public const string WRITER_OPTION_COMPRESSION_LEVEL = 'compression_level';

    public const string WRITER_OPTION_NUMBER_OF_COLORS = 'number_of_colors';

    /**
     * Render the QR code through GD and return a PNG result wrapper.
     */
    public function write(QrCodeInterface $qrCode, ?LogoInterface $logo = null, ?LabelInterface $label = null, array $options = []): ResultInterface
    {
        if (!isset($options[self::WRITER_OPTION_COMPRESSION_LEVEL])) {
            $options[self::WRITER_OPTION_COMPRESSION_LEVEL] = -1;
        }

        if (!array_key_exists(self::WRITER_OPTION_NUMBER_OF_COLORS, $options)) {
            $options[self::WRITER_OPTION_NUMBER_OF_COLORS] = match (true) {
                $qrCode->getBackgroundColor()->getAlpha() > 0 || $qrCode->getForegroundColor()->getAlpha() > 0 => null,
                $logo instanceof LogoInterface => null,
                default => 16,
            };
        }

        $gdResult = $this->writeGd($qrCode, $logo, $label, $options);

        return new PngResult(
            $gdResult->getMatrix(),
            $gdResult->getImage(),
            $this->intOption($options, self::WRITER_OPTION_COMPRESSION_LEVEL, -1),
            $this->nullableIntOption($options, self::WRITER_OPTION_NUMBER_OF_COLORS),
        );
    }

    /**
     * Read a numeric writer option as an integer.
     *
     * @param array<string, mixed> $options
     */
    private function intOption(array $options, string $key, int $default): int
    {
        if (!array_key_exists($key, $options)) {
            return $default;
        }

        $value = $options[$key];

        if (!is_numeric($value)) {
            throw InvalidArgumentException::withMessage(
                sprintf('Writer option "%s" must be numeric', $key),
            );
        }

        return (int) $value;
    }

    /**
     * Read an optional numeric writer option as an integer or `null`.
     *
     * @param array<string, mixed> $options
     */
    private function nullableIntOption(array $options, string $key): ?int
    {
        if (!array_key_exists($key, $options) || null === $options[$key]) {
            return null;
        }

        return $this->intOption($options, $key, 0);
    }
}
