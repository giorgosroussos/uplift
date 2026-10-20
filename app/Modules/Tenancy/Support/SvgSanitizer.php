<?php

namespace App\Modules\Tenancy\Support;

use DOMAttr;
use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Sanitises an uploaded SVG logo (SEC-010): keeps only drawing elements,
 * drops scripts, event handlers, styles that load resources and every
 * reference that leaves the document. An SVG that is not well-formed, has a
 * DOCTYPE (entities) or whose root is not <svg> is rejected.
 */
final class SvgSanitizer
{
    /**
     * Elements kept (lower case); anything else is removed with its content.
     *
     * @var list<string>
     */
    private const array ELEMENTS = [
        'svg', 'g', 'defs', 'symbol', 'use', 'title', 'desc', 'metadata',
        'path', 'rect', 'circle', 'ellipse', 'line', 'polyline', 'polygon',
        'text', 'tspan', 'textpath',
        'lineargradient', 'radialgradient', 'stop', 'pattern', 'clippath', 'mask',
        'filter', 'feblend', 'fecolormatrix', 'fecomposite', 'feflood', 'fegaussianblur',
        'femerge', 'femergenode', 'feoffset',
    ];

    /**
     * Values that run code or load data, wherever they appear.
     */
    private const string DANGEROUS_VALUE = '/(javascript|vbscript|data)\s*:|expression\s*\(|@import/i';

    public function sanitize(string $svg): ?string
    {
        if (trim($svg) === '') {
            return null;
        }

        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);

        try {
            $loaded = $document->loadXML($svg, LIBXML_NONET | LIBXML_COMPACT);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        $root = $document->documentElement;

        if (! $loaded || $document->doctype !== null || $root === null || strtolower($root->localName) !== 'svg') {
            return null;
        }

        $this->clean($root);

        $output = $document->saveXML($root);

        return $output === false ? null : $output;
    }

    private function clean(DOMElement $element): void
    {
        foreach (iterator_to_array($element->attributes) as $attribute) {
            if (! $this->attributeIsSafe($attribute)) {
                $element->removeAttributeNode($attribute);
            }
        }

        /** @var list<DOMNode> $children */
        $children = iterator_to_array($element->childNodes);

        foreach ($children as $child) {
            if ($child instanceof DOMElement) {
                if (in_array(strtolower($child->localName), self::ELEMENTS, true)) {
                    $this->clean($child);
                } else {
                    $element->removeChild($child);
                }
            } elseif ($child->nodeType === XML_PI_NODE) {
                $element->removeChild($child);
            }
        }
    }

    private function attributeIsSafe(DOMAttr $attribute): bool
    {
        $name = strtolower($attribute->localName ?? $attribute->nodeName);
        $value = (string) $attribute->value;

        if (str_starts_with($name, 'on') || preg_match(self::DANGEROUS_VALUE, $value) === 1) {
            return false;
        }

        // Links may only point inside the document.
        if ($name === 'href') {
            return str_starts_with(trim($value), '#');
        }

        // url(...) references may only point inside the document.
        if (preg_match_all('/url\s*\(\s*[\'"]?\s*([^\'")\s]*)/i', $value, $matches) > 0) {
            foreach ($matches[1] as $target) {
                if (! str_starts_with($target, '#')) {
                    return false;
                }
            }
        }

        return true;
    }
}
