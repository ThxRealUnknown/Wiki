<?php

namespace App\Export;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

/**
 * Converts the sanitised rich text stored in entries into other formats.
 *
 * The input is never arbitrary HTML: App\Sanitizer has already reduced it to a
 * small, known whitelist, so this walker only has to handle those tags.
 */
final class RichText
{
    /**
     * Turns stored rich text into Markdown.
     *
     * @param int $headingOffset pushes headings below whatever heading they sit under
     */
    public static function toMarkdown(string $html, int $headingOffset = 0): string
    {
        $root = self::parse($html);
        if ($root === null) {
            return '';
        }

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= self::block($child, 0, $headingOffset);
        }

        return trim(preg_replace("/\n{3,}/", "\n\n", $out) ?? $out);
    }

    /** Block-level nodes, each ending in a blank line. */
    private static function block(DOMNode $node, int $depth = 0, int $headingOffset = 0): string
    {
        if ($node instanceof DOMText) {
            $text = self::inlineText($node->textContent);

            return trim($text) === '' ? '' : $text . "\n\n";
        }

        if (!$node instanceof DOMElement) {
            return '';
        }

        $tag = strtolower($node->nodeName);

        switch ($tag) {
            case 'h1':
            case 'h2':
            case 'h3':
            case 'h4':
                $level = min(6, (int) substr($tag, 1) + $headingOffset);

                return str_repeat('#', $level) . ' ' . trim(self::inline($node)) . "\n\n";

            case 'p':
            case 'div':
                $text = trim(self::inline($node));

                return $text === '' ? '' : $text . "\n\n";

            case 'blockquote':
                $inner = '';
                foreach ($node->childNodes as $child) {
                    $inner .= self::block($child, $depth, $headingOffset);
                }
                $lines = explode("\n", trim($inner));

                return '> ' . implode("\n> ", $lines) . "\n\n";

            case 'ul':
            case 'ol':
                $out = '';
                $index = 1;
                foreach ($node->childNodes as $child) {
                    if (!$child instanceof DOMElement || strtolower($child->nodeName) !== 'li') {
                        continue;
                    }

                    $marker = $tag === 'ol' ? ($index++ . '.') : '-';
                    $indent = str_repeat('  ', $depth);

                    // A nested list lives inside the <li>, so split the item's
                    // own text from any list that follows it.
                    $own = '';
                    $nested = '';
                    foreach ($child->childNodes as $part) {
                        if ($part instanceof DOMElement
                            && in_array(strtolower($part->nodeName), ['ul', 'ol'], true)) {
                            $nested .= self::block($part, $depth + 1, $headingOffset);
                        } else {
                            $own .= self::inlineNode($part);
                        }
                    }

                    $out .= $indent . $marker . ' ' . trim($own) . "\n";
                    if (trim($nested) !== '') {
                        $out .= rtrim($nested) . "\n";
                    }
                }

                return $out . "\n";

            case 'pre':
                return "```\n" . trim($node->textContent) . "\n```\n\n";

            case 'hr':
                return "---\n\n";

            case 'br':
                return "\n";

            default:
                // An inline tag sitting at block level: treat it as a paragraph.
                $text = trim(self::inlineNode($node));

                return $text === '' ? '' : $text . "\n\n";
        }
    }

    /** The inline content of an element. */
    private static function inline(DOMNode $node): string
    {
        $out = '';
        foreach ($node->childNodes as $child) {
            $out .= self::inlineNode($child);
        }

        return $out;
    }

    private static function inlineNode(DOMNode $node): string
    {
        if ($node instanceof DOMText) {
            return self::inlineText($node->textContent);
        }

        if (!$node instanceof DOMElement) {
            return '';
        }

        $tag = strtolower($node->nodeName);
        $inner = self::inline($node);

        return match ($tag) {
            'strong', 'b'    => trim($inner) === '' ? '' : '**' . $inner . '**',
            'em', 'i'        => trim($inner) === '' ? '' : '*' . $inner . '*',
            's', 'strike'    => trim($inner) === '' ? '' : '~~' . $inner . '~~',
            'code'           => '`' . $inner . '`',
            'br'             => "  \n",
            'a'              => self::link($node, $inner),
            // Markdown has no underline; the text still has to survive.
            default          => $inner,
        };
    }

    private static function link(DOMElement $node, string $inner): string
    {
        $href = trim($node->getAttribute('href'));
        if ($href === '' || trim($inner) === '') {
            return $inner;
        }

        return '[' . $inner . '](' . $href . ')';
    }

    /** Escapes the handful of characters Markdown would otherwise read as syntax. */
    private static function inlineText(string $text): string
    {
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return preg_replace('/([\\\\`*_\[\]<>])/u', '\\\\$1', $text) ?? $text;
    }

    private static function parse(string $html): ?DOMElement
    {
        $html = trim($html);
        if ($html === '') {
            return null;
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML(
            '<?xml encoding="UTF-8"><div id="richtext-root">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $document->getElementById('richtext-root');

        return $root instanceof DOMElement ? $root : null;
    }
}
