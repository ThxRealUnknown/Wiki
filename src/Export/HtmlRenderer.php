<?php

namespace App\Export;

/**
 * A single self-contained HTML file: styles inlined, no external requests, and
 * a table of contents that links to every heading. Opens in any browser and
 * prints to PDF cleanly — the page breaks are already in the stylesheet.
 */
final class HtmlRenderer implements Renderer
{
    public function extension(): string
    {
        return 'html';
    }

    public function contentType(): string
    {
        return 'text/html; charset=utf-8';
    }

    public function render(Document $document): string
    {
        // Assigned up front so the contents list can point at headings not yet written.
        $seen = [];
        $anchors = [];
        $headings = [];
        foreach ($document->nodes() as $index => $node) {
            if ($node[0] === 'heading' && $node[1] <= 2) {
                $anchor = Document::anchor($node[2], $seen);
                $anchors[$index] = $anchor;
                $headings[] = ['level' => $node[1], 'text' => $node[2], 'anchor' => $anchor];
            }
        }

        $body = '';
        foreach ($document->nodes() as $index => $node) {
            $body .= $this->node($node, $anchors[$index] ?? null, $headings);
        }

        $meta = '';
        foreach ($document->metaLines() as $label => $value) {
            $meta .= '<span><strong>' . e($label) . ':</strong> ' . e($value) . '</span>';
        }

        return '<!DOCTYPE html>' . "\n"
            . '<html lang="en"><head>' . "\n"
            . '<meta charset="utf-8">' . "\n"
            . '<meta name="viewport" content="width=device-width, initial-scale=1">' . "\n"
            . '<title>' . e($document->title()) . '</title>' . "\n"
            . '<style>' . self::css() . '</style>' . "\n"
            . '</head><body>' . "\n"
            . '<header class="cover">' . "\n"
            . '<h1 class="cover-title">' . e($document->title()) . '</h1>' . "\n"
            . ($document->subtitle() !== ''
                ? '<p class="cover-subtitle">' . e($document->subtitle()) . '</p>' . "\n"
                : '')
            . ($meta !== '' ? '<p class="cover-meta">' . $meta . '</p>' . "\n" : '')
            . '</header>' . "\n"
            . $body
            . '</body></html>' . "\n";
    }

    /**
     * @param array<int, array{level:int, text:string, anchor:string}> $headings
     */
    private function node(array $node, ?string $anchor, array $headings): string
    {
        switch ($node[0]) {
            case 'heading':
                $level = min(6, max(1, $node[1] + 1));   // the cover already used h1
                $id = $anchor !== null ? ' id="' . e($anchor) . '"' : '';

                return '<h' . $level . $id . ' class="h' . $node[1] . '">'
                    . e($node[2]) . '</h' . $level . '>' . "\n";

            case 'text':
                return '<p>' . e($node[1]) . '</p>' . "\n";

            case 'rich':
                // Sanitised when it was saved, so it is safe to emit as markup.
                return '<div class="rich">' . $node[1] . '</div>' . "\n";

            case 'meta':
                $parts = [];
                foreach ($node[1] as $label => $value) {
                    $parts[] = '<span><strong>' . e($label) . ':</strong> ' . e((string) $value) . '</span>';
                }

                return '<p class="entry-meta">' . implode('', $parts) . '</p>' . "\n";

            case 'field':
                return $this->field($node[1], $node[2], $node[3]);

            case 'links':
                $items = '';
                foreach ($node[2] as [$title, $context]) {
                    $items .= '<li>' . e($title)
                        . ($context !== '' ? ' <span class="ctx">' . e($context) . '</span>' : '')
                        . '</li>';
                }

                return '<div class="links"><h4>' . e($node[1]) . '</h4><ul>' . $items . '</ul></div>' . "\n";

            case 'toc':
                return $this->contents($headings);

            case 'break':
                return '<div class="page-break"></div>' . "\n";
        }

        return '';
    }

