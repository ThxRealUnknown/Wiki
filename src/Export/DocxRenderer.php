<?php

namespace App\Export;

use DOMDocument;
use DOMNode;
use DOMText;

/**
 * Word (.docx) — an Office Open XML package built by hand. Headings carry
 * real Word heading styles, so the navigation pane and a generated table of
 * contents work. Lists are styled paragraphs with their marker in the text
 * rather than real Word numbering.
 */
final class DocxRenderer implements Renderer
{
    private int $depth = 1;

    /** Paragraph XML, accumulated as the document is walked. */
    private string $body = '';

    public function extension(): string
    {
        return 'docx';
    }

    public function contentType(): string
    {
        return 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
    }

    public function render(Document $document): string
    {
        $this->body = '';
        $this->depth = 1;

        $this->para($document->title(), 'Title');

        if ($document->subtitle() !== '') {
            $this->para($document->subtitle(), 'Subtitle');
        }

        $meta = [];
        foreach ($document->metaLines() as $label => $value) {
            $meta[] = $label . ': ' . $value;
        }
        if ($meta !== []) {
            $this->para(implode('  ·  ', $meta), 'Meta');
        }

        foreach ($document->nodes() as $node) {
            $this->node($node);
        }

        $zip = new Zip();
        $zip->add('[Content_Types].xml', self::contentTypes());
        $zip->add('_rels/.rels', self::rels());
        $zip->add('docProps/core.xml', self::core($document->title()));
        $zip->add('word/_rels/document.xml.rels', self::documentRels());
        $zip->add('word/styles.xml', self::styles());
        $zip->add('word/document.xml', $this->document());

        return $zip->toString();
    }

    // ------------------------------------------------------------ the walk

    private function node(array $node): void
    {
        switch ($node[0]) {
            case 'heading':
                $level = max(1, min(4, $node[1]));
                $this->depth = $level;
                $this->para($node[2], 'Heading' . $level);

                return;

            case 'text':
                $this->para($node[1], 'Normal');

                return;

            case 'rich':
                $this->rich((string) $node[1]);

                return;

            case 'meta':
                $parts = [];
                foreach ($node[1] as $label => $value) {
                    $parts[] = $label . ': ' . $value;
                }
                if ($parts !== []) {
                    $this->para(implode('  ·  ', $parts), 'Meta');
                }

                return;

            case 'field':
                $this->field($node[1], $node[2], $node[3]);

                return;

            case 'links':
                $this->para($node[1], 'FieldLabel');
                foreach ($node[2] as [$title, $context]) {
                    $this->para('•  ' . $title . ($context !== '' ? ' — ' . $context : ''), 'ListItem');
                }

                return;

            case 'toc':
                $this->para('Contents', 'Heading1');
                foreach ($node[1] as [$text, $level]) {
                    $this->para(str_repeat('    ', max(0, $level - 1)) . $text, 'ListItem');
                }

                return;

            case 'break':
                $this->body .= '<w:p><w:r><w:br w:type="page"/></w:r></w:p>';

                return;
        }
    }

    private function field(string $label, string $kind, mixed $value): void
    {
        $this->para($label, 'FieldLabel');

        switch ($kind) {
            case Document::KIND_RICH:
                $this->rich((string) $value);

                return;

            case Document::KIND_LIST:
                $items = array_map(static fn ($i) => (string) $i, (array) $value);
                $this->para(implode(', ', $items), 'Normal');

                return;

            case Document::KIND_LINKS:
                foreach ((array) $value as [$title, $context]) {
                    $this->para('•  ' . $title . ($context !== '' ? ' — ' . $context : ''), 'ListItem');
                }

                return;

            case Document::KIND_IMAGE:
                // The picture itself is not embedded, just its path.
                $this->para((string) $value, 'Meta');

                return;

            case Document::KIND_EMPTY:
                $this->para('Not set', 'Meta');

                return;

            default:
                $this->para((string) $value, 'Normal');
        }
    }

    // --------------------------------------------------- rich text as runs

