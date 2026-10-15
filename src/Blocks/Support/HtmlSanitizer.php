<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Minimal allow-list sanitizer for rich text: sanitizing HTML is the backend's
 * job and the frontend inserts the payload as it is.
 *
 * Active markup (script, iframe and friends), event handler attributes and
 * `javascript:` addresses are removed; everything else is left alone.
 */
final class HtmlSanitizer
{
    /** @var list<string> */
    private const FORBIDDEN_ELEMENTS = [
        'script', 'iframe', 'object', 'embed', 'applet', 'form', 'style', 'link', 'meta', 'base',
    ];

    /** @var list<string> */
    private const URL_ATTRIBUTES = ['href', 'src', 'srcset', 'action', 'formaction', 'xlink:href', 'data'];

    private const ROOT_ID = 'cms-blocks-sanitizer-root';

    public function sanitize(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);

        $loaded = $document->loadHTML(
            '<?xml encoding="UTF-8"?><div id="'.self::ROOT_ID.'">'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $document->getElementById(self::ROOT_ID);

        if ($loaded === false || ! $root instanceof DOMElement) {
            // Unparseable input never travels to the frontend as markup.
            return strip_tags($html);
        }

        $this->clean($root);

        $output = '';

        foreach (iterator_to_array($root->childNodes) as $child) {
            $output .= (string) $document->saveHTML($child);
        }

        return trim($output);
    }

    private function clean(DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if (! $child instanceof DOMElement) {
                continue;
            }

            if (in_array(strtolower($child->nodeName), self::FORBIDDEN_ELEMENTS, true)) {
                $child->parentNode?->removeChild($child);

                continue;
            }

            $this->cleanAttributes($child);
            $this->clean($child);
        }
    }

    private function cleanAttributes(DOMElement $element): void
    {
        foreach (iterator_to_array($element->attributes) as $attribute) {
            $name = strtolower($attribute->nodeName);

            if (str_starts_with($name, 'on')) {
                $element->removeAttribute($attribute->nodeName);

                continue;
            }

            if (in_array($name, self::URL_ATTRIBUTES, true) && $this->isDangerousUrl($attribute->value)) {
                $element->removeAttribute($attribute->nodeName);
            }
        }
    }

    private function isDangerousUrl(string $value): bool
    {
        $normalized = strtolower(preg_replace('/\s+/', '', $value) ?? $value);

        return str_starts_with($normalized, 'javascript:')
            || str_starts_with($normalized, 'vbscript:')
            || str_starts_with($normalized, 'data:text/html');
    }
}
