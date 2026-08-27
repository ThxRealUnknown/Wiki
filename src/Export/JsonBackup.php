<?php

namespace App\Export;

use App\ChapterRepo;
use App\Config;
use App\ConnectionRepo;
use App\Database;
use App\EntryLinks;
use App\Guid;
use App\LayoutRepo;
use RuntimeException;

/**
 * The round-trippable backup: everything the database holds, written as JSON
 * and readable back in.
 *
 * Every row is identified by its guid rather than its auto-increment id, so a
 * file taken from one database restores correctly into another. Archived
 * fields travel too, along with their stored values.
 */
final class JsonBackup
{
    public const FORMAT = 'worldbuilder-backup';
    public const VERSION = 1;

    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::instance();
    }

    // ------------------------------------------------------------- writing

    public function export(): string
    {
        $data = [
            'format'      => self::FORMAT,
            'version'     => self::VERSION,
            'exported_at' => date('c'),
            'site_name'   => (string) Config::get('site_name', 'Worldbuilder'),
            'settings'    => $this->db->tableExists('settings')
                ? $this->db->all('SELECT name, value FROM settings ORDER BY name')
                : [],
            'categories'  => $this->categories(),
            'chapters'    => $this->chapters(),
            'connections' => $this->connections(),
        ];

        return json_encode(
            $data,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        ) ?: '{}';
    }

    /** Archives, each carrying its layouts, their fields, and its entries. */
    private function categories(): array
    {
        $layouts = new LayoutRepo($this->db);

        // parent is written as a guid so nesting survives a restore.
        $guidById = [];
        foreach ($this->db->all('SELECT id, guid FROM categories') as $row) {
            $guidById[(int) $row['id']] = $row['guid'];
        }

        $out = [];
        foreach ($this->db->all('SELECT * FROM categories ORDER BY sort_order, id') as $category) {
            $categoryId = (int) $category['id'];

            $layoutRows = [];
            foreach ($this->db->all(
                'SELECT * FROM layouts WHERE category_id = :cid ORDER BY sort_order, id',
                ['cid' => $categoryId]
            ) as $layout) {
                $fields = [];
                foreach ($layouts->allFields((int) $layout['id']) as $field) {
                    $fields[] = [
                        'guid'        => $field['guid'],
                        'key'         => $field['field_key'],
                        'label'       => $field['label'],
                        'type'        => $field['field_type'],
                        'help'        => $field['help'],
                        'width'       => $field['width'],
                        'config'      => $field['config'],
                        'sort_order'  => (int) $field['sort_order'],
                        'archived_at' => $field['archived_at'],
                    ];
                }

                $layoutRows[] = [
                    'guid'       => $layout['guid'],
                    'name'       => $layout['name'],
                    'is_default' => (int) $layout['is_default'] === 1,
                    'sort_order' => (int) $layout['sort_order'],
                    'fields'     => $fields,
                ];
            }

            $out[] = [
                'guid'         => $category['guid'],
                'name'         => $category['name'],
                'icon'         => $category['icon'],
                'color'        => $category['color'],
                'description'  => $category['description'],
                'default_sort' => $category['default_sort'],
                'sort_order'   => (int) $category['sort_order'],
                'parent'       => $category['parent_id'] === null
                    ? null
                    : ($guidById[(int) $category['parent_id']] ?? null),
                'layouts'      => $layoutRows,
                'entries'      => $this->entries($categoryId),
            ];
        }

        return $out;
    }

    private function entries(int $categoryId): array
    {
        // Keyed by field guid, so they land on the right field regardless of id.
        $fieldGuids = [];
        foreach ($this->db->all('SELECT id, guid FROM layout_fields') as $row) {
            $fieldGuids[(int) $row['id']] = $row['guid'];
        }

        $entryGuids = [];
        foreach ($this->db->all('SELECT id, guid FROM entries') as $row) {
            $entryGuids[(int) $row['id']] = $row['guid'];
        }

        $layoutGuids = [];
        foreach ($this->db->all('SELECT id, guid FROM layouts') as $row) {
            $layoutGuids[(int) $row['id']] = $row['guid'];
        }

        $out = [];
        foreach ($this->db->all(
            'SELECT * FROM entries WHERE category_id = :cid ORDER BY id',
            ['cid' => $categoryId]
        ) as $entry) {
            $entryId = (int) $entry['id'];

            $values = [];
            foreach ($this->db->all(
                'SELECT field_id, value_text, value_number FROM entry_values WHERE entry_id = :eid',
                ['eid' => $entryId]
            ) as $value) {
                $guid = $fieldGuids[(int) $value['field_id']] ?? null;
                if ($guid === null) {
                    continue;
                }
                $values[] = [
                    'field'  => $guid,
                    'text'   => $value['value_text'],
                    'number' => $value['value_number'] === null ? null : (float) $value['value_number'],
                ];
            }

            $links = [];
            foreach ($this->db->all(
                'SELECT field_id, target_entry_id, sort_order FROM entry_links
                  WHERE entry_id = :eid ORDER BY field_id, sort_order',
                ['eid' => $entryId]
            ) as $link) {
                $fieldGuid = $fieldGuids[(int) $link['field_id']] ?? null;
                $targetGuid = $entryGuids[(int) $link['target_entry_id']] ?? null;
                if ($fieldGuid === null || $targetGuid === null) {
                    continue;
                }
                $links[] = [
                    'field'      => $fieldGuid,
                    'target'     => $targetGuid,
                    'sort_order' => (int) $link['sort_order'],
                ];
            }

            $out[] = [
                'guid'         => $entry['guid'],
                'layout'       => $layoutGuids[(int) $entry['layout_id']] ?? null,
                'title'        => $entry['title'],
                'favorited_at' => $entry['favorited_at'] ?? null,
                'created_at'   => $entry['created_at'],
                'updated_at'   => $entry['updated_at'],
                'values'       => $values,
                'links'        => $links,
            ];
        }

        return $out;
    }

    private function chapters(): array
    {
        $out = [];
        foreach ((new ChapterRepo($this->db))->all() as $chapter) {
            $out[] = [
                'guid'       => $chapter['guid'],
                'title'      => $chapter['title'],
                'number'     => $chapter['chapter_number'] === null
                    ? null
                    : (float) $chapter['chapter_number'],
                'content'    => $chapter['content'],
                'notes'      => $chapter['notes'],
                'is_visible' => (int) $chapter['is_visible'] === 1,
                'created_at' => $chapter['created_at'],
                'updated_at' => $chapter['updated_at'],
            ];
        }

        return $out;
    }

    private function connections(): array
    {
        $guids = ['entry' => [], 'chapter' => []];
        foreach ($this->db->all('SELECT id, guid FROM entries') as $row) {
            $guids['entry'][(int) $row['id']] = $row['guid'];
        }
        foreach ($this->db->all('SELECT id, guid FROM chapters') as $row) {
            $guids['chapter'][(int) $row['id']] = $row['guid'];
        }

        $out = [];
        foreach ($this->db->all('SELECT * FROM connections ORDER BY id') as $row) {
            $a = $guids[$row['a_type']][(int) $row['a_id']] ?? null;
            $b = $guids[$row['b_type']][(int) $row['b_id']] ?? null;
            if ($a === null || $b === null) {
                continue;
            }

            $out[] = [
                'a_type' => $row['a_type'],
                'a'      => $a,
                'b_type' => $row['b_type'],
                'b'      => $b,
                'note'   => $row['note'],
            ];
        }

        return $out;
    }

    // ------------------------------------------------------------- reading

    /**
     * Reads a backup back in. Anything whose guid is already here is updated in
     * place; anything unrecognised is created, even if something else already
     * has that name.
     *
     * @param bool $dryRun report what would happen without writing
     * @return array<string, int> a tally of what was created and updated
     */
    public function import(string $json, bool $dryRun = false): array
    {
        $data = json_decode($json, true);

        if (!is_array($data) || ($data['format'] ?? null) !== self::FORMAT) {
            throw new RuntimeException(
                t('That is not a Worldbuilder backup file. Expected JSON with a "format" of "%s".', self::FORMAT)
            );
        }

        if ((int) ($data['version'] ?? 0) > self::VERSION) {
            throw new RuntimeException(
                t('That backup was written by a newer version of Worldbuilder (file version %d, this understands %d).',
                    (int) $data['version'], self::VERSION)
            );
        }

        $tally = [
            'categories_created' => 0, 'categories_updated' => 0,
            'layouts_created'    => 0, 'layouts_updated'    => 0,
            'fields_created'     => 0, 'fields_updated'     => 0,
            'entries_created'    => 0, 'entries_updated'    => 0,
            'chapters_created'   => 0, 'chapters_updated'   => 0,
            'connections_added'  => 0,
            'skipped'            => 0,
        ];

        $apply = function (Database $db) use ($data, &$tally, $dryRun): void {
            $categoryIds = $this->indexBy('categories');
            $layoutIds = $this->indexBy('layouts');
            $fieldIds = $this->indexBy('layout_fields');
            $entryIds = $this->indexBy('entries');
            $chapterIds = $this->indexBy('chapters');

            // -- archives, layouts, fields ---------------------------------
            $parentWanted = [];

            foreach (($data['categories'] ?? []) as $category) {
                if (!Guid::isValid($category['guid'] ?? null)) {
                    $tally['skipped']++;
                    continue;
                }

                $row = [
                    'name'         => (string) ($category['name'] ?? 'Untitled'),
                    'icon'         => $category['icon'] ?? null,
                    'color'        => $category['color'] ?? null,
                    'description'  => $category['description'] ?? null,
                    'default_sort' => \App\CategoryRepo::cleanSort($category['default_sort'] ?? 'title'),
                    'sort_order'   => (int) ($category['sort_order'] ?? 0),
                    'updated_at'   => now(),
                ];

                $categoryId = $this->upsert(
                    $db, 'categories', $category['guid'], $row, $categoryIds,
                    $tally, 'categories', $dryRun,
                    ['slug' => $this->freeSlug($db, 'categories', (string) $row['name'], null)]
                );

                if (!empty($category['parent'])) {
                    $parentWanted[$category['guid']] = $category['parent'];
                }

                foreach (($category['layouts'] ?? []) as $layout) {
                    if (!Guid::isValid($layout['guid'] ?? null)) {
                        $tally['skipped']++;
                        continue;
                    }

                    $layoutId = $this->upsert(
                        $db, 'layouts', $layout['guid'],
                        [
                            'category_id' => $categoryId,
                            'name'        => (string) ($layout['name'] ?? 'Untitled layout'),
                            'is_default'  => !empty($layout['is_default']) ? 1 : 0,
                            'sort_order'  => (int) ($layout['sort_order'] ?? 0),
                            'updated_at'  => now(),
                        ],
                        $layoutIds, $tally, 'layouts', $dryRun
                    );

                    foreach (($layout['fields'] ?? []) as $field) {
                        if (!Guid::isValid($field['guid'] ?? null)) {
                            $tally['skipped']++;
                            continue;
                        }

                        $this->upsert(
                            $db, 'layout_fields', $field['guid'],
                            [
                                'layout_id'   => $layoutId,
                                'field_key'   => (string) ($field['key'] ?? 'field'),
                                'label'       => (string) ($field['label'] ?? 'Field'),
                                'field_type'  => (string) ($field['type'] ?? 'text'),
                                'help'        => $field['help'] ?? null,
                                'width'       => ($field['width'] ?? 'full') === 'half' ? 'half' : 'full',
                                'config'      => json_encode($field['config'] ?? [], JSON_UNESCAPED_UNICODE),
                                'sort_order'  => (int) ($field['sort_order'] ?? 0),
                                'archived_at' => $field['archived_at'] ?? null,
                            ],
                            $fieldIds, $tally, 'fields', $dryRun
                        );
                    }
                }
            }

            // Nesting, once every archive exists.
            foreach ($parentWanted as $childGuid => $parentGuid) {
                if ($dryRun || !isset($categoryIds[$childGuid], $categoryIds[$parentGuid])) {
                    continue;
                }
                $db->update('categories', $categoryIds[$childGuid], [
                    'parent_id' => $categoryIds[$parentGuid],
                ]);
            }

            // -- entries ----------------------------------------------------
            // Two passes: every entry must exist before links between them can
            // be written.
            $pendingValues = [];

            foreach (($data['categories'] ?? []) as $category) {
                $categoryId = $categoryIds[$category['guid'] ?? ''] ?? null;

                foreach (($category['entries'] ?? []) as $entry) {
                    if (!Guid::isValid($entry['guid'] ?? null)) {
                        $tally['skipped']++;
                        continue;
                    }

                    $layoutId = $layoutIds[$entry['layout'] ?? ''] ?? null;
                    if ($categoryId === null || $layoutId === null) {
                        $tally['skipped']++;
                        continue;
                    }

                    $title = (string) ($entry['title'] ?? 'Untitled');
                    $entryId = $this->upsert(
                        $db, 'entries', $entry['guid'],
                        [
                            'category_id'  => $categoryId,
                            'layout_id'    => $layoutId,
                            'title'        => $title,
                            'favorited_at' => $entry['favorited_at'] ?? null,
                            'created_at'   => $entry['created_at'] ?? now(),
                            'updated_at'   => $entry['updated_at'] ?? now(),
                        ],
                        $entryIds, $tally, 'entries', $dryRun,
                        ['slug' => $this->freeSlug($db, 'entries', $title, $categoryId)]
                    );

                    $pendingValues[] = [$entry, $entryId];
                }
            }

            if (!$dryRun) {
                foreach ($pendingValues as [$entry, $entryId]) {
                    $db->run('DELETE FROM entry_values WHERE entry_id = :eid', ['eid' => $entryId]);
                    $db->run('DELETE FROM entry_links WHERE entry_id = :eid', ['eid' => $entryId]);

                    foreach (($entry['values'] ?? []) as $value) {
                        $fieldId = $fieldIds[$value['field'] ?? ''] ?? null;
                        if ($fieldId === null) {
                            continue;
                        }
                        $db->insert('entry_values', [
                            'entry_id'     => $entryId,
                            'field_id'     => $fieldId,
                            'value_text'   => $value['text'] ?? null,
                            'value_number' => $value['number'] ?? null,
                        ]);
                    }

                    foreach (($entry['links'] ?? []) as $link) {
                        $fieldId = $fieldIds[$link['field'] ?? ''] ?? null;
                        $targetId = $entryIds[$link['target'] ?? ''] ?? null;
                        if ($fieldId === null || $targetId === null) {
                            continue;
                        }
                        $db->insert('entry_links', [
                            'entry_id'        => $entryId,
                            'field_id'        => $fieldId,
                            'target_entry_id' => $targetId,
                            'sort_order'      => (int) ($link['sort_order'] ?? 0),
                        ]);
                    }
                }
            }

            // -- chapters ---------------------------------------------------
            foreach (($data['chapters'] ?? []) as $chapter) {
                if (!Guid::isValid($chapter['guid'] ?? null)) {
                    $tally['skipped']++;
                    continue;
                }

                $title = (string) ($chapter['title'] ?? 'Untitled');
                $this->upsert(
                    $db, 'chapters', $chapter['guid'],
                    [
                        'title'          => $title,
                        'chapter_number' => $chapter['number'] ?? null,
                        'content'        => $chapter['content'] ?? null,
                        'notes'          => $chapter['notes'] ?? null,
                        'is_visible'     => !empty($chapter['is_visible']) ? 1 : 0,
                        'created_at'     => $chapter['created_at'] ?? now(),
                        'updated_at'     => $chapter['updated_at'] ?? now(),
                    ],
                    $chapterIds, $tally, 'chapters', $dryRun,
                    ['slug' => $this->freeSlug($db, 'chapters', $title, null)]
                );
            }

            // -- settings ---------------------------------------------------
            // Restored by name, so a banner survives a rebuild.
            if (!$dryRun && $db->tableExists('settings')) {
                foreach (($data['settings'] ?? []) as $setting) {
                    $name = (string) ($setting['name'] ?? '');
                    if ($name === '') {
                        continue;
                    }

                    $existing = $db->first('SELECT id FROM settings WHERE name = :n', ['n' => $name]);
                    if ($existing === null) {
                        $db->insert('settings', [
                            'name'       => $name,
                            'value'      => $setting['value'] ?? null,
                            'updated_at' => now(),
                        ]);
                    } else {
                        $db->update('settings', (int) $existing['id'], [
                            'value'      => $setting['value'] ?? null,
                            'updated_at' => now(),
                        ]);
                    }
                }
            }

            // -- connections ------------------------------------------------
            if (!$dryRun) {
                $connections = new ConnectionRepo($db);
                foreach (($data['connections'] ?? []) as $connection) {
                    $aMap = ($connection['a_type'] ?? '') === 'chapter' ? $chapterIds : $entryIds;
                    $bMap = ($connection['b_type'] ?? '') === 'chapter' ? $chapterIds : $entryIds;

                    $a = $aMap[$connection['a'] ?? ''] ?? null;
                    $b = $bMap[$connection['b'] ?? ''] ?? null;
                    if ($a === null || $b === null) {
                        continue;
                    }

                    if ($connections->connect(
                        (string) $connection['a_type'], $a,
                        (string) $connection['b_type'], $b,
                        $connection['note'] ?? null
                    )) {
                        $tally['connections_added']++;
                    }
                }
            }
        };

        if ($dryRun) {
            // Run inside a transaction that is then discarded, so the preview is exact.
            try {
                $this->db->pdo()->beginTransaction();
                $apply($this->db);
            } finally {
                if ($this->db->pdo()->inTransaction()) {
                    $this->db->pdo()->rollBack();
                }
            }

            return $tally;
        }

        $this->db->transaction($apply);

        // Titles and slugs have just moved; a later render in this request
        // must not resolve links to where entries used to be.
        EntryLinks::forget();

        return $tally;
    }

    /** guid => id for everything currently in a table. */
    private function indexBy(string $table): array
    {
        $out = [];
        foreach ($this->db->all('SELECT id, guid FROM ' . $table . ' WHERE guid IS NOT NULL') as $row) {
            $out[$row['guid']] = (int) $row['id'];
        }

        return $out;
    }

    /**
     * Updates the row with this guid, or creates it. $extraOnCreate holds
     * columns that only matter for a new row, such as a slug.
     *
     * @param array<string, int> $index guid => id, updated in place
     */
    private function upsert(
        Database $db,
        string $table,
        string $guid,
        array $row,
        array &$index,
        array &$tally,
        string $tallyKey,
        bool $dryRun,
        array $extraOnCreate = []
    ): int {
        if (isset($index[$guid])) {
            $tally[$tallyKey . '_updated']++;
            if (!$dryRun) {
                $db->update($table, $index[$guid], $row);
            }

            return $index[$guid];
        }

        $tally[$tallyKey . '_created']++;

        $id = $db->insert($table, $row + $extraOnCreate + ['guid' => $guid]);
        $index[$guid] = $id;

        return $id;
    }

    /** A slug not already taken, for a row being created. */
    private function freeSlug(Database $db, string $table, string $title, ?int $categoryId): string
    {
        $base = substr(slugify($title, 'item'), 0, 180);
        $slug = $base;
        $suffix = 2;

        while (true) {
            $sql = 'SELECT COUNT(*) FROM ' . $table . ' WHERE slug = :slug';
            $params = ['slug' => $slug];
            if ($categoryId !== null) {
                $sql .= ' AND category_id = :cid';
                $params['cid'] = $categoryId;
            }

            if ((int) $db->value($sql, $params) === 0) {
                return $slug;
            }

            $slug = $base . '-' . $suffix++;
        }
    }
}
