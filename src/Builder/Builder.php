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

use function array_key_exists;

/**
 * Immutable QR code construction pipeline.
 *
 * Builder instances capture a complete rendering configuration up front and
 * then materialize a `QrCode` plus optional `Logo` and `Label` objects when
 * `build()` is called. The object is intentionally immutable so a configured
 * builder can be reused safely across requests, batch jobs, or registry lookups
 * without leaking state between invocations.
 *
 * Writer options are passed through verbatim to the selected writer. Runtime
 * configuration changes are expressed through explicit immutable `with*`
 * methods instead of ad hoc `build()` overrides. If result validation is
 * enabled, the configured writer must also implement
 * `ValidatingWriterInterface`; otherwise `build()` fails fast before any
 * render work begins.
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

    public function withWriter(WriterInterface $writer): self
    {
        return $this->duplicate(['writer' => $writer]);
    }

    /**
     * @param array<string, mixed> $writerOptions
     */
    public function withWriterOptions(array $writerOptions): self
    {
        return $this->duplicate(['writerOptions' => $writerOptions]);
    }

    public function withValidateResult(bool $validateResult): self
    {
        return $this->duplicate(['validateResult' => $validateResult]);
    }

    public function withData(string $data): self
    {
        return $this->duplicate(['data' => $data]);
    }

    public function withEncoding(EncodingInterface $encoding): self
    {
        return $this->duplicate(['encoding' => $encoding]);
    }

    public function withErrorCorrectionLevel(ErrorCorrectionLevel $errorCorrectionLevel): self
    {
        return $this->duplicate(['errorCorrectionLevel' => $errorCorrectionLevel]);
    }

    public function withSize(int $size): self
    {
        return $this->duplicate(['size' => $size]);
    }

    public function withMargin(int $margin): self
    {
        return $this->duplicate(['margin' => $margin]);
    }

    public function withRoundBlockSizeMode(RoundBlockSizeMode $roundBlockSizeMode): self
    {
        return $this->duplicate(['roundBlockSizeMode' => $roundBlockSizeMode]);
    }

    public function withForegroundColor(ColorInterface $foregroundColor): self
    {
        return $this->duplicate(['foregroundColor' => $foregroundColor]);
    }

    public function withBackgroundColor(ColorInterface $backgroundColor): self
    {
        return $this->duplicate(['backgroundColor' => $backgroundColor]);
    }

    public function withLabelText(string $labelText): self
    {
        return $this->duplicate(['labelText' => $labelText]);
    }

    public function withLabelFont(FontInterface $labelFont): self
    {
        return $this->duplicate(['labelFont' => $labelFont]);
    }

    public function withLabelAlignment(LabelAlignment $labelAlignment): self
    {
        return $this->duplicate(['labelAlignment' => $labelAlignment]);
    }

    public function withLabelMargin(MarginInterface $labelMargin): self
    {
        return $this->duplicate(['labelMargin' => $labelMargin]);
    }

    public function withLabelTextColor(ColorInterface $labelTextColor): self
    {
        return $this->duplicate(['labelTextColor' => $labelTextColor]);
    }

    public function withLogoPath(string $logoPath): self
    {
        return $this->duplicate(['logoPath' => $logoPath]);
    }

    public function withLogoResizeToWidth(?int $logoResizeToWidth): self
    {
        return $this->duplicate(['logoResizeToWidth' => $logoResizeToWidth]);
    }

    public function withLogoResizeToHeight(?int $logoResizeToHeight): self
    {
        return $this->duplicate(['logoResizeToHeight' => $logoResizeToHeight]);
    }

    public function withLogoPunchoutBackground(bool $logoPunchoutBackground): self
    {
        return $this->duplicate(['logoPunchoutBackground' => $logoPunchoutBackground]);
    }

    /**
     * Render a QR code using the builder's configured defaults.
     *
     * When validation is enabled, the configured writer must support result
     * validation. The writer is asked to validate the produced output against
     * the final QR payload before the result is returned to the caller.
     */
    public function build(): ResultInterface
    {
        if ($this->validateResult && !$this->writer instanceof ValidatingWriterInterface) {
            throw UnsupportedValidationWriterException::forWriter($this->writer::class);
        }

        $createLabel = $this->labelText !== '';
        $createLogo = $this->logoPath !== '';

        $qrCode = new QrCode(
            data: $this->data,
            encoding: $this->encoding,
            errorCorrectionLevel: $this->errorCorrectionLevel,
            size: $this->size,
            margin: $this->margin,
            roundBlockSizeMode: $this->roundBlockSizeMode,
            foregroundColor: $this->foregroundColor,
            backgroundColor: $this->backgroundColor,
        );

        $logo = $createLogo ? new Logo(
            path: $this->logoPath,
            resizeToWidth: $this->logoResizeToWidth,
            resizeToHeight: $this->logoResizeToHeight,
            punchoutBackground: $this->logoPunchoutBackground,
        ) : null;

        $label = $createLabel ? new Label(
            text: $this->labelText,
            font: $this->labelFont,
            alignment: $this->labelAlignment,
            margin: $this->labelMargin,
            textColor: $this->labelTextColor,
        ) : null;

        $result = $this->writer->write($qrCode, $logo, $label, $this->writerOptions);

        if ($this->validateResult && $this->writer instanceof ValidatingWriterInterface) {
            $this->writer->validateResult($result, $qrCode->getData());
        }

        return $result;
    }

    /**
     * @param array{
     *     writer?: WriterInterface,
     *     writerOptions?: array<string, mixed>,
     *     validateResult?: bool,
     *     data?: string,
     *     encoding?: EncodingInterface,
     *     errorCorrectionLevel?: ErrorCorrectionLevel,
     *     size?: int,
     *     margin?: int,
     *     roundBlockSizeMode?: RoundBlockSizeMode,
     *     foregroundColor?: ColorInterface,
     *     backgroundColor?: ColorInterface,
     *     labelText?: string,
     *     labelFont?: FontInterface,
     *     labelAlignment?: LabelAlignment,
     *     labelMargin?: MarginInterface,
     *     labelTextColor?: ColorInterface,
     *     logoPath?: string,
     *     logoResizeToWidth?: ?int,
     *     logoResizeToHeight?: ?int,
     *     logoPunchoutBackground?: bool
     * } $overrides
     */
    private function duplicate(array $overrides): self
    {
        return new self(
            writer: $overrides['writer'] ?? $this->writer,
            writerOptions: $overrides['writerOptions'] ?? $this->writerOptions,
            validateResult: $overrides['validateResult'] ?? $this->validateResult,
            data: $overrides['data'] ?? $this->data,
            encoding: $overrides['encoding'] ?? $this->encoding,
            errorCorrectionLevel: $overrides['errorCorrectionLevel'] ?? $this->errorCorrectionLevel,
            size: $overrides['size'] ?? $this->size,
            margin: $overrides['margin'] ?? $this->margin,
            roundBlockSizeMode: $overrides['roundBlockSizeMode'] ?? $this->roundBlockSizeMode,
            foregroundColor: $overrides['foregroundColor'] ?? $this->foregroundColor,
            backgroundColor: $overrides['backgroundColor'] ?? $this->backgroundColor,
            labelText: $overrides['labelText'] ?? $this->labelText,
            labelFont: $overrides['labelFont'] ?? $this->labelFont,
            labelAlignment: $overrides['labelAlignment'] ?? $this->labelAlignment,
            labelMargin: $overrides['labelMargin'] ?? $this->labelMargin,
            labelTextColor: $overrides['labelTextColor'] ?? $this->labelTextColor,
            logoPath: $overrides['logoPath'] ?? $this->logoPath,
            logoResizeToWidth: array_key_exists('logoResizeToWidth', $overrides)
                ? $overrides['logoResizeToWidth']
                : $this->logoResizeToWidth,
            logoResizeToHeight: array_key_exists('logoResizeToHeight', $overrides)
                ? $overrides['logoResizeToHeight']
                : $this->logoResizeToHeight,
            logoPunchoutBackground: $overrides['logoPunchoutBackground'] ?? $this->logoPunchoutBackground,
        );
    }
}
