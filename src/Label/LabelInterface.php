<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Label;

use Cline\Qr\Color\ColorInterface;
use Cline\Qr\Label\Font\FontInterface;
use Cline\Qr\Label\Margin\MarginInterface;

/**
 * Contract for label configuration consumed by writers.
 *
 * The writer layer depends only on the data exposed here, which keeps label
 * measurement and rendering decoupled from the concrete `Label` value object.
 *
 * @author Brian Faust <brian@cline.sh>
 */
interface LabelInterface
{
    /**
     * Return the label text to render.
     */
    public function getText(): string;

    /**
     * Return the font configuration for measurement and drawing.
     */
    public function getFont(): FontInterface;

    /**
     * Return the label alignment rule.
     */
    public function getAlignment(): LabelAlignment;

    /**
     * Return the label margin values.
     */
    public function getMargin(): MarginInterface;

    /**
     * Return the label text color.
     */
    public function getTextColor(): ColorInterface;
}
