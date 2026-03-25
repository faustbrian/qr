<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Label;

use Cline\Qr\Color\Color;
use Cline\Qr\Color\ColorInterface;
use Cline\Qr\Label\Font\Font;
use Cline\Qr\Label\Font\FontInterface;
use Cline\Qr\Label\Margin\Margin;
use Cline\Qr\Label\Margin\MarginInterface;

/**
 * Immutable label definition rendered beneath or beside the QR matrix.
 *
 * A label combines its text content, font, alignment, margin, and text color
 * so writers can measure and paint it consistently regardless of backend.
 *
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class Label implements LabelInterface
{
    public function __construct(
        private string $text,
        private FontInterface $font = new Font(__DIR__.'/../../assets/open_sans.ttf', 16),
        private LabelAlignment $alignment = LabelAlignment::Center,
        private MarginInterface $margin = new Margin(0, 10, 10, 10),
        private ColorInterface $textColor = new Color(0, 0, 0),
    ) {}

    /**
     * Return the label text to render.
     */
    public function getText(): string
    {
        return $this->text;
    }

    /**
     * Return the font configuration used to measure and draw the label.
     */
    public function getFont(): FontInterface
    {
        return $this->font;
    }

    /**
     * Return the alignment rule applied relative to the QR matrix.
     */
    public function getAlignment(): LabelAlignment
    {
        return $this->alignment;
    }

    /**
     * Return the margin around the rendered label text.
     */
    public function getMargin(): MarginInterface
    {
        return $this->margin;
    }

    /**
     * Return the label text color.
     */
    public function getTextColor(): ColorInterface
    {
        return $this->textColor;
    }
}
