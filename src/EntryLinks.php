<?php

namespace App;

use DOMDocument;
use DOMElement;

/**
 * Turns the stored form of a link between entries into a working one.
 *
 * A link names its target by guid, not address:
 *
 *     <a href="/c/places/e/red-lake" data-entry="8f2c…">Red Lake</a>
 *
 * The guid never changes even when the entry is renamed (which rewrites its
 * slug), so the href is resolved from it here on the way out.
 *
 * A link whose target has been deleted is marked rather than silently
 * becoming plain text.
 */
final class EntryLinks
{
    /** guid => ['url' => string, 'title' => string, 'archive' => string] */
    private static ?array $targets = null;

    /** Rewrites every internal link in a stored rich-text value. Text with no internal links is returned untouched, unparsed. */
    public static function resolve(?string $html, ?Database $db = null): string
    {
        $html = (string) $html;

        if ($html === '' || !str_contains($html, 'data-entry')) {
            return $html;
        }

        $targets = self::targets($db);

        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML(
            '<?xml encoding="UTF-8"><div id="entry-links-root">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $document->getElementById('entry-links-root');
        if (!$root instanceof DOMElement) {
            return $html;
        }

        foreach (iterator_to_array($root->getElementsByTagName('a')) as $link) {
            $guid = $link->getAttribute('data-entry');
            if ($guid === '') {
                continue;
            }

            $target = $targets[$guid] ?? null;

            if ($target === null) {
                self::markMissing($link);
                continue;
            }

            $link->setAttribute('href', url($target['url']));
            $link->setAttribute('class', 'entry-link');
            $link->setAttribute('title', $target['archive'] . ' · ' . $target['title']);

            // Only an outbound link opens elsewhere; this one stays in the wiki.
            $link->removeAttribute('target');
            $link->removeAttribute('rel');
        }

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $document->saveHTML($child);
        }

        return $out;
    }

    /** Replaces a link to a deleted entry with plain text, keeping its content. */
    private static function markMissing(DOMElement $link): void
    {
        $span = $link->ownerDocument->createElement('span');
        $span->setAttribute('class', 'entry-link is-missing');
        $span->setAttribute('title', 'This entry no longer exists.');

        while ($link->firstChild) {
            $span->appendChild($link->firstChild);
        }

        $link->parentNode->replaceChild($span, $link);
    }

    /**
     * Every entry that can be linked to, by guid. Cached per request.
     *
     * @return array<string, array{url: string, title: string, archive: string}>
     */
    private static function targets(?Database $db = null): array
    {
        if (self::$targets !== null) {
            return self::$targets;
        }

        $rows = ($db ?? Database::instance())->all(
            'SELECT e.guid, e.title, e.slug, c.slug AS category_slug, c.name AS category_name
               FROM entries e
               JOIN categories c ON c.id = e.category_id
              WHERE e.guid IS NOT NULL'
        );

        self::$targets = [];
        foreach ($rows as $row) {
            self::$targets[(string) $row['guid']] = [
                'url'     => '/c/' . $row['category_slug'] . '/e/' . $row['slug'],
                'title'   => (string) $row['title'],
                'archive' => (string) $row['category_name'],
            ];
        }

        return self::$targets;
    }

    /** Drops the cached map. Only the importer needs this, having rewritten it. */
    public static function forget(): void
    {
        self::$targets = null;
    }
}
