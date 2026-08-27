<?php

namespace App\Export;

/**
 * A format-neutral description of an export: a title, some metadata, and a flat
 * list of nodes. The renderers walk this rather than the database, so adding a
 * new output format means writing one renderer and nothing else.
 *
 * Nodes are plain arrays so they stay trivially inspectable:
 *   ['heading', level, text]
 *   ['text',    string]                    a plain paragraph
 *   ['rich',    html]                      sanitised rich text
 *   ['field',   label, kind, value]        one field of an entry
 *   ['meta',    [label => value]]          a compact key/value strip
 *   ['links',   heading, [[title, context]]]
 *   ['toc',     [[text, level]]]
 *   ['break']                              a page break between sections
 */
final class Document
{
    public const KIND_TEXT   = 'text';
    public const KIND_RICH   = 'rich';
    public const KIND_LIST   = 'list';
    public const KIND_LINKS  = 'links';
    public const KIND_IMAGE  = 'image';
    public const KIND_EMPTY  = 'empty';

    private string $title;
    private string $subtitle;
    private array $meta = [];
    private array $nodes = [];

    public function __construct(string $title, string $subtitle = '')
    {
        $this->title = $title;
        $this->subtitle = $subtitle;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function subtitle(): string
    {
        return $this->subtitle;
    }

    /** @return array<string, string> */
    public function metaLines(): array
    {
        return $this->meta;
    }

    public function addMeta(string $label, string $value): void
    {
        $this->meta[$label] = $value;
    }

    /** @return array<int, array> */
    public function nodes(): array
    {
        return $this->nodes;
    }

    public function heading(int $level, string $text): void
    {
        $this->nodes[] = ['heading', $level, $text];
    }

    public function text(string $text): void
    {
        if (trim($text) === '') {
            return;
        }
        $this->nodes[] = ['text', $text];
    }

    public function rich(string $html): void
    {
        if (trim(strip_tags($html)) === '') {
            return;
        }
        $this->nodes[] = ['rich', $html];
    }

    public function field(string $label, string $kind, mixed $value): void
    {
        $this->nodes[] = ['field', $label, $kind, $value];
    }

    /** @param array<string, string> $pairs */
    public function meta(array $pairs): void
    {
        $pairs = array_filter($pairs, static fn ($v) => trim((string) $v) !== '');
        if ($pairs === []) {
            return;
        }
        $this->nodes[] = ['meta', $pairs];
    }

    /** @param array<int, array{0:string, 1:string}> $items title, context */
    public function links(string $heading, array $items): void
    {
        if ($items === []) {
            return;
        }
        $this->nodes[] = ['links', $heading, $items];
    }

    /** @param array<int, array{0:string, 1:int}> $items text, level */
    public function contents(array $items): void
    {
        if ($items === []) {
            return;
        }
        $this->nodes[] = ['toc', $items];
    }

    public function pageBreak(): void
    {
        $this->nodes[] = ['break'];
    }

    /**
     * A stable id for a heading, used for in-document links. Duplicated titles
     * across archives get a numeric suffix so anchors stay unique.
     */
    public static function anchor(string $text, array &$seen): string
    {
        $base = slugify($text, 'section');
        $anchor = $base;
        $suffix = 2;
        while (isset($seen[$anchor])) {
            $anchor = $base . '-' . $suffix++;
        }
        $seen[$anchor] = true;

        return $anchor;
    }
}
