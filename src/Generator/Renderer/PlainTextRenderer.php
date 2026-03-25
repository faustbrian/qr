<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Generator\Renderer;

use Cline\Qr\Generator\Encoder\QrCode;
use Cline\Qr\Generator\Internal\Exception\InvalidArgumentException;

use function array_fill;
use function ceil;
use function str_repeat;

/**
 * Renderer that emits a Unicode block-character representation of the matrix.
 *
 * Two matrix rows are packed into one line of text using half-block glyphs,
 * which keeps terminal output compact while preserving enough contrast for
 * debugging and CLI display.
 * @author Brian Faust <brian@cline.sh>
 */
final class PlainTextRenderer implements RendererInterface
{
    /**
     * UTF-8 full block (U+2588)
     */
    private const string FULL_BLOCK = "\xe2\x96\x88";

    /**
     * UTF-8 upper half block (U+2580)
     */
    private const string UPPER_HALF_BLOCK = "\xe2\x96\x80";

    /**
     * UTF-8 lower half block (U+2584)
     */
    private const string LOWER_HALF_BLOCK = "\xe2\x96\x84";

    /**
     * UTF-8 no-break space (U+00A0)
     */
    private const string EMPTY_BLOCK = "\xc2\xa0";

    public function __construct(
        private readonly int $margin = 2,
    ) {}

    /**
     * Render the QR matrix as plain text.
     *
     * If the matrix has an odd height, a blank row is appended so each emitted
     * line can combine exactly two source rows.
     *
     * @throws InvalidArgumentException if matrix width doesn't match height
     */
    public function render(QrCode $qrCode): string
    {
        $matrix = $qrCode->getMatrix();
        $matrixSize = $matrix->getWidth();

        if ($matrixSize !== $matrix->getHeight()) {
            throw InvalidArgumentException::withMessage('Matrix must have the same width and height');
        }

        $rows = $matrix->getArray()->toArray();

        if (0 !== $matrixSize % 2) {
            $rows[] = array_fill(0, $matrixSize, 0);
        }

        $horizontalMargin = str_repeat(self::EMPTY_BLOCK, $this->margin);
        $result = str_repeat("\n", (int) ceil($this->margin / 2));

        for ($i = 0; $i < $matrixSize; $i += 2) {
            $result .= $horizontalMargin;

            $upperRow = $rows[$i];
            $lowerRow = $rows[$i + 1];

            for ($j = 0; $j < $matrixSize; ++$j) {
                $upperBit = $upperRow[$j];
                $lowerBit = $lowerRow[$j];

                if ($upperBit) {
                    $result .= $lowerBit ? self::FULL_BLOCK : self::UPPER_HALF_BLOCK;
                } else {
                    $result .= $lowerBit ? self::LOWER_HALF_BLOCK : self::EMPTY_BLOCK;
                }
            }

            $result .= $horizontalMargin."\n";
        }

        $result .= str_repeat("\n", (int) ceil($this->margin / 2));

        return $result;
    }
}
