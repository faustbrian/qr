<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Builder;

use Cline\Qr\Color\ColorInterface;
use Cline\Qr\Encoding\EncodingInterface;
use Cline\Qr\ErrorCorrectionLevel;
use Cline\Qr\Label\Font\FontInterface;
use Cline\Qr\Label\LabelAlignment;
use Cline\Qr\Label\Margin\MarginInterface;
use Cline\Qr\RoundBlockSizeMode;
use Cline\Qr\Writer\Result\ResultInterface;
use Cline\Qr\Writer\WriterInterface;

/**
 * Contract for QR rendering pipelines.
 *
 * Implementations combine QR payload, styling, and writer selection into a
 * single render step. The interface exists so applications can substitute
 * alternate builders while keeping the caller-facing composition rules stable.
 *
 * @author Brian Faust <brian@cline.sh>
 */
interface BuilderInterface
{
    /**
     * Return a clone with a different writer.
     */
    public function withWriter(WriterInterface $writer): self;

    /**
     * Return a clone with different writer options.
     *
     * @param array<string, mixed> $writerOptions
     */
    public function withWriterOptions(array $writerOptions): self;

    public function withValidateResult(bool $validateResult): self;

    public function withData(string $data): self;

    public function withEncoding(EncodingInterface $encoding): self;

    public function withErrorCorrectionLevel(ErrorCorrectionLevel $errorCorrectionLevel): self;

    public function withSize(int $size): self;

    public function withMargin(int $margin): self;

    public function withRoundBlockSizeMode(RoundBlockSizeMode $roundBlockSizeMode): self;

    public function withForegroundColor(ColorInterface $foregroundColor): self;

    public function withBackgroundColor(ColorInterface $backgroundColor): self;

    public function withLabelText(string $labelText): self;

    public function withLabelFont(FontInterface $labelFont): self;

    public function withLabelAlignment(LabelAlignment $labelAlignment): self;

    public function withLabelMargin(MarginInterface $labelMargin): self;

    public function withLabelTextColor(ColorInterface $labelTextColor): self;

    public function withLogoPath(string $logoPath): self;

    public function withLogoResizeToWidth(?int $logoResizeToWidth): self;

    public function withLogoResizeToHeight(?int $logoResizeToHeight): self;

    public function withLogoPunchoutBackground(bool $logoPunchoutBackground): self;

    /**
     * Build a rendered QR code result from the builder's configured state.
     */
    public function build(): ResultInterface;
}
