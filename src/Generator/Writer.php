<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Generator;

use Cline\Qr\Generator\Common\ErrorCorrectionLevel;
use Cline\Qr\Generator\Common\Version;
use Cline\Qr\Generator\Encoder\Encoder;
use Cline\Qr\Generator\Internal\Exception\InvalidArgumentException;
use Cline\Qr\Generator\Renderer\RendererInterface;

use function file_put_contents;
use function mb_strlen;

/**
 * Facade that pairs the encoder with a concrete renderer.
 *
 * This is the low-level generator entry point for callers who work directly
 * with the internal renderer stack. It encodes content into a QR matrix and
 * immediately renders the result through the configured backend.
 * @author Brian Faust <brian@cline.sh>
 */
final class Writer
{
    /**
     * Create a writer that renders every generated symbol through the supplied
     * renderer.
     */
    public function __construct(
        private readonly RendererInterface $renderer,
    ) {}

    /**
     * Encode the content and return the rendered output string.
     *
     * Content should usually be UTF-8 when non-ASCII characters are present so
     * the encoder can apply the requested byte encoding consistently.
     *
     * @throws InvalidArgumentException if the content is empty
     */
    public function writeString(
        string $content,
        string $encoding = Encoder::DEFAULT_BYTE_MODE_ENCODING,
        ?ErrorCorrectionLevel $ecLevel = null,
        ?Version $forcedVersion = null,
    ): string {
        if ($content === '') {
            throw InvalidArgumentException::withMessage('Found empty contents');
        }

        if (null === $ecLevel) {
            $ecLevel = ErrorCorrectionLevel::L;
        }

        return $this->renderer->render(Encoder::encode($content, $ecLevel, $encoding, $forcedVersion));
    }

    /**
     * Encode the content and write the rendered output to a file.
     *
     * @see Writer::writeString()
     */
    public function writeFile(
        string $content,
        string $filename,
        string $encoding = Encoder::DEFAULT_BYTE_MODE_ENCODING,
        ?ErrorCorrectionLevel $ecLevel = null,
        ?Version $forcedVersion = null,
    ): void {
        file_put_contents($filename, $this->writeString($content, $encoding, $ecLevel, $forcedVersion));
    }
}
