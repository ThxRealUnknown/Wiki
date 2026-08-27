<?php

namespace App\Export;

use App\CategoryRepo;
use App\ChapterRepo;
use App\Config;
use App\ConnectionRepo;
use App\EntryLinks;
use App\EntryRepo;
use App\FieldTypes;
use App\LayoutRepo;

/**
 * Turns the archives, or the book, into a Document ready for any renderer.
 *
 * The two are kept strictly apart: the wiki export never touches chapters, and
 * the book export never touches archives.
 */
final class Builder
{
    private CategoryRepo $categories;
    private LayoutRepo $layouts;
    private EntryRepo $entries;
    private ChapterRepo $chapters;
    private ConnectionRepo $connections;

    public function __construct()
    {
        $this->categories = new CategoryRepo();
        $this->layouts = new LayoutRepo();
        $this->entries = new EntryRepo();
        $this->chapters = new ChapterRepo();
        $this->connections = new ConnectionRepo();
    }

    // ------------------------------------------------------------- the wiki

    /**
     * @param array{connections?: bool, empty_fields?: bool} $options
     */
    public function wiki(array $options = []): Document
    {
        $withConnections = $options['connections'] ?? true;
        $withEmptyFields = $options['empty_fields'] ?? false;

        $siteName = (string) Config::get('site_name', 'Worldbuilder');
        $document = new Document($siteName, 'World archive');

        $tree = $this->categories->treeWithCounts();

        $ordered = [];
        foreach ($tree as $parent) {
            $ordered[] = ['category' => $parent, 'depth' => 0];
            foreach ($parent['children'] as $child) {
                $ordered[] = ['category' => $child, 'depth' => 1];
            }
        }

        $totalEntries = 0;
        foreach ($ordered as $row) {
            $totalEntries += (int) $row['category']['entry_count'];
        }

        $document->addMeta('Exported', date('j F Y, H:i'));
        $document->addMeta('Archives', (string) count($ordered));
        $document->addMeta('Entries', (string) $totalEntries);

        $contents = [];
        foreach ($ordered as $row) {
            $contents[] = [
                trim(($row['category']['icon'] ?? '') . ' ' . $row['category']['name'])
                    . ' (' . (int) $row['category']['entry_count'] . ')',
                $row['depth'] + 1,
            ];
        }
        $document->contents($contents);

        foreach ($ordered as $row) {
            $category = $row['category'];
            $document->pageBreak();
            $document->heading(1, trim(($category['icon'] ?? '') . ' ' . $category['name']));

            if (!empty($category['description'])) {
                $document->text($category['description']);
            }

            $sort = CategoryRepo::cleanSort($category['default_sort'] ?? 'title');
            $list = $this->entries->forCategory((int) $category['id'], '', null, $sort);

            if ($list === []) {
                $document->text('This archive is empty.');
                continue;
            }

            foreach ($list as $entry) {
                $this->entrySection($document, $entry, $withConnections, $withEmptyFields);
            }
        }

        return $document;
    }

