<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\ImageData;

use Cline\Qr\Exception\InvalidArgumentException;
use Cline\Qr\Exception\RuntimeException;
use Cline\Qr\Logo\LogoInterface;
use GdImage;

use const FILTER_VALIDATE_URL;

use function array_combine;
use function array_keys;
use function array_map;
use function base64_encode;
use function error_clear_last;
use function error_get_last;
use function file_get_contents;
use function filter_var;
use function function_exists;
use function get_headers;
use function imagecreatefromstring;
use function imagesx;
use function imagesy;
use function is_array;
use function is_string;
use function mb_strtolower;
use function mime_content_type;
use function preg_match;
use function sprintf;
use function throw_if;
use function throw_unless;

/**
 * Loaded logo image payload plus the derived render dimensions.
 *
 * This value object centralizes logo file loading, mime detection, SVG/GD
 * branching, and optional resize calculations so writers can consume one
 * normalized representation regardless of source format.
 *
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class LogoImageData
{
    private function __construct(
        private string $data,
        private ?GdImage $image,
        private string $mimeType,
        private int $width,
        private int $height,
        private bool $punchoutBackground,
    ) {}

    /**
     * Load and normalize the logo source defined by the caller.
     *
     * Raster logos are decoded into a `GdImage`, while SVG logos keep their raw
     * string payload and require explicit target dimensions because their size
     * cannot be inferred safely by the raster writers.
     */
    public static function createForLogo(LogoInterface $logo): self
    {
        error_clear_last();
        $data = file_get_contents($logo->getPath());

        if (!is_string($data)) {
            $errorDetails = error_get_last()['message'] ?? 'invalid data';

            throw RuntimeException::withMessage(
                sprintf(
                    'Could not read logo image data from path "%s": %s',
                    $logo->getPath(),
                    $errorDetails,
                ),
            );
        }

        if (false !== filter_var($logo->getPath(), FILTER_VALIDATE_URL)) {
            $mimeType = self::detectMimeTypeFromUrl($logo->getPath());
        } else {
            $mimeType = self::detectMimeTypeFromPath($logo->getPath());
        }

        $width = $logo->getResizeToWidth();
        $height = $logo->getResizeToHeight();

        if ('image/svg+xml' === $mimeType) {
            throw_if(
                null === $width || null === $height,
                InvalidArgumentException::withMessage(
                    'SVG Logos require an explicitly set resize width and height',
                ),
            );

            return new self($data, null, $mimeType, $width, $height, $logo->getPunchoutBackground());
        }

        throw_unless(
            function_exists('imagecreatefromstring'),
            RuntimeException::withMessage(
                'Function "imagecreatefromstring" does not exist: check your GD installation',
            ),
        );

        error_clear_last();
        $image = imagecreatefromstring($data);

        if (!$image) {
            $errorDetails = error_get_last()['message'] ?? 'invalid data';

            throw RuntimeException::withMessage(
                sprintf(
                    'Unable to parse image data at path "%s": %s',
                    $logo->getPath(),
                    $errorDetails,
                ),
            );
        }

        // No target width and height specified: use from original image
        if (null !== $width && null !== $height) {
            return new self($data, $image, $mimeType, $width, $height, $logo->getPunchoutBackground());
        }

        // Only target width specified: calculate height
        if (null !== $width) {
            return new self($data, $image, $mimeType, $width, (int) (imagesy($image) * $width / imagesx($image)), $logo->getPunchoutBackground());
        }

        // Only target height specified: calculate width
        if (null !== $height) {
            return new self($data, $image, $mimeType, (int) (imagesx($image) * $height / imagesy($image)), $height, $logo->getPunchoutBackground());
        }

        return new self($data, $image, $mimeType, imagesx($image), imagesy($image), $logo->getPunchoutBackground());
    }

    /**
     * Return the raw logo bytes.
     */
    public function getData(): string
    {
        return $this->data;
    }

    /**
     * Return the decoded raster image resource.
     *
     * @throws RuntimeException when the logo is an SVG and therefore has no `GdImage`
     */
    public function getImage(): GdImage
    {
        throw_unless(
            $this->image instanceof GdImage,
            RuntimeException::withMessage('SVG Images have no image resource'),
        );

        return $this->image;
    }

    /**
     * Return the detected logo mime type.
     */
    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    /**
     * Return the target render width.
     */
    public function getWidth(): int
    {
        return $this->width;
    }

    /**
     * Return the target render height.
     */
    public function getHeight(): int
    {
        return $this->height;
    }

    /**
     * Return whether the logo should punch out the QR background beneath it.
     */
    public function getPunchoutBackground(): bool
    {
        return $this->punchoutBackground;
    }

    /**
     * Return the logo as a base64 data URI.
     */
    public function createDataUri(): string
    {
        return 'data:'.$this->mimeType.';base64,'.base64_encode($this->data);
    }

    /**
     * Determine the mime type for a remote logo URL from response headers.
     */
    private static function detectMimeTypeFromUrl(string $url): string
    {
        $headers = get_headers($url, true);

        if (!is_array($headers)) {
            throw RuntimeException::withMessage(
                sprintf(
                    'Could not retrieve headers to determine content type for logo URL "%s"',
                    $url,
                ),
            );
        }

        $headers = array_combine(
            array_map(
                static fn (string|int $key): string => mb_strtolower((string) $key),
                array_keys($headers),
            ),
            $headers,
        );

        if (!isset($headers['content-type'])) {
            throw RuntimeException::withMessage(
                sprintf(
                    'Content type could not be determined for logo URL "%s"',
                    $url,
                ),
            );
        }

        return is_array($headers['content-type']) ? $headers['content-type'][1] : $headers['content-type'];
    }

    /**
     * Determine the mime type for a local logo path.
     */
    private static function detectMimeTypeFromPath(string $path): string
    {
        throw_unless(
            function_exists('mime_content_type'),
            RuntimeException::withMessage(
                'You need the ext-fileinfo extension to determine logo mime type',
            ),
        );

        error_clear_last();
        $mimeType = mime_content_type($path);

        if (!is_string($mimeType)) {
            $errorDetails = error_get_last()['message'] ?? 'invalid data';

            throw RuntimeException::withMessage(
                sprintf('Could not determine mime type: %s', $errorDetails),
            );
        }

        throw_unless(
            preg_match('#^image/#', $mimeType),
            InvalidArgumentException::withMessage('Logo path is not an image'),
        );

        // Passing mime type image/svg results in invisible images
        if ('image/svg' === $mimeType) {
            return 'image/svg+xml';
        }

        return $mimeType;
    }
}
