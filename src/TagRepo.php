<?php

namespace App;

/**
 * Tags across every tags field.
 *
 * A tag is not a row anywhere — it is a string inside the JSON list a tags
 * field stores in entry_values, and optionally a name in that field's
 * predefined options. This class handles the one thing that can't be done
 * entry by entry: viewing the whole vocabulary at once, and removing a tag
 * everywhere.
 */
final class TagRepo
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::instance();
    }

    /**
     * Every tag in use, merged across fields, case-insensitively.
     *
     * @return array<int, array{
     *     label: string, entries: int, variants: array<int,string>,
     *     fields: array<int,string>, unused: bool
     * }>
     */
    public function all(): array
    {
        $tags = [];

        /** Records one sighting under a case-insensitive key. */
        $note = static function (
            array &$tags,
            string $tag,
            string $where,
            bool $counts
        ): void {
            $tag = trim($tag);
            if ($tag === '') {
                return;
            }

            $key = mb_strtolower($tag);
            $tags[$key] ??= [
                'label' => $tag, 'entries' => 0, 'variants' => [], 'fields' => [], 'unused' => true,
            ];

            if ($counts) {
                $tags[$key]['entries']++;
                $tags[$key]['unused'] = false;
            }
            if (!in_array($tag, $tags[$key]['variants'], true)) {
                $tags[$key]['variants'][] = $tag;
            }
            if (!in_array($where, $tags[$key]['fields'], true)) {
                $tags[$key]['fields'][] = $where;
            }
        };

        // What entries actually carry.
        foreach ($this->db->all(
            "SELECT v.value_text, f.label AS field_label, c.name AS category_name
               FROM entry_values v
               JOIN layout_fields f ON f.id = v.field_id
               JOIN layouts l ON l.id = f.layout_id
               JOIN categories c ON c.id = l.category_id
              WHERE f.field_type = 'tags' AND v.value_text IS NOT NULL"
        ) as $row) {
            $where = $row['category_name'] . ' · ' . $row['field_label'];
            foreach ((array) (json_decode((string) $row['value_text'], true) ?: []) as $tag) {
                $note($tags, (string) $tag, $where, true);
            }
        }

        // Plus anything a layout offers but no entry has taken up.
        foreach ($this->db->all(
            "SELECT f.config, f.label AS field_label, c.name AS category_name
               FROM layout_fields f
               JOIN layouts l ON l.id = f.layout_id
               JOIN categories c ON c.id = l.category_id
              WHERE f.field_type = 'tags'"
        ) as $row) {
            $where = $row['category_name'] . ' · ' . $row['field_label'];
            $options = LayoutRepo::decodeConfig($row['config'])['options'] ?? [];
            foreach ((array) $options as $tag) {
                $note($tags, (string) $tag, $where, false);
            }
        }

        uasort($tags, static fn (array $x, array $y): int =>
            [-$x['entries'], mb_strtolower($x['label'])]
            <=> [-$y['entries'], mb_strtolower($y['label'])]);

        return array_values($tags);
    }

    /** Ids of every entry carrying this tag, matched the same case-insensitive way as the rest of this class. */
    public function entryIdsWith(string $tag): array
    {
        $needle = mb_strtolower(trim($tag));
        if ($needle === '') {
            return [];
        }

        $ids = [];
        foreach ($this->db->all(
            "SELECT v.entry_id, v.value_text
               FROM entry_values v
               JOIN layout_fields f ON f.id = v.field_id
              WHERE f.field_type = 'tags' AND v.value_text IS NOT NULL"
        ) as $row) {
            foreach ((array) (json_decode((string) $row['value_text'], true) ?: []) as $existing) {
                if (mb_strtolower(trim((string) $existing)) === $needle) {
                    $ids[] = (int) $row['entry_id'];
                    break;
                }
            }
        }

        return array_values(array_unique($ids));
    }

    /** How many entries would be touched by removing this tag. */
    public function countFor(string $tag): int
    {
        $needle = mb_strtolower(trim($tag));
        $count = 0;

        foreach ($this->tagValueRows() as $row) {
            foreach ((array) (json_decode((string) $row['value_text'], true) ?: []) as $existing) {
                if (mb_strtolower(trim((string) $existing)) === $needle) {
                    $count++;
                    break;
                }
            }
        }

        return $count;
    }

    /**
     * Removes a tag from every entry that carries it, and from any layout that
     * offers it. Matching ignores case, so "Magus" and "magus" go together.
     *
     * @return array{entries: int, layouts: int}
     */
    public function delete(string $tag): array
    {
        $needle = mb_strtolower(trim($tag));
        if ($needle === '') {
            return ['entries' => 0, 'layouts' => 0];
        }

        return $this->db->transaction(function (Database $db) use ($needle): array {
            $touchedEntries = 0;
            $touchedLayouts = 0;

            foreach ($this->tagValueRows() as $row) {
                $tags = (array) (json_decode((string) $row['value_text'], true) ?: []);

                $kept = array_values(array_filter(
                    $tags,
                    static fn ($t) => mb_strtolower(trim((string) $t)) !== $needle
                ));

                if (count($kept) === count($tags)) {
                    continue;
                }

                $touchedEntries++;

                // An emptied tags field loses its row entirely.
                if ($kept === []) {
                    $db->delete('entry_values', (int) $row['id']);
                    continue;
                }

                $db->update('entry_values', (int) $row['id'], [
                    'value_text' => json_encode($kept, JSON_UNESCAPED_UNICODE),
                ]);
            }

            foreach ($db->all(
                "SELECT id, config FROM layout_fields WHERE field_type = 'tags'"
            ) as $field) {
                $config = LayoutRepo::decodeConfig($field['config']);
                $options = (array) ($config['options'] ?? []);

                $kept = array_values(array_filter(
                    $options,
                    static fn ($t) => mb_strtolower(trim((string) $t)) !== $needle
                ));

                if (count($kept) === count($options)) {
                    continue;
                }

                $config['options'] = $kept;
                $db->update('layout_fields', (int) $field['id'], [
                    'config' => json_encode($config, JSON_UNESCAPED_UNICODE),
                ]);
                $touchedLayouts++;
            }

            return ['entries' => $touchedEntries, 'layouts' => $touchedLayouts];
        });
    }

    /** @return array<int, array<string, mixed>> */
    private function tagValueRows(): array
    {
        return $this->db->all(
            "SELECT v.id, v.value_text
               FROM entry_values v
               JOIN layout_fields f ON f.id = v.field_id
              WHERE f.field_type = 'tags' AND v.value_text IS NOT NULL"
        );
    }
}