    private function entrySection(
        Document $document,
        array $entry,
        bool $withConnections,
        bool $withEmptyFields
    ): void {
        $entryId = (int) $entry['id'];

        $document->heading(2, $entry['title']);
        $document->meta([
            'Layout'      => $entry['layout_name'] ?? '',
            'Last edited' => human_time($entry['updated_at']),
        ]);

        $fields = $this->layouts->fields((int) $entry['layout_id']);
        $values = $this->entries->values($entryId);
        $links = $this->entries->links($entryId);

        foreach ($fields as $field) {
            $fieldId = (int) $field['id'];
            $type = (string) $field['field_type'];
            $stored = $values[$fieldId]['value_text'] ?? null;

            if (FieldTypes::isRelation($type)) {
                $targets = $links[$fieldId] ?? [];
                if ($targets === []) {
                    if ($withEmptyFields) {
                        $document->field($field['label'], Document::KIND_EMPTY, null);
                    }
                    continue;
                }

                $document->field($field['label'], Document::KIND_LINKS, array_map(
                    static fn (array $t) => [
                        $t['title'],
                        empty($t['relation_type']) ? $t['category_name'] : $t['relation_type'] . ' · ' . $t['category_name'],
                    ],
                    $targets
                ));
                continue;
            }

            if ($stored === null || $stored === '') {
                if ($withEmptyFields) {
                    $document->field($field['label'], Document::KIND_EMPTY, null);
                }
                continue;
            }

            switch ($type) {
                case FieldTypes::RICHTEXT:
                    // Links are stored by guid; resolved to a real address on the way out.
                    $document->field($field['label'], Document::KIND_RICH, EntryLinks::resolve($stored));
                    break;

                case FieldTypes::TAGS:
                    $document->field(
                        $field['label'],
                        Document::KIND_LIST,
                        json_decode($stored, true) ?: []
                    );
                    break;

                case FieldTypes::IMAGE:
                    $document->field($field['label'], Document::KIND_IMAGE, $stored);
                    break;

                case FieldTypes::NUMBER:
                    $unit = $field['config']['unit'] ?? '';
                    $document->field(
                        $field['label'],
                        Document::KIND_TEXT,
                        trim($stored . ($unit !== '' ? ' ' . $unit : ''))
                    );
                    break;

                default:
                    $document->field($field['label'], Document::KIND_TEXT, $stored);
            }
        }

        if (!$withConnections) {
            return;
        }

        $items = [];
        foreach ($this->connections->forTarget(ConnectionRepo::ENTRY, $entryId) as $connection) {
            $items[] = [$connection['title'], $connection['context']];
        }
        $document->links('Connections', $items);

        $backlinks = [];
        foreach ($this->entries->backlinks($entryId) as $backlink) {
            $where = $backlink['category_name'] . ' · ' . $backlink['field_label'];
            if (!empty($backlink['relation_type'])) {
                $where .= ' (' . $backlink['relation_type'] . ')';
            }
            $backlinks[] = [$backlink['title'], $where];
        }
        $document->links('Referenced by', $backlinks);
    }

    // ------------------------------------------------------------- the book

    /**
     * @param array{hidden?: bool, notes?: bool} $options
     */
    public function book(array $options = []): Document
    {
        $withHidden = $options['hidden'] ?? false;
        $withNotes = $options['notes'] ?? false;

        $title = (string) Config::get('book_title', (string) Config::get('site_name', 'Worldbuilder'));
        $document = new Document($title, $withHidden ? 'Draft manuscript' : 'Manuscript');

        $chapters = $withHidden ? $this->chapters->all() : $this->chapters->published();

        $document->addMeta('Exported', date('j F Y, H:i'));
        $document->addMeta('Chapters', (string) count($chapters));
        if ($withHidden) {
            $document->addMeta('Includes', 'chapters not yet shown in the Story');
        }

        $words = 0;
        foreach ($chapters as $chapter) {
            $words += word_count((string) $chapter['content']);
        }
        $document->addMeta('Words', number_format($words));

        if ($chapters === []) {
            $document->text($withHidden
                ? 'There are no chapters yet.'
                : 'No chapters are shown in the Story yet. Export the draft instead to include hidden ones.');

            return $document;
        }

        $groups = ChapterRepo::grouped($chapters);

        $contents = [];
        foreach ($groups as $group) {
            if ($group['part'] !== null) {
                $contents[] = [$group['part'], 1];
            }
            foreach ($group['chapters'] as $chapter) {
                $contents[] = [self::chapterTitle($chapter), $group['part'] !== null ? 2 : 1];
            }
        }
        $document->contents($contents);

        foreach ($groups as $group) {
            if ($group['part'] !== null) {
                $document->pageBreak();
                $document->heading(1, $group['part']);
            }

            foreach ($group['chapters'] as $chapter) {
                $document->pageBreak();
                $document->heading($group['part'] !== null ? 2 : 1, self::chapterTitle($chapter));

                if ($withHidden && (int) $chapter['is_visible'] !== 1) {
                    $document->meta(['Status' => 'Hidden from the Story']);
                }

                $content = (string) $chapter['content'];
                if (trim(strip_tags($content)) === '') {
                    $document->text('(This chapter has no text yet.)');
                } else {
                    $document->rich(EntryLinks::resolve($content));
                }

                if ($withNotes && trim(strip_tags((string) $chapter['notes'])) !== '') {
                    $document->heading(2, 'Notes');
                    $document->rich(EntryLinks::resolve((string) $chapter['notes']));
                }
            }
        }

        return $document;
    }

    private static function chapterTitle(array $chapter): string
    {
        $number = ChapterRepo::formatNumber(
            $chapter['chapter_number'] === null ? null : (float) $chapter['chapter_number']
        );

        return $number === '' ? $chapter['title'] : $number . '. ' . $chapter['title'];
    }
}
