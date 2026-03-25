<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Writer\Result;

use Cline\Qr\Label\LabelInterface;
use Cline\Qr\Logo\LogoInterface;
use Cline\Qr\Matrix\MatrixInterface;
use Cline\Qr\QrCodeInterface;

use const JSON_THROW_ON_ERROR;

use function implode;
use function is_scalar;
use function json_encode;

/**
 * Debug-oriented result that exposes all render inputs as text.
 *
 * This result is intended for inspection rather than end-user delivery. It
 * records the QR configuration, optional logo, optional label, writer options,
 * and whether validation was requested.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class DebugResult extends AbstractResult
{
    private bool $validateResult = false;

    public function __construct(
        MatrixInterface $matrix,
        private readonly QrCodeInterface $qrCode,
        private readonly ?LogoInterface $logo = null,
        private readonly ?LabelInterface $label = null,
        /** @var array<string, mixed> $options */
        private readonly array $options = [],
    ) {
        parent::__construct($matrix);
    }

    /**
     * Toggle whether validation should be reported in the debug output.
     */
    public function setValidateResult(bool $validateResult): void
    {
        $this->validateResult = $validateResult;
    }

    /**
     * Serialize the captured debug metadata as human-readable lines.
     */
    public function getString(): string
    {
        $debugLines = [];

        $debugLines[] = 'Data: '.$this->qrCode->getData();
        $debugLines[] = 'Encoding: '.$this->qrCode->getEncoding();
        $debugLines[] = 'Error Correction Level: '.$this->qrCode->getErrorCorrectionLevel()::class;
        $debugLines[] = 'Size: '.$this->qrCode->getSize();
        $debugLines[] = 'Margin: '.$this->qrCode->getMargin();
        $debugLines[] = 'Round block size mode: '.$this->qrCode->getRoundBlockSizeMode()::class;
        $debugLines[] = 'Foreground color: ['.implode(', ', $this->qrCode->getForegroundColor()->toArray()).']';
        $debugLines[] = 'Background color: ['.implode(', ', $this->qrCode->getBackgroundColor()->toArray()).']';

        foreach ($this->options as $key => $value) {
            $debugLines[] = 'Writer option: '.$key.': '.$this->stringifyValue($value);
        }

        if (isset($this->logo)) {
            $debugLines[] = 'Logo path: '.$this->logo->getPath();
            $debugLines[] = 'Logo resize to width: '.$this->logo->getResizeToWidth();
            $debugLines[] = 'Logo resize to height: '.$this->logo->getResizeToHeight();
            $debugLines[] = 'Logo punchout background: '.($this->logo->getPunchoutBackground() ? 'true' : 'false');
        }

        if (isset($this->label)) {
            $debugLines[] = 'Label text: '.$this->label->getText();
            $debugLines[] = 'Label font path: '.$this->label->getFont()->getPath();
            $debugLines[] = 'Label font size: '.$this->label->getFont()->getSize();
            $debugLines[] = 'Label alignment: '.$this->label->getAlignment()::class;
            $debugLines[] = 'Label margin: ['.implode(', ', $this->label->getMargin()->toArray()).']';
            $debugLines[] = 'Label text color: ['.implode(', ', $this->label->getTextColor()->toArray()).']';
        }

        $debugLines[] = 'Validate result: '.($this->validateResult ? 'true' : 'false');

        return implode("\n", $debugLines);
    }

    /**
     * Return the mime type for debug text output.
     */
    public function getMimeType(): string
    {
        return 'text/plain';
    }

    /**
     * Convert an arbitrary writer option value into a printable string.
     */
    private function stringifyValue(mixed $value): string
    {
        if (is_scalar($value) || null === $value) {
            return (string) $value;
        }

        return json_encode($value, JSON_THROW_ON_ERROR);
    }
}
