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
use SimpleXMLElement;

use function is_string;
use function str_replace;
use function throw_unless;

/**
 * Result wrapper for SVG XML output.
 *
 * The result keeps the mutable `SimpleXMLElement` tree available for callers
 * that want to inspect or extend it before serialization.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class SvgResult extends AbstractResult
{
    public function __construct(
        MatrixInterface $matrix,
        private readonly SimpleXMLElement $xml,
        private readonly bool $excludeXmlDeclaration = false,
    ) {
        parent::__construct($matrix);
    }

    /**
     * Return the underlying SVG XML tree.
     */
    public function getXml(): SimpleXMLElement
    {
        return $this->xml;
    }

    /**
     * Serialize the SVG XML tree to a string, optionally omitting the XML
     * declaration.
     */
    public function getString(): string
    {
        $string = $this->xml->asXML();

        throw_unless(
            is_string($string),
            RuntimeException::withMessage('Could not save SVG XML to string'),
        );

        if ($this->excludeXmlDeclaration) {
            return str_replace("<?xml version=\"1.0\"?>\n", '', $string);
        }

        return $string;
    }

    /**
     * Return the SVG mime type.
     */
    public function getMimeType(): string
    {
        return 'image/svg+xml';
    }
}
