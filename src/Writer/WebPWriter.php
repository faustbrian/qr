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
use Cline\Qr\Writer\Result\ResultInterface;
use Cline\Qr\Writer\Result\WebPResult;

use function array_key_exists;
use function is_numeric;
use function sprintf;

/**
 * WebP writer built on the shared GD pipeline.
 *
 * The writer delegates rendering and validation support to `GdTrait`, then
 * wraps the image in a WebP-specific result object with a configurable quality
 * setting.
 *
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class WebPWriter implements ValidatingWriterInterface, WriterInterface
{
    use GdTrait;

    public const string WRITER_OPTION_QUALITY = 'quality';

    /**
     * Render the QR code through GD and return a WebP result wrapper.
     */
    public function write(QrCodeInterface $qrCode, ?LogoInterface $logo = null, ?LabelInterface $label = null, array $options = []): ResultInterface
    {
        if (!isset($options[self::WRITER_OPTION_QUALITY])) {
            $options[self::WRITER_OPTION_QUALITY] = -1;
        }

        $gdResult = $this->writeGd($qrCode, $logo, $label, $options);

        return new WebPResult(
            $gdResult->getMatrix(),
            $gdResult->getImage(),
            $this->intOption($options, self::WRITER_OPTION_QUALITY, -1),
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
}
