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
     * Build a rendered QR code result.
     *
     * Callers may provide partial overrides; any omitted value should fall back
     * to the implementation's configured defaults. Writers receive their option
     * array unchanged, which allows each concrete writer to define its own
     * dialect without polluting the shared builder contract.
     *
     * @param null|array<string, mixed> $writerOptions Writer-specific options
     *                                                 forwarded to the selected writer.
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
    ): ResultInterface;
}
