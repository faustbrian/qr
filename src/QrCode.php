<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr;

use Cline\Qr\Color\Color;
use Cline\Qr\Color\ColorInterface;
use Cline\Qr\Encoding\Encoding;
use Cline\Qr\Encoding\EncodingInterface;

/**
 * Public immutable QR configuration value object.
 *
 * This type captures the payload plus the high-level rendering options that the
 * package exposes to callers: encoding, error correction, output size, margin,
 * block rounding strategy, and foreground/background colors.
 *
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class QrCode implements QrCodeInterface
{
    public function __construct(
        private string $data,
        private EncodingInterface $encoding = new Encoding('UTF-8'),
        private ErrorCorrectionLevel $errorCorrectionLevel = ErrorCorrectionLevel::Low,
        private int $size = 300,
        private int $margin = 10,
        private RoundBlockSizeMode $roundBlockSizeMode = RoundBlockSizeMode::Margin,
        private ColorInterface $foregroundColor = new Color(0, 0, 0),
        private ColorInterface $backgroundColor = new Color(255, 255, 255),
    ) {}

    /**
     * Return the payload string that will be encoded into the QR symbol.
     */
    public function getData(): string
    {
        return $this->data;
    }

    public function withData(string $data): self
    {
        return new self(
            data: $data,
            encoding: $this->encoding,
            errorCorrectionLevel: $this->errorCorrectionLevel,
            size: $this->size,
            margin: $this->margin,
            roundBlockSizeMode: $this->roundBlockSizeMode,
            foregroundColor: $this->foregroundColor,
            backgroundColor: $this->backgroundColor,
        );
    }

    /**
     * Return the text encoding used when serializing the payload.
     */
    public function getEncoding(): EncodingInterface
    {
        return $this->encoding;
    }

    public function withEncoding(EncodingInterface $encoding): self
    {
        return new self(
            data: $this->data,
            encoding: $encoding,
            errorCorrectionLevel: $this->errorCorrectionLevel,
            size: $this->size,
            margin: $this->margin,
            roundBlockSizeMode: $this->roundBlockSizeMode,
            foregroundColor: $this->foregroundColor,
            backgroundColor: $this->backgroundColor,
        );
    }

    /**
     * Return the requested error-correction level.
     */
    public function getErrorCorrectionLevel(): ErrorCorrectionLevel
    {
        return $this->errorCorrectionLevel;
    }

    public function withErrorCorrectionLevel(ErrorCorrectionLevel $errorCorrectionLevel): self
    {
        return new self(
            data: $this->data,
            encoding: $this->encoding,
            errorCorrectionLevel: $errorCorrectionLevel,
            size: $this->size,
            margin: $this->margin,
            roundBlockSizeMode: $this->roundBlockSizeMode,
            foregroundColor: $this->foregroundColor,
            backgroundColor: $this->backgroundColor,
        );
    }

    /**
     * Return the target output size.
     */
    public function getSize(): int
    {
        return $this->size;
    }

    public function withSize(int $size): self
    {
        return new self(
            data: $this->data,
            encoding: $this->encoding,
            errorCorrectionLevel: $this->errorCorrectionLevel,
            size: $size,
            margin: $this->margin,
            roundBlockSizeMode: $this->roundBlockSizeMode,
            foregroundColor: $this->foregroundColor,
            backgroundColor: $this->backgroundColor,
        );
    }

    /**
     * Return the requested quiet-zone margin.
     */
    public function getMargin(): int
    {
        return $this->margin;
    }

    public function withMargin(int $margin): self
    {
        return new self(
            data: $this->data,
            encoding: $this->encoding,
            errorCorrectionLevel: $this->errorCorrectionLevel,
            size: $this->size,
            margin: $margin,
            roundBlockSizeMode: $this->roundBlockSizeMode,
            foregroundColor: $this->foregroundColor,
            backgroundColor: $this->backgroundColor,
        );
    }

    /**
     * Return the block-size rounding strategy for matrix adaptation.
     */
    public function getRoundBlockSizeMode(): RoundBlockSizeMode
    {
        return $this->roundBlockSizeMode;
    }

    public function withRoundBlockSizeMode(RoundBlockSizeMode $roundBlockSizeMode): self
    {
        return new self(
            data: $this->data,
            encoding: $this->encoding,
            errorCorrectionLevel: $this->errorCorrectionLevel,
            size: $this->size,
            margin: $this->margin,
            roundBlockSizeMode: $roundBlockSizeMode,
            foregroundColor: $this->foregroundColor,
            backgroundColor: $this->backgroundColor,
        );
    }

    /**
     * Return the foreground color used for dark modules.
     */
    public function getForegroundColor(): ColorInterface
    {
        return $this->foregroundColor;
    }

    public function withForegroundColor(ColorInterface $foregroundColor): self
    {
        return new self(
            data: $this->data,
            encoding: $this->encoding,
            errorCorrectionLevel: $this->errorCorrectionLevel,
            size: $this->size,
            margin: $this->margin,
            roundBlockSizeMode: $this->roundBlockSizeMode,
            foregroundColor: $foregroundColor,
            backgroundColor: $this->backgroundColor,
        );
    }

    /**
     * Return the background color used behind the symbol.
     */
    public function getBackgroundColor(): ColorInterface
    {
        return $this->backgroundColor;
    }

    public function withBackgroundColor(ColorInterface $backgroundColor): self
    {
        return new self(
            data: $this->data,
            encoding: $this->encoding,
            errorCorrectionLevel: $this->errorCorrectionLevel,
            size: $this->size,
            margin: $this->margin,
            roundBlockSizeMode: $this->roundBlockSizeMode,
            foregroundColor: $this->foregroundColor,
            backgroundColor: $backgroundColor,
        );
    }
}
