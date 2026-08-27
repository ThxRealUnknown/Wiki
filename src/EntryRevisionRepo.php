<?php

namespace App;

use RuntimeException;

/**
 * Snapshots of an entry's title, values and links, taken right before an edit
 * overwrites them or a delete removes them — so either can be undone from
 * Settings → Export → Change history.
 *
 * Relation targets are stored by guid, never by id, since an id can be reused
 * by a later, unrelated entry once the original is deleted.
 */
final class EntryRevisionRepo
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::instance();
    }

    /** A comparable snapshot of one moment: this title, these values, these links. */
    public function snapshot(int $entryId, string $title): array
    {
        return [
            'title'  => $title,
            'values' => $this->snapshotValues($entryId),
            'links'  => $this->snapshotLinks($entryId),
        ];
    }

    /**
     * Writes one snapshot to history.
     *
     * @param array $entry the entry row as it stood at snapshot time (id, guid,
     *                      category_id, layout_id) — title comes from $snapshot instead
     */
    public function record(array $entry, array $snapshot, string $kind): void
    {
        $this->db->insert('entry_revisions', [
            'entry_id'    => (int) $entry['id'],
            'entry_guid'  => (string) $entry['guid'],
            'category_id' => $entry['category_id'] !== null ? (int) $entry['category_id'] : null,
            'layout_id'   => $entry['layout_id'] !== null ? (int) $entry['layout_id'] : null,
            'title'       => $snapshot['title'],
            'kind'        => $kind,
            'values_json' => json_encode($snapshot['values'], JSON_UNESCAPED_UNICODE),
            'links_json'  => json_encode($snapshot['links'], JSON_UNESCAPED_UNICODE),
            'created_at'  => now(),
        ]);
    }

    /**
     * Every revision, newest first. If the entry has since been deleted, the
     * revision's own stored category_id carries the archive name instead.
     */
    public function recent(int $limit, int $offset = 0): array
    {
        return $this->db->all(
            'SELECT r.*, e.slug AS entry_slug,
                    COALESCE(ec.slug, rc.slug) AS category_slug,
                    COALESCE(ec.name, rc.name) AS category_name
               FROM entry_revisions r
               LEFT JOIN entries e ON e.id = r.entry_id
               LEFT JOIN categories ec ON ec.id = e.category_id
               LEFT JOIN categories rc ON rc.id = r.category_id
              ORDER BY r.created_at DESC, r.id DESC
              LIMIT ' . max(1, $limit) . ' OFFSET ' . max(0, $offset)
        );
    }

    public function count(): int
    {
        return (int) $this->db->value('SELECT COUNT(*) FROM entry_revisions');
    }

    /** Wipes every recorded revision. Nothing left to restore afterward. */
    public function clear(): void
    {
        $this->db->run('DELETE FROM entry_revisions');
    }

    /**
     * What one edit actually changed: this revision's snapshot against
     * whatever came right after it — the next revision for the same entry if
     * there is one, or the entry as it stands now otherwise. A deletion has
     * nothing to compare against.
     *
     * @return array{
     *     revision: array, title_before: string, title_after: ?string,
     *     title_changed: bool, entry_gone: bool,
     *     fields: array<int, array{label: string, kind: string, old: mixed, new: mixed}>
     * }
     */
    public function diff(int $revisionId): array
    {
        $revision = $this->db->first('SELECT * FROM entry_revisions WHERE id = :id', ['id' => $revisionId]);
        if ($revision === null) {
            throw new RuntimeException(t('That version no longer exists.'));
        }

        $before = [
            'title'  => (string) $revision['title'],
            'values' => (array) (json_decode((string) $revision['values_json'], true) ?: []),
            'links'  => (array) (json_decode((string) $revision['links_json'], true) ?: []),
        ];

        $next = $this->db->first(
            'SELECT * FROM entry_revisions
              WHERE entry_guid = :g
                AND (created_at > :t OR (created_at = :t AND id > :id))
              ORDER BY created_at ASC, id ASC LIMIT 1',
            ['g' => $revision['entry_guid'], 't' => $revision['created_at'], 'id' => $revisionId]
        );

        $after = null;
        if ($next !== null) {
            $after = [
                'title'  => (string) $next['title'],
                'values' => (array) (json_decode((string) $next['values_json'], true) ?: []),
                'links'  => (array) (json_decode((string) $next['links_json'], true) ?: []),
            ];
        } elseif ($revision['kind'] === 'edit') {
            $entry = $revision['entry_id'] !== null
                ? $this->db->first('SELECT * FROM entries WHERE id = :id', ['id' => (int) $revision['entry_id']])
                : null;
            $entry ??= $this->db->first('SELECT * FROM entries WHERE guid = :g', ['g' => $revision['entry_guid']]);

            if ($entry !== null) {
                $after = $this->snapshot((int) $entry['id'], (string) $entry['title']);
            }
        }

        $fieldIds = array_values(array_unique(array_map('intval', array_merge(
            array_keys($before['values']),
            array_keys($before['links']),
            array_keys($after['values'] ?? []),
            array_keys($after['links'] ?? [])
        ))));

        $labels = [];
        if ($fieldIds !== []) {
            $placeholders = [];
            $params = [];
            foreach ($fieldIds as $i => $fid) {
                $placeholders[] = ':f' . $i;
                $params['f' . $i] = $fid;
            }
            foreach ($this->db->all(
                'SELECT id, label, field_type FROM layout_fields WHERE id IN (' . implode(', ', $placeholders) . ')',
                $params
            ) as $row) {
                $labels[(int) $row['id']] = $row;
            }
        }

        $fields = [];
        foreach ($fieldIds as $fid) {
            $meta = $labels[$fid] ?? null;
            $label = $meta['label'] ?? ('Removed field #' . $fid);
            $type = $meta['field_type'] ?? null;
            $isLink = $type !== null
                ? FieldTypes::isRelation((string) $type)
                : (array_key_exists($fid, $before['links']) || array_key_exists($fid, $after['links'] ?? []));

            if ($isLink) {
                $oldGuids = (array) ($before['links'][$fid] ?? []);
                $newGuids = (array) ($after['links'][$fid] ?? []);
                if ($oldGuids === $newGuids) {
                    continue;
                }

                $fields[] = [
                    'label' => $label,
                    'kind'  => 'links',
                    'old'   => array_map($this->describeLink(...), $oldGuids),
                    'new'   => array_map($this->describeLink(...), $newGuids),
                ];
                continue;
            }

            $oldValue = $before['values'][$fid] ?? null;
            $newValue = $after !== null ? ($after['values'][$fid] ?? null) : null;
            if ($oldValue == $newValue) { // both null, or the same array
                continue;
            }

            $fields[] = [
                'label' => $label,
                'kind'  => 'text',
                'old'   => self::displayValue($oldValue, $type),
                'new'   => self::displayValue($newValue, $type),
            ];
        }

        return [
            'revision'      => $revision,
            'title_before'  => $before['title'],
            'title_after'   => $after['title'] ?? null,
            'title_changed' => $after !== null && $before['title'] !== $after['title'],
            'entry_gone'    => $after === null,
            'fields'        => $fields,
        ];
    }

    private function titleForGuid(string $guid): string
    {
        $entry = $this->db->first('SELECT title FROM entries WHERE guid = :g', ['g' => $guid]);

        return $entry !== null ? (string) $entry['title'] : '(no longer exists)';
    }

    /** A stored link snapshot is a bare guid string, or a ['guid' => .., 'type' => ..] pair; both are accepted. */
    private function describeLink(mixed $item): string
    {
        $guid = is_array($item) ? ($item['guid'] ?? '') : $item;
        $type = is_array($item) ? ($item['type'] ?? null) : null;

        $title = $this->titleForGuid((string) $guid);

        return $type ? $title . ' (' . $type . ')' : $title;
    }

    /** @param ?array{value_text: ?string, value_number: ?float} $value */
    private static function displayValue(?array $value, ?string $fieldType): string
    {
        if ($value === null) {
            return '';
        }

        $text = $value['value_text'] ?? null;
        if ($text === null || $text === '') {
            return $value['value_number'] !== null ? rtrim(rtrim(number_format((float) $value['value_number'], 4, '.', ''), '0'), '.') : '';
        }

        if ($fieldType !== null && FieldTypes::isMultiValue($fieldType)) {
            $list = json_decode($text, true);

            return is_array($list) ? implode(', ', array_map('strval', $list)) : $text;
        }

        if ($fieldType !== null && FieldTypes::isEra($fieldType)) {
            $era = json_decode($text, true);

            return is_array($era) ? trim((string) ($era['from'] ?? '') . ' – ' . (string) ($era['to'] ?? ''), ' –') : $text;
        }

        return Sanitizer::excerpt($text, 300);
    }

    /**
     * Brings back exactly what a revision recorded — restoring the entry in
     * place if it still exists, or re-creating it if this is undoing a delete.
     * What was live a moment ago becomes its own new revision first, so a
     * restore is itself always undoable.
     *
     * @return array{title: string, recreated: bool}
     */
    public function restore(int $revisionId): array
    {
        $revision = $this->db->first('SELECT * FROM entry_revisions WHERE id = :id', ['id' => $revisionId]);
        if ($revision === null) {
            throw new RuntimeException(t('That version no longer exists.'));
        }

        return $this->db->transaction(function (Database $db) use ($revision): array {
            $entryRepo = new EntryRepo($db);

            $entry = $revision['entry_id'] !== null ? $entryRepo->find((int) $revision['entry_id']) : null;
            if ($entry === null) {
                // Might already have been resurrected by an earlier restore.
                $entry = $db->first(
                    'SELECT * FROM entries WHERE guid = :g',
                    ['g' => $revision['entry_guid']]
                );
            }

            $wasDeleted = $entry === null;

            if ($wasDeleted) {
                $entry = $this->recreate($db, $entryRepo, $revision);
            } else {
                // What's live right now, saved as its own restorable version.
                $snapshot = $this->snapshot((int) $entry['id'], (string) $entry['title']);
                $this->record($entry, $snapshot, 'edit');
            }

            $this->applySnapshot($db, $entry, $revision);

            $newTitle = mb_substr((string) $revision['title'], 0, 250);
            $data = ['title' => $newTitle, 'updated_at' => now()];
            if ($newTitle !== (string) $entry['title']) {
                $data['slug'] = $entryRepo->uniqueSlug($newTitle, (int) $entry['category_id'], (int) $entry['id']);
            }
            $db->update('entries', (int) $entry['id'], $data);

            return ['title' => $newTitle, 'recreated' => $wasDeleted];
        });
    }

    /** Re-creates a deleted entry's row so its history can be restored onto it. */
    private function recreate(Database $db, EntryRepo $entryRepo, array $revision): array
    {
        $categoryId = $revision['category_id'] !== null ? (int) $revision['category_id'] : null;
        $layoutId = $revision['layout_id'] !== null ? (int) $revision['layout_id'] : null;

        if ($categoryId === null || $layoutId === null
            || $db->first('SELECT id FROM categories WHERE id = :id', ['id' => $categoryId]) === null
            || $db->first('SELECT id FROM layouts WHERE id = :id', ['id' => $layoutId]) === null
        ) {
            throw new RuntimeException(t('Its archive or layout no longer exists, so this version cannot be restored.'));
        }

        $title = (string) $revision['title'];
        $newId = $db->insert('entries', [
            'guid'        => $revision['entry_guid'],
            'category_id' => $categoryId,
            'layout_id'   => $layoutId,
            'title'       => $title,
            'slug'        => $entryRepo->uniqueSlug($title, $categoryId),
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        // History continues under the resurrected row rather than the old, gone id.
        $db->run(
            'UPDATE entry_revisions SET entry_id = :new WHERE entry_guid = :g',
            ['new' => $newId, 'g' => $revision['entry_guid']]
        );

        return $entryRepo->find($newId);
    }

    /** Replaces an entry's values/links with what a revision recorded. */
    private function applySnapshot(Database $db, array $entry, array $revision): void
    {
        $entryId = (int) $entry['id'];

        $db->run('DELETE FROM entry_values WHERE entry_id = :eid', ['eid' => $entryId]);
        $db->run('DELETE FROM entry_links WHERE entry_id = :eid', ['eid' => $entryId]);

        $liveFieldIds = array_map(
            'intval',
            array_column(
                $db->all('SELECT id FROM layout_fields WHERE layout_id = :lid', ['lid' => (int) $entry['layout_id']]),
                'id'
            )
        );

        $values = json_decode((string) $revision['values_json'], true) ?: [];
        foreach ($values as $fieldId => $value) {
            $fieldId = (int) $fieldId;
            if (!in_array($fieldId, $liveFieldIds, true)) {
                continue; // that field doesn't exist on this layout any more
            }

            $text = $value['value_text'] ?? null;
            $number = $value['value_number'] ?? null;
            if ($text === null && $number === null) {
                continue;
            }

            $db->insert('entry_values', [
                'entry_id'     => $entryId,
                'field_id'     => $fieldId,
                'value_text'   => $text,
                'value_number' => $number,
            ]);
        }

        $links = json_decode((string) $revision['links_json'], true) ?: [];
        foreach ($links as $fieldId => $targetGuids) {
            $fieldId = (int) $fieldId;
            if (!in_array($fieldId, $liveFieldIds, true)) {
                continue;
            }

            $sort = 0;
            foreach ((array) $targetGuids as $item) {
                // Older revisions hold a bare guid string; newer ones hold ['guid' => .., 'type' => ..].
                $guid = is_array($item) ? ($item['guid'] ?? null) : $item;
                $relationType = is_array($item) ? ($item['type'] ?? null) : null;
                if ($guid === null) {
                    continue;
                }

                $target = $db->first('SELECT id FROM entries WHERE guid = :g', ['g' => $guid]);
                if ($target === null) {
                    continue; // that entry no longer exists to link to
                }

                $db->insert('entry_links', [
                    'entry_id'        => $entryId,
                    'field_id'        => $fieldId,
                    'target_entry_id' => (int) $target['id'],
                    'sort_order'      => $sort++,
                    'relation_type'   => $relationType,
                ]);
            }
        }
    }

    private function snapshotValues(int $entryId): array
    {
        $out = [];
        foreach ($this->db->all(
            'SELECT field_id, value_text, value_number FROM entry_values WHERE entry_id = :eid',
            ['eid' => $entryId]
        ) as $row) {
            $out[(int) $row['field_id']] = [
                'value_text'   => $row['value_text'],
                'value_number' => $row['value_number'] === null ? null : (float) $row['value_number'],
            ];
        }

        return $out;
    }

    private function snapshotLinks(int $entryId): array
    {
        $out = [];
        foreach ($this->db->all(
            'SELECT k.field_id, k.relation_type, e.guid
               FROM entry_links k
               JOIN entries e ON e.id = k.target_entry_id
              WHERE k.entry_id = :eid
              ORDER BY k.field_id ASC, k.sort_order ASC',
            ['eid' => $entryId]
        ) as $row) {
            $out[(int) $row['field_id']][] = ['guid' => $row['guid'], 'type' => $row['relation_type']];
        }

        return $out;
    }
}