    /** Turns stored HTML into Word paragraphs, keeping bold, italic and lists. */
    private function rich(string $html): void
    {
        $html = trim($html);
        if ($html === '') {
            return;
        }

        $dom = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML(
            '<?xml encoding="utf-8"?><div>' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $dom->getElementsByTagName('div')->item(0);
        if ($root === null) {
            return;
        }

        $this->block($root, 'Normal', []);
    }

    /**
     * Walks a block element, emitting one Word paragraph per block-level tag.
     *
     * @param array{b?: bool, i?: bool, u?: bool} $format inherited emphasis
     */
    private function block(DOMNode $node, string $style, array $format, string $prefix = ''): void
    {
        $inline = '';
        $counter = 0;

        foreach ($node->childNodes as $child) {
            $name = strtolower($child->nodeName);

            if ($child instanceof DOMText) {
                $text = preg_replace('/\s+/u', ' ', $child->textContent) ?? '';
                if (trim($text) !== '') {
                    $inline .= self::run($text, $format);
                }
                continue;
            }

            switch ($name) {
                case 'p':
                case 'div':
                    $this->flush($inline, $style, $prefix);
                    $this->block($child, $style, $format);
                    break;

                case 'h1':
                case 'h2':
                case 'h3':
                case 'h4':
                    $this->flush($inline, $style, $prefix);
                    $level = min(4, $this->depth + (int) substr($name, 1));
                    $this->block($child, 'Heading' . $level, $format);
                    break;

                case 'blockquote':
                    $this->flush($inline, $style, $prefix);
                    $this->block($child, 'Quote', $format);
                    break;

                case 'ul':
                case 'ol':
                    $this->flush($inline, $style, $prefix);
                    foreach ($child->childNodes as $item) {
                        if (strtolower($item->nodeName) !== 'li') {
                            continue;
                        }
                        $counter++;
                        $marker = $name === 'ol' ? $counter . '.  ' : '•  ';
                        $this->block($item, 'ListItem', $format, $marker);
                    }
                    break;

                case 'br':
                    // Chapters separate paragraphs with <br> rather than <p>, so a break ends the paragraph.
                    $this->flush($inline, $style, $prefix);
                    break;

                case 'hr':
                    $this->flush($inline, $style, $prefix);
                    $this->para('* * *', 'Meta');
                    break;

                case 'strong':
                case 'b':
                    $inline .= $this->inline($child, $format + ['b' => true]);
                    break;

                case 'em':
                case 'i':
                    $inline .= $this->inline($child, $format + ['i' => true]);
                    break;

                case 'u':
                    $inline .= $this->inline($child, $format + ['u' => true]);
                    break;

                case 's':
                case 'strike':
                    $inline .= $this->inline($child, $format + ['s' => true]);
                    break;

                default:
                    $inline .= $this->inline($child, $format);
            }
        }

        $this->flush($inline, $style, $prefix);
    }

    /** Collects the runs of an inline element, emphasis and all. */
    private function inline(DOMNode $node, array $format): string
    {
        $out = '';

        foreach ($node->childNodes as $child) {
            $name = strtolower($child->nodeName);

            if ($child instanceof DOMText) {
                $text = preg_replace('/\s+/u', ' ', $child->textContent) ?? '';
                if ($text !== '') {
                    $out .= self::run($text, $format);
                }
                continue;
            }

            $out .= match ($name) {
                'strong', 'b'    => $this->inline($child, $format + ['b' => true]),
                'em', 'i'        => $this->inline($child, $format + ['i' => true]),
                'u'              => $this->inline($child, $format + ['u' => true]),
                's', 'strike'    => $this->inline($child, $format + ['s' => true]),
                'br'             => '<w:r><w:br/></w:r>',
                default          => $this->inline($child, $format),
            };
        }

        return $out;
    }

    private function flush(string &$inline, string $style, string $prefix = ''): void
    {
        if (trim(strip_tags($inline)) === '' && !str_contains($inline, '<w:br/>')) {
            $inline = '';

            return;
        }

        $runs = ($prefix === '' ? '' : self::run($prefix, [])) . $inline;
        $this->body .= '<w:p>' . self::properties($style) . $runs . '</w:p>';
        $inline = '';
    }

    // ---------------------------------------------------------- primitives

    private function para(string $text, string $style): void
    {
        $text = trim($text);
        if ($text === '') {
            return;
        }

        $this->body .= '<w:p>' . self::properties($style) . self::run($text, []) . '</w:p>';
    }

    private static function properties(string $style): string
    {
        return '<w:pPr><w:pStyle w:val="' . $style . '"/></w:pPr>';
    }

    /** @param array{b?: bool, i?: bool, u?: bool, s?: bool} $format */
    private static function run(string $text, array $format): string
    {
        $properties = '';
        if (!empty($format['b'])) {
            $properties .= '<w:b/>';
        }
        if (!empty($format['i'])) {
            $properties .= '<w:i/>';
        }
        if (!empty($format['u'])) {
            $properties .= '<w:u w:val="single"/>';
        }
        if (!empty($format['s'])) {
            $properties .= '<w:strike/>';
        }

        return '<w:r>'
            . ($properties === '' ? '' : '<w:rPr>' . $properties . '</w:rPr>')
            . '<w:t xml:space="preserve">' . self::escape($text) . '</w:t>'
            . '</w:r>';
    }

    private static function escape(string $text): string
    {
        // Word rejects the control characters that a stray paste can leave behind.
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $text) ?? $text;

        return htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    // ------------------------------------------------------- package parts

    private function document(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            . '<w:body>' . $this->body
            . '<w:sectPr><w:pgSz w:w="11906" w:h="16838"/>'
            . '<w:pgMar w:top="1418" w:right="1418" w:bottom="1418" w:left="1418"'
            . ' w:header="708" w:footer="708" w:gutter="0"/></w:sectPr>'
            . '</w:body></w:document>';
    }

    private static function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
            . '<Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>'
            . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
            . '</Types>';
    }

