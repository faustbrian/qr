<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Writer;

use Cline\Qr\Writer\Result\ResultInterface;

/**
 * Contract for writers that can verify their own rendered output.
 *
 * Implementations typically re-read the rendered image and compare the decoded
 * payload to the expected input string.
 *
 * @author Brian Faust <brian@cline.sh>
 */
interface ValidatingWriterInterface
{
    /**
     * Validate a rendered result against the expected payload.
     */
    public function validateResult(ResultInterface $result, string $expectedData): void;
}
