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
use Cline\Qr\Label\LabelInterface;

use function function_exists;
use function imagettfbbox;
use function is_array;
use function str_contains;
use function throw_if;
use function throw_unless;

/**
 * Derived image metrics for rendering a text label.
 *
 * The package computes these dimensions ahead of drawing so writer backends can
 * reserve enough space for the label and align it relative to the QR matrix.
 *
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class LabelImageData
{
    private function __construct(
        private int $width,
        private int $height,
    ) {}

    /**
     * Measure a label using the configured TrueType font.
     *
     * Labels are restricted to a single line, and FreeType support must be
     * available because the measurement is delegated to `imagettfbbox()`.
     */
    public static function createForLabel(LabelInterface $label): self
    {
        throw_if(
            str_contains($label->getText(), "\n"),
            InvalidArgumentException::withMessage('Label does not support line breaks'),
        );

        throw_unless(
            function_exists('imagettfbbox'),
            RuntimeException::withMessage(
                'Function "imagettfbbox" does not exist: check your FreeType installation',
            ),
        );

        $labelBox = imagettfbbox($label->getFont()->getSize(), 0, $label->getFont()->getPath(), $label->getText());

        throw_unless(
            is_array($labelBox),
            RuntimeException::withMessage(
                'Unable to generate label image box: check your FreeType installation',
            ),
        );

        /** @var array{0: int, 1: int, 2: int, 3: int, 4: int, 5: int, 6: int, 7: int} $labelBox */
        $upperRightX = (int) $labelBox[2];
        $lowerLeftX = (int) $labelBox[0];
        $lowerLeftY = (int) $labelBox[7];

        return new self(
            $upperRightX - $lowerLeftX,
            $lowerLeftX - $lowerLeftY,
        );
    }

    /**
     * Return the measured label width.
     */
    public function getWidth(): int
    {
        return $this->width;
    }

    /**
     * Return the measured label height.
     */
    public function getHeight(): int
    {
        return $this->height;
    }
}
