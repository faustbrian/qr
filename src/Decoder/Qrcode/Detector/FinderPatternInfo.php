<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Decoder\Qrcode\Detector;

/**
 * Carries the three finder patterns selected by the detector.
 *
 * The QR detector resolves finder patterns before any perspective correction
 * or bit extraction can happen. This value object preserves the detector's
 * ordering so later stages can derive the QR code orientation and module size
 * without recomputing the search.
 *
 * @author Sean Owen
 */
final class FinderPatternInfo
{
    private $bottomLeft;

    private $topLeft;

    private $topRight;

    /**
     * @param array<int, mixed> $patternCenters Ordered finder patterns from the
     *                                          detector.
     */
    public function __construct($patternCenters)
    {
        $this->bottomLeft = $patternCenters[0];
        $this->topLeft = $patternCenters[1];
        $this->topRight = $patternCenters[2];
    }

    /**
     * @return mixed The lower-left finder pattern selected by the detector.
     */
    public function getBottomLeft()
    {
        return $this->bottomLeft;
    }

    /**
     * @return mixed The upper-left finder pattern selected by the detector.
     */
    public function getTopLeft()
    {
        return $this->topLeft;
    }

    /**
     * @return mixed The upper-right finder pattern selected by the detector.
     */
    public function getTopRight()
    {
        return $this->topRight;
    }
}
