<?php

namespace App;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Whitelist sanitiser for the rich-text fields. Anything the editor can produce
 * survives; everything else — scripts, event handlers, styles, iframes,
 * javascript: URLs — is removed. Run on save, never on display, so the stored
 * value is always already safe.
 */
final class Sanitizer
{
    private const ALLOWED = [
        'p'          => ['class'],
        'br'         => [],
        'strong'     => [],
        'b'          => [],
        'em'         => [],
        'i'          => [],
        'u'          => [],
        's'          => [],
        'strike'     => [],
        'h1'         => ['class'],
        'h2'         => ['class'],
        'h3'         => ['class'],
        'h4'         => ['class'],
        'ul'         => ['class'],
        'ol'         => ['class'],
        'li'         => ['class'],
        'blockquote' => ['class'],
        'pre'        => ['class'],
        'code'       => [],
        'hr'         => [],
        'span'       => [],
        'div'        => ['class'],
        'a'          => ['href', 'title', 'data-entry'],
    ];

    /**
     * The only class names a rich-text field may carry. Alignment/indentation
     * use classes rather than inline styles, so they can be whitelisted exactly
     * instead of needing a CSS parser.
     */
    private const ALLOWED_CLASSES = [
        'align-left', 'align-center', 'align-right', 'align-justify',
        'indent-1', 'indent-2', 'indent-3', 'indent-4',
        'first-line-indent',
    ];

    /** Tags rewritten to their modern equivalent. */
    private const REPLACE = [
        'strike' => 's',
        'font'   => 'span',
    ];

    public static function clean(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML(
            '<?xml encoding="UTF-8"><div id="sanitizer-root">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $document->getElementById('sanitizer-root');
        if (!$root instanceof DOMElement) {
            return '';
        }

        self::walk($root);

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $document->saveHTML($child);
        }

        // An editor left empty still posts a stray <br> or empty paragraph.
        if (trim(strip_tags($out)) === '' && !str_contains($out, '<img')) {
            return '';
        }

        return trim($out);
    }

    private static function walk(DOMNode $node): void
    {
        // Snapshot: the list is live and we remove nodes as we go.
        $children = iterator_to_array($node->childNodes);

        foreach ($children as $child) {
            if ($child instanceof DOMElement) {
                $tag = strtolower($child->nodeName);

                if (isset(self::REPLACE[$tag])) {
                    $child = self::rename($child, self::REPLACE[$tag]);
                    $tag = self::REPLACE[$tag];
                }

                if (!isset(self::ALLOWED[$tag])) {
                    // Keep the text, drop the wrapper — except for tags whose
                    // content is not prose.
                    if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed'], true)) {
                        $node->removeChild($child);
                    } else {
                        self::unwrap($child);
                    }
                    continue;
                }

                self::stripAttributes($child, self::ALLOWED[$tag]);
                self::walk($child);
                continue;
            }

            if ($child->nodeType === XML_COMMENT_NODE || $child->nodeType === XML_PI_NODE) {
                $node->removeChild($child);
            }
        }
    }

    private static function rename(DOMElement $element, string $newTag): DOMElement
    {
        $replacement = $element->ownerDocument->createElement($newTag);
        while ($element->firstChild) {
            $replacement->appendChild($element->firstChild);
        }
        $element->parentNode->replaceChild($replacement, $element);

        return $replacement;
    }

    private static function unwrap(DOMElement $element): void
    {
        $parent = $element->parentNode;
        while ($element->firstChild) {
            $parent->insertBefore($element->firstChild, $element);
        }
        $parent->removeChild($element);
    }

    private static function stripAttributes(DOMElement $element, array $allowed): void
    {
        foreach (iterator_to_array($element->attributes) as $attribute) {
            $name = strtolower($attribute->nodeName);
            if (!in_array($name, $allowed, true)) {
                $element->removeAttribute($attribute->nodeName);
                continue;
            }

            if ($name === 'href' && !self::isSafeUrl($attribute->nodeValue)) {
                $element->removeAttribute('href');
                continue;
            }

            // data-entry stores a guid (not an address) so links survive renames — see App\EntryLinks.
            if ($name === 'data-entry' && !Guid::isValid($attribute->nodeValue)) {
                $element->removeAttribute('data-entry');
                continue;
            }

            if ($name === 'class') {
                $kept = array_values(array_intersect(
                    preg_split('/\s+/', trim((string) $attribute->nodeValue)) ?: [],
                    self::ALLOWED_CLASSES
                ));

                if ($kept === []) {
                    $element->removeAttribute('class');
                } else {
                    $element->setAttribute('class', implode(' ', $kept));
                }
            }
        }

        if (strtolower($element->nodeName) === 'a' && $element->hasAttribute('href')) {
            $href = $element->getAttribute('href');
            // Outbound links open in a new tab; internal ones stay put.
            if (preg_match('~^https?://~i', $href)) {
                $element->setAttribute('target', '_blank');
                $element->setAttribute('rel', 'noopener noreferrer');
            }
        }
    }

    private static function isSafeUrl(?string $url): bool
    {
        $url = trim((string) $url);
        if ($url === '') {
            return false;
        }

        // Reject anything that smells like a scheme we did not allow, including
        // obfuscated forms such as "java\tscript:".
        $collapsed = strtolower(preg_replace('/[\s\x00-\x1F]/', '', $url) ?? '');

        foreach (['javascript:', 'data:', 'vbscript:', 'file:'] as $bad) {
            if (str_starts_with($collapsed, $bad)) {
                return false;
            }
        }

        return true;
    }

    /**
     * A short plain-text preview of a rich-text or plain value, for entry lists.
     */
    public static function excerpt(string $value, int $length = 140): string
    {
        $text = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');

        if (mb_strlen($text) <= $length) {
            return $text;
        }

        return rtrim(mb_substr($text, 0, $length - 1), " \t\n\r\0\x0B.,;:") . '…';
    }
}