    private static function rels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
            . '</Relationships>';
    }

    private static function documentRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>';
    }

    private static function core(string $title): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<cp:coreProperties'
            . ' xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties"'
            . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
            . ' xmlns:dcterms="http://purl.org/dc/terms/"'
            . ' xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            . '<dc:title>' . self::escape($title) . '</dc:title>'
            . '<dcterms:created xsi:type="dcterms:W3CDTF">' . date('c') . '</dcterms:created>'
            . '</cp:coreProperties>';
    }

    private static function styles(): string
    {
        $heading = static function (int $level, int $halfPoints, bool $keepNext = true): string {
            return '<w:style w:type="paragraph" w:styleId="Heading' . $level . '">'
                . '<w:name w:val="heading ' . $level . '"/>'
                . '<w:basedOn w:val="Normal"/><w:next w:val="Normal"/>'
                . '<w:qFormat/>'
                . '<w:pPr><w:outlineLvl w:val="' . ($level - 1) . '"/>'
                . '<w:spacing w:before="' . (360 - $level * 40) . '" w:after="120"/>'
                . ($keepNext ? '<w:keepNext/>' : '')
                . '</w:pPr>'
                . '<w:rPr><w:b/><w:sz w:val="' . $halfPoints . '"/></w:rPr>'
                . '</w:style>';
        };

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            . '<w:docDefaults><w:rPrDefault><w:rPr>'
            . '<w:rFonts w:ascii="Georgia" w:hAnsi="Georgia"/><w:sz w:val="22"/>'
            . '</w:rPr></w:rPrDefault>'
            . '<w:pPrDefault><w:pPr><w:spacing w:after="140" w:line="276" w:lineRule="auto"/></w:pPr></w:pPrDefault>'
            . '</w:docDefaults>'
            . '<w:style w:type="paragraph" w:default="1" w:styleId="Normal">'
            . '<w:name w:val="Normal"/><w:qFormat/></w:style>'
            . '<w:style w:type="paragraph" w:styleId="Title">'
            . '<w:name w:val="Title"/><w:basedOn w:val="Normal"/><w:next w:val="Normal"/><w:qFormat/>'
            . '<w:pPr><w:spacing w:after="120"/><w:outlineLvl w:val="0"/></w:pPr>'
            . '<w:rPr><w:b/><w:sz w:val="52"/></w:rPr></w:style>'
            . '<w:style w:type="paragraph" w:styleId="Subtitle">'
            . '<w:name w:val="Subtitle"/><w:basedOn w:val="Normal"/><w:next w:val="Normal"/><w:qFormat/>'
            . '<w:rPr><w:i/><w:sz w:val="28"/><w:color w:val="595959"/></w:rPr></w:style>'
            . $heading(1, 36) . $heading(2, 30) . $heading(3, 26) . $heading(4, 24)
            . '<w:style w:type="paragraph" w:styleId="FieldLabel">'
            . '<w:name w:val="Field Label"/><w:basedOn w:val="Normal"/><w:next w:val="Normal"/>'
            . '<w:pPr><w:keepNext/><w:spacing w:before="160" w:after="60"/></w:pPr>'
            . '<w:rPr><w:b/><w:caps/><w:sz w:val="18"/><w:color w:val="595959"/></w:rPr></w:style>'
            . '<w:style w:type="paragraph" w:styleId="ListItem">'
            . '<w:name w:val="List Item"/><w:basedOn w:val="Normal"/>'
            . '<w:pPr><w:ind w:left="360"/><w:spacing w:after="60"/></w:pPr></w:style>'
            . '<w:style w:type="paragraph" w:styleId="Quote">'
            . '<w:name w:val="Quote"/><w:basedOn w:val="Normal"/><w:qFormat/>'
            . '<w:pPr><w:ind w:left="480"/></w:pPr><w:rPr><w:i/></w:rPr></w:style>'
            . '<w:style w:type="paragraph" w:styleId="Meta">'
            . '<w:name w:val="Meta"/><w:basedOn w:val="Normal"/>'
            . '<w:rPr><w:i/><w:sz w:val="18"/><w:color w:val="595959"/></w:rPr></w:style>'
            . '</w:styles>';
    }
}
