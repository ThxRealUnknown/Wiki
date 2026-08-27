<?php

namespace App;

/**
 * A layout *is* the schema for the entries that use it: editing its field list
 * changes what every one of those entries has, both in the form and on the
 * page. Layouts belong to exactly one category.
 */
final class LayoutRepo
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::instance();
    }

    public function forCategory(int $categoryId): array
    {
        return $this->db->all(
            'SELECT l.*, (SELECT COUNT(*) FROM entries e WHERE e.layout_id = l.id) AS entry_count
               FROM layouts l
              WHERE l.category_id = :cid
              ORDER BY l.sort_order ASC, l.name ASC',
            ['cid' => $categoryId]
        );
    }

    public function find(int $id): ?array
    {
        return $this->db->first('SELECT * FROM layouts WHERE id = :id', ['id' => $id]);
    }

    public function defaultForCategory(int $categoryId): ?array
    {
        return $this->db->first(
            'SELECT * FROM layouts
              WHERE category_id = :cid
              ORDER BY is_default DESC, sort_order ASC, id ASC',
            ['cid' => $categoryId]
        );
    }

    /**
     * The layout's live fields — what forms and entry pages are built from.
     * Archived fields are excluded, but their stored values still exist.
     *
     * @return array<int, array<string, mixed>> ordered field definitions
     */
    public function fields(int $layoutId): array
    {
        return $this->decorate($this->db->all(
            'SELECT * FROM layout_fields
              WHERE layout_id = :lid AND archived_at IS NULL
              ORDER BY sort_order ASC, id ASC',
            ['lid' => $layoutId]
        ));
    }

    /** Fields taken out of the layout but not destroyed. */
    public function archivedFields(int $layoutId): array
    {
        return $this->decorate($this->db->all(
            'SELECT * FROM layout_fields
              WHERE layout_id = :lid AND archived_at IS NOT NULL
              ORDER BY archived_at DESC, id ASC',
            ['lid' => $layoutId]
        ));
    }

    /** Live and archived together, for exports and for the fields admin. */
    public function allFields(int $layoutId): array
    {
        return $this->decorate($this->db->all(
            'SELECT * FROM layout_fields WHERE layout_id = :lid ORDER BY sort_order ASC, id ASC',
            ['lid' => $layoutId]
        ));
    }

    public function findField(int $fieldId): ?array
    {
        $row = $this->db->first('SELECT * FROM layout_fields WHERE id = :id', ['id' => $fieldId]);
        if ($row === null) {
            return null;
        }

        $row['config'] = self::decodeConfig($row['config'] ?? null);

        return $row;
    }

    /**
     * How many stored values and links would be lost if a field were destroyed.
     */
    public function fieldContentCount(int $fieldId): int
    {
        return (int) $this->db->value(
            'SELECT COUNT(*) FROM entry_values WHERE field_id = :a',
            ['a' => $fieldId]
        ) + (int) $this->db->value(
            'SELECT COUNT(*) FROM entry_links WHERE field_id = :b',
            ['b' => $fieldId]
        );
    }

    /** Takes a field out of the layout, keeping every value stored against it. */
    public function archiveField(int $fieldId): void
    {
        $this->db->update('layout_fields', $fieldId, ['archived_at' => now()]);
    }

    /** Puts an archived field back, with its content intact. */
    public function restoreField(int $fieldId): void
    {
        $field = $this->db->first('SELECT * FROM layout_fields WHERE id = :id', ['id' => $fieldId]);
        if ($field === null) {
            return;
        }

        $end = (int) $this->db->value(
            'SELECT COALESCE(MAX(sort_order), -1) + 1 FROM layout_fields
              WHERE layout_id = :lid AND archived_at IS NULL',
            ['lid' => $field['layout_id']]
        );

        $this->db->update('layout_fields', $fieldId, [
            'archived_at' => null,
            'sort_order'  => $end,
        ]);
    }

    /** Destroys a field and everything entries stored in it. Only for a field already archived. */
    public function destroyField(int $fieldId): void
    {
        $this->db->delete('layout_fields', $fieldId);
    }

    /**
     * The choice fields in an archive ticked as sortable, ready for the entry
     * list's sort menu. Fields with the same label across layouts are merged
     * into one menu entry, options combined in the order first written.
     *
     * @return array<int, array{key:string, label:string, ids:array<int,int>, options:array<int,string>}>
     */
    public function sortableChoiceFields(int $categoryId): array
    {
        $rows = $this->decorate($this->db->all(
            'SELECT f.* FROM layout_fields f
               JOIN layouts l ON l.id = f.layout_id
              WHERE l.category_id = :cid
                AND f.field_type = :t
                AND f.archived_at IS NULL
              ORDER BY l.sort_order ASC, l.id ASC, f.sort_order ASC, f.id ASC',
            ['cid' => $categoryId, 't' => FieldTypes::SELECT]
        ));

        $groups = [];

        foreach ($rows as $field) {
            if (empty($field['config']['sortable'])) {
                continue;
            }

            $options = array_values(array_filter(
                array_map('strval', (array) ($field['config']['options'] ?? []))
            ));
            if ($options === []) {
                continue;                      // nothing to sort into
            }

            $key = mb_strtolower(trim((string) $field['label']));

            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'key'     => 'field:' . (int) $field['id'],
                    'label'   => (string) $field['label'],
                    'ids'     => [],
                    'options' => [],
                ];
            }

            $groups[$key]['ids'][] = (int) $field['id'];

            foreach ($options as $option) {
                if (!in_array($option, $groups[$key]['options'], true)) {
                    $groups[$key]['options'][] = $option;
                }
            }
        }

        return array_values($groups);
    }

    /**
     * The sortable group a 'field:N' sort key names, or null if that field is
     * gone or is no longer marked sortable.
     *
     * @return array{key:string, label:string, ids:array<int,int>, options:array<int,string>}|null
     */
    public function sortableChoiceField(int $categoryId, string $sortKey): ?array
    {
        if (!str_starts_with($sortKey, 'field:')) {
            return null;
        }

        $wanted = (int) substr($sortKey, 6);

        foreach ($this->sortableChoiceFields($categoryId) as $group) {
            // Matching on the whole group, not just its first field, so a saved
            // sort survives a sibling layout being edited or reordered.
            if (in_array($wanted, $group['ids'], true)) {
                return $group;
            }
        }

        return null;
    }

    /** @param array<int, array|null> $rows */
    private function decorate(array $rows): array
    {
        foreach ($rows as $index => $row) {
            if ($row === null) {
                unset($rows[$index]);
                continue;
            }
            $rows[$index]['config'] = self::decodeConfig($row['config'] ?? null);
        }

        return array_values($rows);
    }

    public static function decodeConfig(?string $json): array
    {
        if ($json === null || $json === '') {
            return [];
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }

    public function create(int $categoryId, string $name, bool $isDefault = false): int
    {
        $name = trim($name) !== '' ? trim($name) : 'Untitled layout';

        $sortOrder = (int) $this->db->value(
            'SELECT COALESCE(MAX(sort_order), -1) + 1 FROM layouts WHERE category_id = :cid',
            ['cid' => $categoryId]
        );

        $hasAny = (int) $this->db->value(
            'SELECT COUNT(*) FROM layouts WHERE category_id = :cid',
            ['cid' => $categoryId]
        ) > 0;

        $id = $this->db->insert('layouts', [
            'category_id' => $categoryId,
            'name'        => mb_substr($name, 0, 120),
            // The first layout in a category is always the default, otherwise
            // new entries would have nothing to be created from.
            'is_default'  => ($isDefault || !$hasAny) ? 1 : 0,
            'sort_order'  => $sortOrder,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        if ($isDefault) {
            $this->makeDefault($id);
        }

        return $id;
    }

    public function rename(int $id, string $name): void
    {
        $name = trim($name);
        if ($name === '') {
            return;
        }

        $this->db->update('layouts', $id, [
            'name'       => mb_substr($name, 0, 120),
            'updated_at' => now(),
        ]);
    }

    public function makeDefault(int $id): void
    {
        $layout = $this->find($id);
        if ($layout === null) {
            return;
        }

        $this->db->transaction(function (Database $db) use ($layout, $id): void {
            $db->run(
                'UPDATE layouts SET is_default = 0 WHERE category_id = :cid',
                ['cid' => $layout['category_id']]
            );
            $db->update('layouts', $id, ['is_default' => 1, 'updated_at' => now()]);
        });
    }

    public function entryCount(int $layoutId): int
    {
        return (int) $this->db->value(
            'SELECT COUNT(*) FROM entries WHERE layout_id = :lid',
            ['lid' => $layoutId]
        );
    }

    /**
     * Refuses to delete a layout that entries still point at — the entries would
     * lose their whole structure. The caller surfaces the false as a message.
     */
    public function delete(int $id): bool
    {
        if ($this->entryCount($id) > 0) {
            return false;
        }

        $layout = $this->find($id);
        if ($layout === null) {
            return false;
        }

        $wasDefault = (int) $layout['is_default'] === 1;
        $this->db->delete('layouts', $id);

        if ($wasDefault) {
            $replacement = $this->defaultForCategory((int) $layout['category_id']);
            if ($replacement !== null) {
                $this->makeDefault((int) $replacement['id']);
            }
        }

        return true;
    }

    public function duplicate(int $id, ?string $newName = null, ?int $targetCategoryId = null): ?int
    {
        $layout = $this->find($id);
        if ($layout === null) {
            return null;
        }

        $categoryId = $targetCategoryId ?? (int) $layout['category_id'];

        return $this->db->transaction(function (Database $db) use ($layout, $newName, $categoryId): int {
            $copyId = $this->create(
                $categoryId,
                $newName ?: $layout['name'] . ' (copy)'
            );

            foreach ($this->fields((int) $layout['id']) as $field) {
                $db->insert('layout_fields', [
                    'layout_id'  => $copyId,
                    'field_key'  => $field['field_key'],
                    'label'      => $field['label'],
                    'field_type' => $field['field_type'],
                    'help'       => $field['help'],
                    'width'      => $field['width'],
                    'config'     => json_encode($field['config']),
                    'sort_order' => $field['sort_order'],
                    'created_at' => now(),
                ]);
            }

            return $copyId;
        });
    }

    /**
     * A layout already in $categoryId with exactly $layoutId's fields — same
     * label and type each, order and everything else ignored. Null if none
     * qualifies, meaning a moved/copied entry needs a fresh one built for it.
     */
    public function findMatching(int $categoryId, int $layoutId): ?array
    {
        $wanted = $this->signature($layoutId);

        foreach ($this->forCategory($categoryId) as $candidate) {
            if ($this->signature((int) $candidate['id']) === $wanted) {
                return $candidate;
            }
        }

        return null;
    }

    /** @return array<int, string> sorted "label|type" pairs describing a layout's shape. */
    private function signature(int $layoutId): array
    {
        $pairs = array_map(
            static fn (array $f) => $f['label'] . '|' . $f['field_type'],
            $this->fields($layoutId)
        );
        sort($pairs);

        return $pairs;
    }

    /**
     * Applies the layout editor's posted field list. Fields absent from the post
     * are removed (taking their stored values with them), fields with an id are
     * updated in place so existing entry data survives, and the rest are new.
     *
     * @param array<int, array<string, mixed>> $posted raw rows from the form
     */
    public function saveFields(int $layoutId, array $posted): void
    {
        $this->db->transaction(function (Database $db) use ($layoutId, $posted): void {
            // Only live fields — the editor never posts archived ones.
            $existing = [];
            foreach ($db->all(
                'SELECT id, field_key, field_type FROM layout_fields
                  WHERE layout_id = :lid AND archived_at IS NULL',
                ['lid' => $layoutId]
            ) as $row) {
                $existing[(int) $row['id']] = $row;
            }

            $keptIds = [];
            // Includes archived fields' keys, so a new field can't collide with them.
            $usedKeys = array_column(
                $db->all(
                    'SELECT field_key FROM layout_fields WHERE layout_id = :lid',
                    ['lid' => $layoutId]
                ),
                'field_key'
            );
            $sortOrder = 0;

            foreach ($posted as $row) {
                $label = trim((string) ($row['label'] ?? ''));
                if ($label === '') {
                    continue; // A row the user added but never filled in.
                }

                $type = (string) ($row['field_type'] ?? FieldTypes::TEXT);
                if (!FieldTypes::exists($type)) {
                    $type = FieldTypes::TEXT;
                }

                $rawConfig = is_array($row['config'] ?? null) ? $row['config'] : [];
                $config = FieldTypes::normaliseConfig($type, $rawConfig);

                $width = ($row['width'] ?? 'full') === 'half' ? 'half' : 'full';
                $help = trim((string) ($row['help'] ?? ''));

                $data = [
                    'label'      => mb_substr($label, 0, 160),
                    'field_type' => $type,
                    'help'       => $help === '' ? null : mb_substr($help, 0, 300),
                    'width'      => $width,
                    'config'     => json_encode($config, JSON_UNESCAPED_UNICODE),
                    'sort_order' => $sortOrder++,
                ];

                $id = (int) ($row['id'] ?? 0);

                if ($id > 0 && isset($existing[$id])) {
                    $keptIds[] = $id;

                    // Switching between relation and non-relation moves the data
                    // to a different table, so the old values are dropped.
                    $oldType = $existing[$id]['field_type'];
                    if (FieldTypes::isRelation($oldType) !== FieldTypes::isRelation($type)) {
                        $db->run('DELETE FROM entry_values WHERE field_id = :fid', ['fid' => $id]);
                        $db->run('DELETE FROM entry_links WHERE field_id = :fid', ['fid' => $id]);
                    }

                    $db->update('layout_fields', $id, $data);
                    continue;
                }

                $key = $this->uniqueKey($label, $usedKeys);
                $usedKeys[] = $key;

                $data['layout_id'] = $layoutId;
                $data['field_key'] = $key;
                $data['created_at'] = now();
                $keptIds[] = $db->insert('layout_fields', $data);
            }

            // Archived, never deleted, so the content survives and can be restored.
            foreach (array_keys($existing) as $existingId) {
                if (!in_array($existingId, $keptIds, true)) {
                    $db->update('layout_fields', $existingId, ['archived_at' => now()]);
                }
            }

            $db->update('layouts', $layoutId, ['updated_at' => now()]);
        });
    }

    /**
     * @param array<int, string> $taken keys already used in this save pass
     */
    private function uniqueKey(string $label, array $taken): string
    {
        $base = slugify($label, 'field');
        $base = str_replace('-', '_', $base);
        $base = substr($base, 0, 70);

        $key = $base;
        $suffix = 2;
        while (in_array($key, $taken, true)) {
            $key = $base . '_' . $suffix++;
        }

        return $key;
    }
}
