<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Builder;

use Cline\Qr\Color\Color;
use Cline\Qr\Color\ColorInterface;
use Cline\Qr\Encoding\Encoding;
use Cline\Qr\Encoding\EncodingInterface;
use Cline\Qr\ErrorCorrectionLevel;
use Cline\Qr\Exception\UnsupportedValidationWriterException;
use Cline\Qr\Label\Font\Font;
use Cline\Qr\Label\Font\FontInterface;
use Cline\Qr\Label\Label;
use Cline\Qr\Label\LabelAlignment;
use Cline\Qr\Label\Margin\Margin;
use Cline\Qr\Label\Margin\MarginInterface;
use Cline\Qr\Logo\Logo;
use Cline\Qr\QrCode;
use Cline\Qr\RoundBlockSizeMode;
use Cline\Qr\Writer\PngWriter;
use Cline\Qr\Writer\Result\ResultInterface;
use Cline\Qr\Writer\ValidatingWriterInterface;
use Cline\Qr\Writer\WriterInterface;

/**
 * Immutable QR code construction pipeline.
 *
 * Builder instances capture a complete rendering configuration up front and
 * then materialize a `QrCode` plus optional `Logo` and `Label` objects when
 * `build()` is called. The object is intentionally immutable so a configured
 * builder can be reused safely across requests, batch jobs, or registry lookups
 * without leaking state between invocations.
 *
 * Writer options are passed through verbatim to the selected writer. If result
 * validation is enabled, the configured writer must also implement
 * `ValidatingWriterInterface`; otherwise `build()` fails fast before any render
 * work begins.
 *
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class Builder implements BuilderInterface
{
    public function __construct(
        private WriterInterface $writer = new PngWriter(),
        /** @var array<string, mixed> */
        private array $writerOptions = [],
        private bool $validateResult = false,
        // QrCode options
        private string $data = '',
        private EncodingInterface $encoding = new Encoding('UTF-8'),
        private ErrorCorrectionLevel $errorCorrectionLevel = ErrorCorrectionLevel::Low,
        private int $size = 300,
        private int $margin = 10,
        private RoundBlockSizeMode $roundBlockSizeMode = RoundBlockSizeMode::Margin,
        private ColorInterface $foregroundColor = new Color(0, 0, 0),
        private ColorInterface $backgroundColor = new Color(255, 255, 255),
        // Label options
        private string $labelText = '',
        private FontInterface $labelFont = new Font(__DIR__.'/../../assets/open_sans.ttf', 16),
        private LabelAlignment $labelAlignment = LabelAlignment::Center,
        private MarginInterface $labelMargin = new Margin(0, 10, 10, 10),
        private ColorInterface $labelTextColor = new Color(0, 0, 0),
        // Logo options
        private string $logoPath = '',
        private ?int $logoResizeToWidth = null,
        private ?int $logoResizeToHeight = null,
        private bool $logoPunchoutBackground = false,
    ) {}

    /**
     * Render a QR code using the builder's configured defaults, overriding them
     * only for the values explicitly provided here.
     *
     * The method resolves the writer, QR payload, label, and logo in that order:
     * nullable arguments fall back to the immutable constructor state, while
     * truthy label and logo markers determine whether those optional components
     * are instantiated at all. This keeps "empty" builders lightweight while
     * still allowing call-time overrides for one-off renders.
     *
     * When validation is enabled, the configured writer must support result
     * validation. The writer is asked to validate the produced output against
     * the final QR payload before the result is returned to the caller.
     *
     * @param null|array<string, mixed> $writerOptions Writer-specific options
     *                                                 that are forwarded without interpretation.
     */
    public function build(
        ?WriterInterface $writer = null,
        ?array $writerOptions = null,
        bool $validateResult = null,
        // QrCode options
        ?string $data = null,
        ?EncodingInterface $encoding = null,
        ?ErrorCorrectionLevel $errorCorrectionLevel = null,
        ?int $size = null,
        ?int $margin = null,
        ?RoundBlockSizeMode $roundBlockSizeMode = null,
        ?ColorInterface $foregroundColor = null,
        ?ColorInterface $backgroundColor = null,
        // Label options
        ?string $labelText = null,
        ?FontInterface $labelFont = null,
        ?LabelAlignment $labelAlignment = null,
        ?MarginInterface $labelMargin = null,
        ?ColorInterface $labelTextColor = null,
        // Logo options
        ?string $logoPath = null,
        ?int $logoResizeToWidth = null,
        ?int $logoResizeToHeight = null,
        bool $logoPunchoutBackground = null,
    ): ResultInterface {
        if ($this->validateResult && !$this->writer instanceof ValidatingWriterInterface) {
            throw UnsupportedValidationWriterException::forWriter($this->writer::class);
        }

        $writer ??= $this->writer;
        $writerOptions ??= $this->writerOptions;
        $validateResult ??= $this->validateResult;

        $createLabel = $this->labelText || $labelText;
        $createLogo = $this->logoPath || $logoPath;

        $qrCode = new QrCode(
            data: $data ?? $this->data,
            encoding: $encoding ?? $this->encoding,
            errorCorrectionLevel: $errorCorrectionLevel ?? $this->errorCorrectionLevel,
            size: $size ?? $this->size,
            margin: $margin ?? $this->margin,
            roundBlockSizeMode: $roundBlockSizeMode ?? $this->roundBlockSizeMode,
            foregroundColor: $foregroundColor ?? $this->foregroundColor,
            backgroundColor: $backgroundColor ?? $this->backgroundColor,
        );

        $logo = $createLogo ? new Logo(
            path: $logoPath ?? $this->logoPath,
            resizeToWidth: $logoResizeToWidth ?? $this->logoResizeToWidth,
            resizeToHeight: $logoResizeToHeight ?? $this->logoResizeToHeight,
            punchoutBackground: $logoPunchoutBackground ?? $this->logoPunchoutBackground,
        ) : null;

        $label = $createLabel ? new Label(
            text: $labelText ?? $this->labelText,
            font: $labelFont ?? $this->labelFont,
            alignment: $labelAlignment ?? $this->labelAlignment,
            margin: $labelMargin ?? $this->labelMargin,
            textColor: $labelTextColor ?? $this->labelTextColor,
        ) : null;

        $result = $writer->write($qrCode, $logo, $label, $writerOptions);

        if ($validateResult && $writer instanceof ValidatingWriterInterface) {
            $writer->validateResult($result, $qrCode->getData());
        }

        return $result;
    }
}
