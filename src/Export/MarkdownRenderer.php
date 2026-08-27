<?php

namespace App\Export;

/**
 * Plain Markdown: no styling, but every word survives, and it opens in any
 * editor, pastes into most writing tools, and diffs sensibly in version control.
 */
final class MarkdownRenderer implements Renderer
{
    /** The heading depth last written, so rich text can nest beneath it. */
    private int $depth = 1;


    public function extension(): string
    {
        return 'md';
    }

    public function contentType(): string
    {
        return 'text/markdown; charset=utf-8';
    }

    public function render(Document $document): string
    {
        $out = '# ' . $document->title() . "\n\n";

        if ($document->subtitle() !== '') {
            $out .= '*' . $document->subtitle() . "*\n\n";
        }

        $meta = [];
        foreach ($document->metaLines() as $label => $value) {
            $meta[] = '**' . $label . ':** ' . $value;
        }
        if ($meta !== []) {
            $out .= implode(' · ', $meta) . "\n\n";
        }

        $out .= "---\n\n";

        foreach ($document->nodes() as $node) {
            $out .= $this->node($node);
        }

        return trim(preg_replace("/\n{4,}/", "\n\n\n", $out) ?? $out) . "\n";
    }

    private function node(array $node): string
    {
        switch ($node[0]) {
            case 'heading':
                // The document title already used a single #.
                $level = min(6, $node[1] + 1);
                $this->depth = $level;

                return "\n" . str_repeat('#', $level) . ' ' . $node[2] . "\n\n";

            case 'text':
                return $node[1] . "\n\n";

            case 'rich':
                $markdown = RichText::toMarkdown($node[1], $this->depth);

                return $markdown === '' ? '' : $markdown . "\n\n";

            case 'meta':
                $parts = [];
                foreach ($node[1] as $label => $value) {
                    $parts[] = '*' . $label . ': ' . $value . '*';
                }

                return implode(' · ', $parts) . "\n\n";

            case 'field':
                return $this->field($node[1], $node[2], $node[3]);

            case 'links':
                $out = '**' . $node[1] . "**\n\n";
                foreach ($node[2] as [$title, $context]) {
                    $out .= '- ' . $title . ($context !== '' ? ' — *' . $context . '*' : '') . "\n";
                }

                return $out . "\n";

            case 'toc':
                $out = "## Contents\n\n";
                foreach ($node[1] as [$text, $level]) {
                    $out .= str_repeat('  ', max(0, $level - 1)) . '- ' . $text . "\n";
                }

                return $out . "\n";

            case 'break':
                // Markdown has no page break; a rule keeps the sections apart.
                return "\n---\n\n";
        }

        return '';
    }

    private function field(string $label, string $kind, mixed $value): string
    {
        $head = '**' . $label . "**\n\n";

        switch ($kind) {
            case Document::KIND_RICH:
                // Nest one level deeper than the entry heading.
                $markdown = RichText::toMarkdown((string) $value, $this->depth + 1);

                return $markdown === '' ? '' : $head . $markdown . "\n\n";

            case Document::KIND_LIST:
                $items = array_map(static fn ($i) => (string) $i, (array) $value);

                return $head . implode(', ', $items) . "\n\n";

            case Document::KIND_LINKS:
                $out = $head;
                foreach ((array) $value as [$title, $context]) {
                    $out .= '- ' . $title . ($context !== '' ? ' — *' . $context . '*' : '') . "\n";
                }

                return $out . "\n";

            case Document::KIND_IMAGE:
                return $head . '![' . $label . '](' . $value . ")\n\n";

            case Document::KIND_EMPTY:
                return $head . "*Not set*\n\n";

            default:
                return $head . (string) $value . "\n\n";
        }
    }
}
