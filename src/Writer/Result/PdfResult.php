<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Writer\Result;

use Cline\Qr\Exception\RuntimeException;
use Cline\Qr\Matrix\MatrixInterface;
use FPDF;

use function is_string;
use function throw_unless;

/**
 * Result wrapper for rendered `FPDF` documents.
 *
 * Callers can either access the underlying `FPDF` instance directly or ask the
 * result to serialize it into a PDF string.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class PdfResult extends AbstractResult
{
    public function __construct(
        MatrixInterface $matrix,
        private readonly FPDF $fpdf,
    ) {
        parent::__construct($matrix);
    }

    /**
     * Return the underlying `FPDF` document instance.
     */
    public function getPdf(): FPDF
    {
        return $this->fpdf;
    }

    /**
     * Serialize the `FPDF` document into a PDF string.
     */
    public function getString(): string
    {
        $output = $this->fpdf->Output('S');

        throw_unless(
            is_string($output),
            RuntimeException::withMessage('Unable to generate PDF output'),
        );

        return $output;
    }

    /**
     * Return the PDF mime type.
     */
    public function getMimeType(): string
    {
        return 'application/pdf';
    }
}