    private function field(string $label, string $kind, mixed $value): string
    {
        $head = '<h4 class="field-label">' . e($label) . '</h4>';

        switch ($kind) {
            case Document::KIND_RICH:
                return '<div class="field">' . $head . '<div class="rich">' . $value . '</div></div>' . "\n";

            case Document::KIND_LIST:
                $items = '';
                foreach ((array) $value as $item) {
                    $items .= '<li>' . e((string) $item) . '</li>';
                }

                return '<div class="field">' . $head . '<ul class="tags">' . $items . '</ul></div>' . "\n";

            case Document::KIND_LINKS:
                $items = '';
                foreach ((array) $value as [$title, $context]) {
                    $items .= '<li>' . e($title)
                        . ($context !== '' ? ' <span class="ctx">' . e($context) . '</span>' : '')
                        . '</li>';
                }

                return '<div class="field">' . $head . '<ul>' . $items . '</ul></div>' . "\n";

            case Document::KIND_IMAGE:
                $data = self::imageData((string) $value);

                return '<div class="field">' . $head
                    . ($data !== null
                        ? '<img src="' . e($data) . '" alt="' . e($label) . '">'
                        : '<p class="empty">Image not found: ' . e((string) $value) . '</p>')
                    . '</div>' . "\n";

            case Document::KIND_EMPTY:
                return '<div class="field">' . $head . '<p class="empty">Not set</p></div>' . "\n";

            default:
                return '<div class="field">' . $head
                    . '<p class="plain">' . nl2br(e((string) $value)) . '</p></div>' . "\n";
        }
    }

    /** @param array<int, array{level:int, text:string, anchor:string}> $headings */
    private function contents(array $headings): string
    {
        if ($headings === []) {
            return '';
        }

        $out = '<nav class="toc"><h2>Contents</h2><ul>';
        $open = false;

        foreach ($headings as $heading) {
            if ($heading['level'] === 1) {
                if ($open) {
                    $out .= '</ul></li>';
                    $open = false;
                }
                $out .= '<li class="toc-1"><a href="#' . e($heading['anchor']) . '">'
                    . e($heading['text']) . '</a>';
                $out .= '<ul class="toc-sub">';
                $open = true;
                continue;
            }

            if (!$open) {
                $out .= '<li class="toc-1"><ul class="toc-sub">';
                $open = true;
            }
            $out .= '<li><a href="#' . e($heading['anchor']) . '">' . e($heading['text']) . '</a></li>';
        }

        if ($open) {
            $out .= '</ul></li>';
        }

        return $out . '</ul></nav>' . "\n";
    }

    /** Images travel inside the file so the export stays a single document. */
    private static function imageData(string $relativePath): ?string
    {
        if (!str_starts_with($relativePath, 'uploads/') || str_contains($relativePath, '..')) {
            return null;
        }

        $absolute = realpath(APP_ROOT . '/public/' . $relativePath);
        $base = realpath(APP_ROOT . '/public/uploads');

        if ($absolute === false || $base === false || !str_starts_with($absolute, $base)) {
            return null;
        }

        $info = @getimagesize($absolute);
        if ($info === false) {
            return null;
        }

        $bytes = @file_get_contents($absolute);
        if ($bytes === false) {
            return null;
        }

        return 'data:' . $info['mime'] . ';base64,' . base64_encode($bytes);
    }

