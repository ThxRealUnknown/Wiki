<?php

namespace App;

/**
 * Creates archives and their starting layouts from plain definition arrays (see
 * bin/reference_archives.php). An archive that already exists is left exactly as
 * it is — never re-shaped, so edits made in the app survive.
 */
final class ArchiveInstaller
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::instance();
    }

    /**
     * @param array<int, array<string, mixed>> $defs
     * @param callable|null $log receives one line per archive created
     * @return array<string, int> every archive name => id, existing ones included
     */
    public function ensure(array $defs, ?callable $log = null): array
    {
        $ids = [];
        foreach ((new CategoryRepo($this->db))->all() as $existing) {
            $ids[$existing['name']] = (int) $existing['id'];
        }

        $created = [];
        foreach ($defs as $def) {
            if (isset($ids[$def['name']])) {
                continue;
            }

            $ids[$def['name']] = $this->db->insert('categories', [
                'name'         => $def['name'],
                'slug'         => slugify($def['name']),
                'icon'         => $def['icon'],
                'color'        => $def['color'],
                'description'  => $def['description'] ?? null,
                'default_sort' => CategoryRepo::cleanSort($def['default_sort'] ?? 'title'),
                'sort_order'   => (int) $this->db->value(
                    'SELECT COALESCE(MAX(sort_order), -1) + 1 FROM categories'
                ),
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);

            $created[] = $def;
            if ($log !== null) {
                $log("creating archive {$def['icon']}  {$def['name']}");
            }
        }

        // Layouts in a second pass, so a relation field can target an archive
        // defined further down the file.
        foreach ($created as $def) {
            $this->buildLayout($ids[$def['name']], $def['layout'], $ids);
        }

        return $ids;
    }

    /**
     * @param array<string, int> $categoryIds for resolving relation targets
     */
    private function buildLayout(int $categoryId, array $layoutDef, array $categoryIds): void
    {
        $layoutId = $this->db->insert('layouts', [
            'category_id' => $categoryId,
            'name'        => $layoutDef['name'],
            'is_default'  => 1,
            'sort_order'  => 0,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $sortOrder = 0;
        foreach ($layoutDef['fields'] as $field) {
            $type = $field['type'];
            $rawConfig = $field['config'] ?? [];

            // 'target' names one archive or several; either way it becomes the
            // list of archives the relation may point into.
            if (isset($rawConfig['target'])) {
                $wanted = (array) $rawConfig['target'];
                $rawConfig['target_category_ids'] = array_values(array_filter(
                    array_map(static fn ($name) => $categoryIds[$name] ?? null, $wanted)
                ));
                unset($rawConfig['target']);
            }

            $this->db->insert('layout_fields', [
                'layout_id'  => $layoutId,
                'field_key'  => str_replace('-', '_', slugify($field['label'], 'field')),
                'label'      => $field['label'],
                'field_type' => $type,
                'help'       => $field['help'] ?? null,
                'width'      => $field['width'] ?? 'full',
                'config'     => json_encode(
                    FieldTypes::normaliseConfig($type, $rawConfig),
                    JSON_UNESCAPED_UNICODE
                ),
                'sort_order' => $sortOrder++,
            ]);
        }
    }
}
