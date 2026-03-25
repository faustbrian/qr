<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Decoder;

use Cline\Qr\Decoder\Common\HybridBinarizer;
use Cline\Qr\Decoder\Qrcode\QRCodeReader;
use Exception;
use GdImage;
use Imagick;

use function extension_loaded;
use function file_get_contents;
use function imagecreatefromstring;
use function imagesx;
use function imagesy;
use function in_array;
use function is_object;
use function method_exists;

/**
 * High-level convenience wrapper for QR decoding.
 *
 * The reader normalizes file, blob, and resource inputs into a luminance
 * source, chooses the best available image backend, and retains the most
 * recent decode result or error for later inspection by callers.
 * @author Brian Faust <brian@cline.sh>
 */
final class QrReader
{
    /**
     * Input source type for file-backed decoding.
     */
    public const string SOURCE_TYPE_FILE = 'file';

    /**
     * Input source type for raw image blobs.
     */
    public const string SOURCE_TYPE_BLOB = 'blob';

    /**
     * Input source type for pre-opened image resources.
     */
    public const string SOURCE_TYPE_RESOURCE = 'resource';

    private \Cline\Qr\Decoder\Result|bool|null $result = null;

    private ?Exception $error = null;

    private readonly BinaryBitmap $bitmap;

    private readonly QRCodeReader $reader;

    /**
     * Prepare a decoder for the given image source.
     *
     * The constructor accepts a path, blob, or resource and converts it into
     * a binary bitmap using Imagick when available, otherwise GD.
     *
     * @param mixed  $imgSource             Image payload or handle.
     * @param string $sourceType            One of the `SOURCE_TYPE_*` constants.
     * @param bool   $useImagickIfAvailable Prefer Imagick when it is installed.
     *
     * @throws InvalidArgumentException When the source type or payload is invalid.
     */
    public function __construct($imgSource, $sourceType = self::SOURCE_TYPE_FILE, $useImagickIfAvailable = true)
    {
        if (!in_array($sourceType, [
            self::SOURCE_TYPE_FILE,
            self::SOURCE_TYPE_BLOB,
            self::SOURCE_TYPE_RESOURCE,
        ], true)) {
            throw InvalidArgumentException::withMessage('Invalid image source.');
        }
        $im = null;

        switch ($sourceType) {
            case self::SOURCE_TYPE_FILE:
                if ($useImagickIfAvailable && extension_loaded('imagick')) {
                    $im = new Imagick();
                    $im->readImage($imgSource);
                } else {
                    $image = file_get_contents($imgSource);
                    $im = imagecreatefromstring($image);
                }

                break;

            case self::SOURCE_TYPE_BLOB:
                if ($useImagickIfAvailable && extension_loaded('imagick')) {
                    $im = new Imagick();
                    $im->readImageBlob($imgSource);
                } else {
                    $im = imagecreatefromstring($imgSource);
                }

                break;

            case self::SOURCE_TYPE_RESOURCE:
                $im = $imgSource;

                if ($useImagickIfAvailable && extension_loaded('imagick')) {
                    $useImagickIfAvailable = true;
                } else {
                    $useImagickIfAvailable = false;
                }

                break;
        }

        if ($useImagickIfAvailable && extension_loaded('imagick')) {
            if (!$im instanceof Imagick) {
                throw InvalidArgumentException::withMessage('Invalid image source.');
            }
            $width = $im->getImageWidth();
            $height = $im->getImageHeight();
            $source = new IMagickLuminanceSource($im, $width, $height);
        } else {
            if (!$im instanceof GdImage && !is_object($im)) {
                throw InvalidArgumentException::withMessage('Invalid image source.');
            }
            $width = imagesx($im);
            $height = imagesy($im);
            $source = new GDLuminanceSource($im, $width, $height);
        }
        $histo = new HybridBinarizer($source);
        $this->bitmap = new BinaryBitmap($histo);
        $this->reader = new QRCodeReader();
    }

    /**
     * Decode the prepared bitmap and cache the outcome.
     *
     * Only expected decode failures are captured here. On success, the result
     * is stored for `getResult()` and `text()`; on failure, the exception is
     * preserved for `getError()`.
     *
     * @param mixed $hints Optional decode hints.
     */
    public function decode($hints = null): void
    {
        try {
            $this->result = $this->reader->decode($this->bitmap, $hints);
        } catch (NotFoundException|FormatException|ChecksumException $e) {
            $this->result = false;
            $this->error = $e;
        }
    }

    /**
     * Decode the bitmap and return the textual payload when available.
     *
     * The underlying result is still cached, so callers can inspect the raw
     * `Result` or the decode error after calling this method.
     *
     * @param  mixed            $hints Optional decode hints.
     * @return null|bool|string Decoded text, or `false` on decode failure.
     */
    public function text($hints = null)
    {
        $this->decode($hints);

        if ($this->result !== false && method_exists($this->result, 'toString')) {
            return $this->result->toString();
        }

        return $this->result;
    }

    /**
     * Return the last cached decode result.
     *
     * @return null|bool|Result The most recent result, `false` on decode failure, or `null` before decode.
     */
    public function getResult(): bool|Result|null
    {
        return $this->result;
    }

    /**
     * Return the last decode error, if one was captured.
     *
     * @return null|Exception The most recent decode exception.
     */
    public function getError(): ?Exception
    {
        return $this->error;
    }
}