    private static function css(): string
    {
        return <<<'CSS'
:root { --ink:#1c1d20; --muted:#6a6d74; --line:#dcd8d0; --accent:#8a6220; }
* { box-sizing:border-box; }
body {
    margin:0 auto; max-width:46em; padding:3rem 1.5rem 6rem;
    background:#fdfcfa; color:var(--ink);
    font:16px/1.62 Georgia,"Iowan Old Style",serif;
}
h1,h2,h3,h4 { font-family:"Segoe UI",system-ui,sans-serif; line-height:1.25; }
a { color:var(--accent); }

.cover { text-align:center; padding:4rem 0 3rem; border-bottom:2px solid var(--line); margin-bottom:2.5rem; }
.cover-title { font-size:2.6rem; margin:0 0 .4rem; letter-spacing:-.02em; }
.cover-subtitle { margin:0 0 1.4rem; color:var(--muted); font-size:1.1rem; font-style:italic; }
.cover-meta { display:flex; flex-wrap:wrap; gap:.4rem 1.4rem; justify-content:center;
    font-family:"Segoe UI",system-ui,sans-serif; font-size:.8rem; color:var(--muted); margin:0; }

.toc { margin:0 0 2rem; padding:1.4rem 1.6rem; background:#f6f3ee; border:1px solid var(--line); border-radius:6px; }
.toc h2 { margin:0 0 .8rem; font-size:.8rem; text-transform:uppercase; letter-spacing:.1em; color:var(--muted); }
.toc ul { margin:0; padding:0; list-style:none; }
.toc a { text-decoration:none; }
.toc a:hover { text-decoration:underline; }
.toc-1 > a { font-weight:600; font-family:"Segoe UI",system-ui,sans-serif; }
.toc-sub { margin:.15rem 0 .7rem .9rem !important; border-left:1px solid var(--line); padding-left:.9rem !important; }
.toc-sub li { font-size:.9rem; color:var(--muted); }

h2.h1 { font-size:1.9rem; margin:0 0 .6rem; padding-bottom:.4rem; border-bottom:2px solid var(--line); }
h3.h2 { font-size:1.35rem; margin:2.4rem 0 .3rem; }
h4.h3 { font-size:1.05rem; margin:1.6rem 0 .3rem; }

.entry-meta { display:flex; flex-wrap:wrap; gap:.2rem 1.1rem; margin:.2rem 0 1rem;
    font-family:"Segoe UI",system-ui,sans-serif; font-size:.76rem; color:var(--muted); }

.field { margin:0 0 1.1rem; }
.field-label { font-family:"Segoe UI",system-ui,sans-serif; font-size:.7rem; font-weight:700;
    letter-spacing:.09em; text-transform:uppercase; color:var(--muted); margin:0 0 .25rem; }
.field p, .rich p { margin:0 0 .7em; }
.field .plain { white-space:pre-wrap; }
.field .empty { color:#a5a29c; font-style:italic; }
.field img { max-width:100%; border:1px solid var(--line); border-radius:4px; }
.field ul, .links ul { margin:.1rem 0 0; padding-left:1.2rem; }
ul.tags { list-style:none; padding:0; display:flex; flex-wrap:wrap; gap:.35rem; }
ul.tags li { border:1px solid var(--line); border-radius:99px; padding:.05rem .6rem; font-size:.85rem; }
.ctx { color:var(--muted); font-size:.82rem; }

.links { margin:1.2rem 0 0; padding-top:.7rem; border-top:1px solid var(--line); }
.links h4 { font-family:"Segoe UI",system-ui,sans-serif; font-size:.7rem; font-weight:700;
    letter-spacing:.09em; text-transform:uppercase; color:var(--muted); margin:0 0 .25rem; }
.links li { font-size:.92rem; }

.rich blockquote { margin:0 0 .8em; padding-left:1em; border-left:3px solid var(--line); color:var(--muted); }
.rich pre { background:#f2efe9; padding:.8em 1em; border-radius:4px; overflow-x:auto; }
.rich code { background:#f2efe9; padding:.05em .35em; border-radius:3px; font-size:.9em; }
.rich hr { border:none; border-top:1px solid var(--line); margin:1.6em 0; }
.rich h1,.rich h2,.rich h3,.rich h4 { margin:1.2em 0 .35em; }

/* Alignment and indentation carried by the rich-text classes. */
.align-left { text-align:left; }
.align-center { text-align:center; }
.align-right { text-align:right; }
.align-justify { text-align:justify; }
.indent-1 { padding-left:2.5em; }
.indent-2 { padding-left:5em; }
.indent-3 { padding-left:7.5em; }
.indent-4 { padding-left:10em; }
.first-line-indent { text-indent:2em; }

.page-break { height:0; }

@media print {
    body { max-width:none; padding:0; background:#fff; font-size:11.5pt; }
    .page-break { break-before:page; page-break-before:always; }
    .toc { background:none; border:none; padding:0; }
    h2.h1 { break-after:avoid; page-break-after:avoid; }
    h3.h2, h4.h3, .field-label { break-after:avoid; page-break-after:avoid; }
    .field, .links { break-inside:avoid; page-break-inside:avoid; }
    a { color:inherit; text-decoration:none; }
}
CSS;
    }
}
