<?php

namespace App;

use RuntimeException;

/**
 * Entries and their field values. Scalar values live in entry_values; relation
 * fields live in entry_links, which is what makes backlinks a single query.
 */
final class EntryRepo
{
    private Database $db;

    /** What the last layout change carried across, and what it could not. */
    private ?array $lastCarry = null;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::instance();
    }

    public function find(int $id): ?array
    {
        return $this->db->first('SELECT * FROM entries WHERE id = :id', ['id' => $id]);
    }

    public function findInCategory(int $categoryId, string $slug): ?array
    {
        return $this->db->first(
            'SELECT * FROM entries WHERE category_id = :cid AND slug = :slug',
            ['cid' => $categoryId, 'slug' => $slug]
        );
    }

    /** Entries by id, hydrated with their archive. Archived entries are excluded. */
    public function findManyWithCategory(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn ($id) => $id > 0)));
        if ($ids === []) {
            return [];
        }

        $placeholders = [];
        $params = [];
        foreach ($ids as $index => $id) {
            $placeholders[] = ':i' . $index;
            $params['i' . $index] = $id;
        }

        return $this->db->all(
            'SELECT e.*, c.name AS category_name, c.slug AS category_slug,
                    c.icon AS category_icon, c.color AS category_color
               FROM entries e
               JOIN categories c ON c.id = e.category_id
              WHERE e.id IN (' . implode(', ', $placeholders) . ') AND e.archived_at IS NULL
              ORDER BY e.title ASC',
            $params
        );
    }

    /** Page sizes the entry list offers. */
    public const PAGE_SIZES = [10, 25, 50, 100];

    public const PAGE_SIZE_DEFAULT = 25;

    public static function cleanPerPage(mixed $value): int
    {
        $value = (int) $value;

        return in_array($value, self::PAGE_SIZES, true) ? $value : self::PAGE_SIZE_DEFAULT;
    }

    /**
     * @param string $sort one of 'title', 'recent', 'created', 'timeline'
     * @param int|null $perPage null returns every match; otherwise one page
     * @return array{items: array, total: int, page: int, pages: int, per_page: int}
     */
    public function pageForCategory(
        int $categoryId,
        string $search = '',
        ?int $layoutId = null,
        string $sort = 'title',
        ?int $perPage = null,
        int $page = 1
    ): array {
        $all = $this->forCategory($categoryId, $search, $layoutId, $sort);
        $total = count($all);

        if ($perPage === null) {
            return ['items' => $all, 'total' => $total, 'page' => 1, 'pages' => 1, 'per_page' => $total];
        }

        $perPage = self::cleanPerPage($perPage);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($page, $pages));

        return [
            'items'    => array_slice($all, ($page - 1) * $perPage, $perPage),
            'total'    => $total,
            'page'     => $page,
            'pages'    => $pages,
            'per_page' => $perPage,
        ];
    }

    /**
     * @param string $sort one of 'title', 'recent', 'created'
     */
    public function forCategory(
        int $categoryId,
        string $search = '',
        ?int $layoutId = null,
        string $sort = 'title'
    ): array {
        $sql = 'SELECT e.*, l.name AS layout_name
                  FROM entries e
                  JOIN layouts l ON l.id = e.layout_id
                 WHERE e.category_id = :cid AND e.archived_at IS NULL';
        $params = ['cid' => $categoryId];

        if ($search !== '') {
            // Matches the title, or any stored text value on the entry.
            $sql .= ' AND (LOWER(e.title) LIKE :needle
                        OR EXISTS (SELECT 1 FROM entry_values v
                                    WHERE v.entry_id = e.id
                                      AND LOWER(v.value_text) LIKE :needle2))';
            $needle = '%' . mb_strtolower($search) . '%';
            $params['needle'] = $needle;
            $params['needle2'] = $needle;
        }

        if ($layoutId !== null) {
            $sql .= ' AND e.layout_id = :lid';
            $params['lid'] = $layoutId;
        }

        // A choice field's order isn't expressible in ORDER BY, so rows come
        // back title-sorted and are grouped by option afterwards.
        $byChoice = str_starts_with($sort, 'field:');

        $sql .= self::orderClause($byChoice ? 'title' : $sort);

        $rows = $this->db->all($sql, $params);

        return $byChoice ? $this->sortByChoice($rows, $categoryId, $sort) : $rows;
    }

    /**
     * Orders entries by a sortable choice field: options in layout order (not
     * alphabetical), unset values last, titles order within each group.
     */
    private function sortByChoice(array $rows, int $categoryId, string $sortKey): array
    {
        $group = (new LayoutRepo($this->db))->sortableChoiceField($categoryId, $sortKey);

        if ($group === null || $rows === []) {
            return $rows;                       // already sorted by title
        }

        $rank = [];
        foreach ($group['options'] as $position => $option) {
            $rank[mb_strtolower(trim($option))] = $position;
        }
        $unset = count($group['options']);      // no value, or a value since removed

        $placeholders = [];
        $params = [];
        foreach ($group['ids'] as $index => $fieldId) {
            $placeholders[] = ':f' . $index;
            $params['f' . $index] = $fieldId;
        }

        $chosen = [];
        foreach ($this->db->all(
            'SELECT entry_id, value_text FROM entry_values
              WHERE field_id IN (' . implode(', ', $placeholders) . ')',
            $params
        ) as $value) {
            $key = mb_strtolower(trim((string) $value['value_text']));
            $chosen[(int) $value['entry_id']] = $rank[$key] ?? $unset;
        }

        usort($rows, static function (array $a, array $b) use ($chosen, $unset): int {
            $left  = $chosen[(int) $a['id']] ?? $unset;
            $right = $chosen[(int) $b['id']] ?? $unset;

            return $left <=> $right
                ?: mb_strtolower((string) $a['title']) <=> mb_strtolower((string) $b['title']);
        });

        return $rows;
    }

    /**
     * 'timeline' orders by whatever number, Date, or Era field the layout
     * carries, putting undated entries last. The CASE is needed because
     * SQLite sorts NULLs first by default.
     */
    private static function orderClause(string $sort): string
    {
        $chronological = '(SELECT MIN(nv.value_number)
                             FROM entry_values nv
                             JOIN layout_fields nf ON nf.id = nv.field_id
                            WHERE nv.entry_id = e.id
                              AND nf.field_type IN (\'number\', \'date\', \'era\'))';

        return match ($sort) {
            'recent'   => ' ORDER BY e.updated_at DESC, e.title ASC',
            'created'  => ' ORDER BY e.created_at DESC, e.title ASC',
            'timeline' => ' ORDER BY CASE WHEN ' . $chronological . ' IS NULL THEN 1 ELSE 0 END ASC, '
                          . $chronological . ' ASC, e.title ASC',
            default    => ' ORDER BY e.title ASC',
        };
    }

    /** Every favourited entry, grouped by archive in sidebar order, alphabetical within each. */
    public function favorites(): array
    {
        $rows = $this->db->all(
            'SELECT e.id, e.title, e.slug, e.favorited_at, e.archived_at,
                    c.id AS category_id, c.name AS category_name,
                    c.slug AS category_slug, c.icon AS category_icon,
                    c.color AS category_color
               FROM entries e
               JOIN categories c ON c.id = e.category_id
              WHERE e.favorited_at IS NOT NULL'
        );

        $order = (new CategoryRepo($this->db))->orderMap();

        usort($rows, static function (array $x, array $y) use ($order): int {
            $xa = $order[(int) $x['category_id']] ?? PHP_INT_MAX;
            $ya = $order[(int) $y['category_id']] ?? PHP_INT_MAX;

            return [$xa, mb_strtolower($x['title'])] <=> [$ya, mb_strtolower($y['title'])];
        });

        return $rows;
    }

    /** Pins or unpins an entry. Re-favouriting an already-favourited entry keeps the original timestamp. */
    public function setFavorite(int $entryId, bool $on): void
    {
        $current = $this->db->value(
            'SELECT favorited_at FROM entries WHERE id = :id',
            ['id' => $entryId]
        );

        if ($on === ($current !== null)) {
            return;
        }

        $this->db->update('entries', $entryId, ['favorited_at' => $on ? now() : null]);
    }

    /** Takes an entry out of circulation: it keeps its content but stops appearing anywhere else. */
    public function archive(int $entryId): void
    {
        $this->db->update('entries', $entryId, ['archived_at' => now()]);
    }

    /** Puts an archived entry back into circulation, exactly as it was. */
    public function restoreEntry(int $entryId): void
    {
        $this->db->update('entries', $entryId, ['archived_at' => null]);
    }

    /** Every archived entry in one archive, most recently archived first. */
    public function archivedInCategory(int $categoryId): array
    {
        return $this->db->all(
            'SELECT * FROM entries
              WHERE category_id = :cid AND archived_at IS NOT NULL
              ORDER BY archived_at DESC',
            ['cid' => $categoryId]
        );
    }

    /** Cross-archive search for the top bar. */
    public function searchEverywhere(string $search, int $limit = 60): array
    {
        if (trim($search) === '') {
            return [];
        }

        $needle = '%' . mb_strtolower(trim($search)) . '%';

        return $this->db->all(
            'SELECT e.*, c.name AS category_name, c.slug AS category_slug,
                    c.icon AS category_icon, c.color AS category_color
               FROM entries e
               JOIN categories c ON c.id = e.category_id
              WHERE (LOWER(e.title) LIKE :needle
                 OR EXISTS (SELECT 1 FROM entry_values v
                             WHERE v.entry_id = e.id
                               AND LOWER(v.value_text) LIKE :needle2))
                AND e.archived_at IS NULL
              ORDER BY e.title ASC',
            ['needle' => $needle, 'needle2' => $needle]
        );
    }

    /**
     * Entries the picker may offer.
     *
     * @param array<int,int>|int|null $categoryIds archives to restrict to; empty or null searches everywhere
     * @param array<int, int> $excludeIds entries already chosen, left out of the results
     */
    public function lookup(
        string $search,
        array|int|null $categoryIds = null,
        int $excludeId = 0,
        int $limit = 20,
        array $excludeIds = []
    ): array {
        $sql = 'SELECT e.id, e.title, e.slug, e.guid, e.category_id,
                       c.name AS category_name, c.icon AS category_icon,
                       c.slug AS category_slug
                  FROM entries e
                  JOIN categories c ON c.id = e.category_id
                 WHERE e.id <> :exclude AND e.archived_at IS NULL';
        $params = ['exclude' => $excludeId];

        $categoryIds = array_values(array_unique(array_filter(
            array_map('intval', is_array($categoryIds) ? $categoryIds : [$categoryIds])
        )));

        if ($categoryIds !== []) {
            $placeholders = [];
            foreach ($categoryIds as $index => $id) {
                $placeholders[] = ':c' . $index;
                $params['c' . $index] = $id;
            }
            $sql .= ' AND e.category_id IN (' . implode(', ', $placeholders) . ')';
        }

        if (trim($search) !== '') {
            $sql .= ' AND LOWER(e.title) LIKE :needle';
            $params['needle'] = '%' . mb_strtolower(trim($search)) . '%';
        }

        $excludeIds = array_values(array_unique(array_filter(array_map('intval', $excludeIds))));
        if ($excludeIds !== []) {
            $placeholders = [];
            foreach ($excludeIds as $index => $id) {
                $placeholders[] = ':x' . $index;
                $params['x' . $index] = $id;
            }
            $sql .= ' AND e.id NOT IN (' . implode(', ', $placeholders) . ')';
        }

        $rows = $this->db->all($sql, $params);

        // Grouped by archive in sidebar order, alphabetical within each.
        $order = (new CategoryRepo($this->db))->orderMap();

        usort($rows, static function (array $x, array $y) use ($order): int {
            $xa = $order[(int) $x['category_id']] ?? PHP_INT_MAX;
            $ya = $order[(int) $y['category_id']] ?? PHP_INT_MAX;

            return [$xa, mb_strtolower($x['title'])] <=> [$ya, mb_strtolower($y['title'])];
        });

        // Take a turn from each archive so the limit doesn't get spent
        // entirely on whichever one comes first.
        if ($categoryIds === [] && count($rows) > $limit) {
            $rows = self::spreadAcrossArchives($rows, $limit, $order);
        }

        return array_slice($rows, 0, $limit);
    }

    /**
     * Takes one row from each archive in turn until the limit is filled, then
     * puts the result back into archive order.
     *
     * @param array<int, array<string, mixed>> $rows  already sorted
     * @param array<int, int>                  $order archive id => tree position
     * @return array<int, array<string, mixed>>
     */
    private static function spreadAcrossArchives(array $rows, int $limit, array $order): array
    {
        $byCategory = [];
        foreach ($rows as $row) {
            $byCategory[(int) $row['category_id']][] = $row;
        }

        $taken = [];
        while (count($taken) < $limit && $byCategory !== []) {
            foreach (array_keys($byCategory) as $categoryId) {
                $taken[] = array_shift($byCategory[$categoryId]);

                if ($byCategory[$categoryId] === []) {
                    unset($byCategory[$categoryId]);
                }
                if (count($taken) >= $limit) {
                    break;
                }
            }
        }

        usort($taken, static function (array $x, array $y) use ($order): int {
            $xa = $order[(int) $x['category_id']] ?? PHP_INT_MAX;
            $ya = $order[(int) $y['category_id']] ?? PHP_INT_MAX;

            return [$xa, mb_strtolower($x['title'])] <=> [$ya, mb_strtolower($y['title'])];
        });

        return $taken;
    }

    /**
     * @return array<int, array{value_text: ?string, value_number: ?float}> keyed by field id
     */
    public function values(int $entryId): array
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

    /**
     * @return array<int, array<int, array>> targets keyed by field id, in order
     */
    public function links(int $entryId): array
    {
        $rows = $this->db->all(
            'SELECT k.field_id, k.relation_type, e.id, e.title, e.slug,
                    c.name AS category_name, c.slug AS category_slug, c.icon AS category_icon
               FROM entry_links k
               JOIN entries e ON e.id = k.target_entry_id
               JOIN categories c ON c.id = e.category_id
              WHERE k.entry_id = :eid AND e.archived_at IS NULL
              ORDER BY k.field_id ASC, k.sort_order ASC',
            ['eid' => $entryId]
        );

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row['field_id']][] = $row;
        }

        return $out;
    }

    /**
     * Every entry that points at this one, with the field that does the pointing.
     */
    public function backlinks(int $entryId): array
    {
        return $this->db->all(
            'SELECT e.id, e.title, e.slug, f.label AS field_label, k.relation_type,
                    c.name AS category_name, c.slug AS category_slug, c.icon AS category_icon
               FROM entry_links k
               JOIN entries e ON e.id = k.entry_id
               JOIN layout_fields f ON f.id = k.field_id
               JOIN categories c ON c.id = e.category_id
              WHERE k.target_entry_id = :eid AND e.archived_at IS NULL
              ORDER BY c.name ASC, e.title ASC',
            ['eid' => $entryId]
        );
    }

    public function countForCategory(int $categoryId): int
    {
        return (int) $this->db->value(
            'SELECT COUNT(*) FROM entries WHERE category_id = :cid',
            ['cid' => $categoryId]
        );
    }

    public function recent(int $limit = 8): array
    {
        $rows = $this->db->all(
            'SELECT e.*, c.name AS category_name, c.slug AS category_slug,
                    c.icon AS category_icon, c.color AS category_color
               FROM entries e
               JOIN categories c ON c.id = e.category_id
              WHERE e.archived_at IS NULL
              ORDER BY e.updated_at DESC'
        );

        return array_slice($rows, 0, $limit);
    }

    /**
     * Creates or updates an entry together with all of its field values.
     *
     * @param array<int, array<string, mixed>> $fields the layout's field definitions
     * @param array $post  usually $_POST
     * @param array $files usually $_FILES
     * @return int the entry id
     */
    public function save(
        ?int $entryId,
        int $categoryId,
        int $layoutId,
        array $fields,
        array $post,
        array $files = []
    ): int {
        $title = trim((string) ($post['title'] ?? ''));
        if ($title === '') {
            throw new RuntimeException(t('An entry needs a name.'));
        }

        // Only the save that actually moves an entry reports a move; a plain
        // save afterwards must not inherit the last one's answer.
        $this->lastCarry = null;

        return $this->db->transaction(function (Database $db) use (
            $entryId, $categoryId, $layoutId, $fields, $post, $files, $title
        ): int {
            if ($entryId === null) {
                $entryId = $db->insert('entries', [
                    'category_id' => $categoryId,
                    'layout_id'   => $layoutId,
                    'title'       => mb_substr($title, 0, 250),
                    'slug'        => $this->uniqueSlug($title, $categoryId),
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);
            } else {
                $current = $this->find($entryId);
                if ($current === null) {
                    throw new RuntimeException(t('That entry no longer exists.'));
                }

                $previousLayout = (int) $current['layout_id'];

                // Taken before anything changes, so it can be recorded as
                // history if this save turns out to actually change something.
                $revisions = new EntryRevisionRepo($db);
                $before = $revisions->snapshot($entryId, (string) $current['title']);

                $data = [
                    'title'      => mb_substr($title, 0, 250),
                    'layout_id'  => $layoutId,
                    'updated_at' => now(),
                ];
                if ($title !== $current['title']) {
                    $data['slug'] = $this->uniqueSlug($title, $categoryId, $entryId);
                }

                $db->update('entries', $entryId, $data);
            }

            $postedValues = is_array($post['fields'] ?? null) ? $post['fields'] : [];

            foreach ($fields as $field) {
                $fieldId = (int) $field['id'];
                $type = (string) $field['field_type'];
                $raw = $postedValues[$fieldId] ?? null;

                if (FieldTypes::isRelation($type)) {
                    // Posted as a side channel rather than nested in fields[$fieldId].
                    $rawTypes = $post['relation_types'][$fieldId] ?? null;
                    $this->saveLinks(
                        $db,
                        $entryId,
                        $fieldId,
                        is_array($raw) ? $raw : [],
                        is_array($rawTypes) ? $rawTypes : []
                    );
                    continue;
                }

                if (FieldTypes::isUpload($type)) {
                    $this->saveImage($db, $entryId, $fieldId, $post, $files);
                    continue;
                }

                $this->saveScalar($db, $entryId, $fieldId, $type, $raw);
            }

            // Only record a revision if the save actually changed something.
            if (isset($before, $revisions, $current)) {
                $after = $revisions->snapshot($entryId, mb_substr($title, 0, 250));
                if ($before != $after) {
                    $revisions->record($current, $before, 'edit');
                }
            }

            // Content follows the entry wherever a field of the same name
            // exists on the new layout; whatever is left over is cleared out.
            if (isset($previousLayout) && $previousLayout !== $layoutId) {
                $this->lastCarry = $this->carryToNewLayout($db, $entryId, $layoutId, $fields);
            }

            return $entryId;
        });
    }

    /**
     * What the last layout change moved, and what it could not.
     *
     * @return array{carried: array<int, string>, dropped: array<int, string>}|null
     */
    public function lastCarry(): ?array
    {
        return $this->lastCarry;
    }

    /**
     * Carries an entry's content onto its new layout by matching field labels
     * and type, then deletes whatever is left over from the old layout. Only
     * fields the save left empty are filled in — anything typed into the form
     * wins over the old copy.
     *
     * @param array $fields the new layout's live fields
     * @return array{carried: array<int, string>, dropped: array<int, string>}
     */
    private function carryToNewLayout(Database $db, int $entryId, int $layoutId, array $fields): array
    {
        $strays = $db->all(
            'SELECT f.id AS field_id, f.label, f.field_type, f.layout_id
               FROM layout_fields f
              WHERE f.layout_id <> :lid
                AND (EXISTS (SELECT 1 FROM entry_values v
                              WHERE v.field_id = f.id AND v.entry_id = :eid)
                  OR EXISTS (SELECT 1 FROM entry_links k
                              WHERE k.field_id = f.id AND k.entry_id = :eid2))',
            ['lid' => $layoutId, 'eid' => $entryId, 'eid2' => $entryId]
        );

        $carried = [];
        $dropped = [];

        foreach ($strays as $stray) {
            $type = (string) $stray['field_type'];
            $target = null;

            foreach ($fields as $field) {
                if ($field['label'] === $stray['label'] && (string) $field['field_type'] === $type) {
                    $target = $field;
                    break;
                }
            }

            if ($target === null) {
                $dropped[] = (string) $stray['label'];
                continue;
            }

            $targetId = (int) $target['id'];

            if (FieldTypes::isRelation($type)) {
                $already = (int) $db->value(
                    'SELECT COUNT(*) FROM entry_links WHERE entry_id = :eid AND field_id = :fid',
                    ['eid' => $entryId, 'fid' => $targetId]
                );

                if ($already > 0) {
                    continue;   // the form supplied links; they win
                }

                $rows = $db->all(
                    'SELECT target_entry_id, relation_type FROM entry_links
                      WHERE entry_id = :eid AND field_id = :fid ORDER BY sort_order ASC',
                    ['eid' => $entryId, 'fid' => (int) $stray['field_id']]
                );

                $this->saveLinks(
                    $db,
                    $entryId,
                    $targetId,
                    array_map(static fn (array $r) => (int) $r['target_entry_id'], $rows),
                    array_map(static fn (array $r) => (string) ($r['relation_type'] ?? ''), $rows)
                );

                $carried[] = (string) $stray['label'];
                continue;
            }

            $existing = $db->first(
                'SELECT value_text FROM entry_values WHERE entry_id = :eid AND field_id = :fid',
                ['eid' => $entryId, 'fid' => $targetId]
            );

            if ($existing !== null && trim((string) $existing['value_text']) !== '') {
                continue;       // the form supplied a value; it wins
            }

            $old = $db->first(
                'SELECT value_text, value_number FROM entry_values
                  WHERE entry_id = :eid AND field_id = :fid',
                ['eid' => $entryId, 'fid' => (int) $stray['field_id']]
            );

            if ($old === null || trim((string) $old['value_text']) === '') {
                continue;
            }

            if ($existing === null) {
                $db->insert('entry_values', [
                    'entry_id'     => $entryId,
                    'field_id'     => $targetId,
                    'value_text'   => $old['value_text'],
                    'value_number' => $old['value_number'],
                ]);
            } else {
                $db->run(
                    'UPDATE entry_values SET value_text = :t, value_number = :n
                      WHERE entry_id = :eid AND field_id = :fid',
                    ['t' => $old['value_text'], 'n' => $old['value_number'],
                     'eid' => $entryId, 'fid' => $targetId]
                );
            }

            $carried[] = (string) $stray['label'];
        }

        // Archived fields of the *current* layout are untouched; their
        // content is meant to survive.
        $db->run(
            'DELETE FROM entry_values WHERE entry_id = :eid AND field_id IN
               (SELECT id FROM layout_fields WHERE layout_id <> :lid)',
            ['eid' => $entryId, 'lid' => $layoutId]
        );
        $db->run(
            'DELETE FROM entry_links WHERE entry_id = :eid AND field_id IN
               (SELECT id FROM layout_fields WHERE layout_id <> :lid)',
            ['eid' => $entryId, 'lid' => $layoutId]
        );

        return ['carried' => array_values(array_unique($carried)),
                'dropped' => array_values(array_unique($dropped))];
    }

    private function saveScalar(
        Database $db,
        int $entryId,
        int $fieldId,
        string $type,
        mixed $raw
    ): void {
        $number = null;

        if (FieldTypes::isMultiValue($type)) {
            $list = is_array($raw) ? $raw : preg_split('/\s*,\s*/', (string) $raw);
            $list = array_values(array_unique(array_filter(
                array_map(static fn ($v) => trim((string) $v), $list ?: []),
                static fn ($v) => $v !== ''
            )));
            $text = $list === [] ? null : json_encode($list, JSON_UNESCAPED_UNICODE);
        } elseif ($type === FieldTypes::RICHTEXT) {
            $clean = Sanitizer::clean((string) $raw);
            $text = $clean === '' ? null : $clean;
        } elseif ($type === FieldTypes::NUMBER) {
            $text = trim((string) $raw);
            if ($text === '') {
                $text = null;
            } else {
                // Accept both "1.5" and the German "1,5".
                $normalised = str_replace(',', '.', $text);
                $number = is_numeric($normalised) ? (float) $normalised : null;
            }
        } elseif ($type === FieldTypes::DATE) {
            $date = is_array($raw)
                ? Calendar::parseDate($raw['year'] ?? '', $raw['slot'] ?? '', $raw['day'] ?? '')
                : null;
            $text = $date === null ? null : Calendar::encode($date);
            $number = $date === null ? null : Calendar::sortValue($date);
        } elseif (FieldTypes::isEra($type)) {
            $rawFrom = is_array($raw) ? (array) ($raw['from'] ?? []) : [];
            $rawTo = is_array($raw) ? (array) ($raw['to'] ?? []) : [];
            $from = Calendar::parseDate($rawFrom['year'] ?? '', $rawFrom['slot'] ?? '', $rawFrom['day'] ?? '');
            $to = Calendar::parseDate($rawTo['year'] ?? '', $rawTo['slot'] ?? '', $rawTo['day'] ?? '');
            $text = Calendar::encodeEra($from, $to);
            $number = $from === null ? null : Calendar::sortValue($from);
        } else {
            $text = trim((string) $raw);
            if ($text === '') {
                $text = null;
            }
        }

        $this->putValue($db, $entryId, $fieldId, $text, $number);
    }

    private function saveImage(
        Database $db,
        int $entryId,
        int $fieldId,
        array $post,
        array $files
    ): void {
        $existing = $db->first(
            'SELECT value_text FROM entry_values WHERE entry_id = :eid AND field_id = :fid',
            ['eid' => $entryId, 'fid' => $fieldId]
        );
        $currentPath = $existing['value_text'] ?? null;

        $upload = $files['field_image_' . $fieldId] ?? null;
        $hasUpload = is_array($upload) && ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        $wantsRemoval = !empty($post['field_image_remove'][$fieldId]);

        if ($hasUpload) {
            $newPath = Uploads::store($upload);
            $this->putValue($db, $entryId, $fieldId, $newPath, null);
            Uploads::remove($currentPath);

            return;
        }

        if ($wantsRemoval) {
            $this->putValue($db, $entryId, $fieldId, null, null);
            Uploads::remove($currentPath);
        }

        // Otherwise leave whatever is already stored alone.
    }

    /** Select-then-write rather than an upsert. A null value removes the row entirely. */
    private function putValue(
        Database $db,
        int $entryId,
        int $fieldId,
        ?string $text,
        ?float $number
    ): void {
        $existing = $db->first(
            'SELECT id FROM entry_values WHERE entry_id = :eid AND field_id = :fid',
            ['eid' => $entryId, 'fid' => $fieldId]
        );

        if ($text === null && $number === null) {
            if ($existing !== null) {
                $db->delete('entry_values', (int) $existing['id']);
            }

            return;
        }

        if ($existing !== null) {
            $db->update('entry_values', (int) $existing['id'], [
                'value_text'   => $text,
                'value_number' => $number,
            ]);

            return;
        }

        $db->insert('entry_values', [
            'entry_id'     => $entryId,
            'field_id'     => $fieldId,
            'value_text'   => $text,
            'value_number' => $number,
        ]);
    }

    /**
     * @param array<int, mixed> $targetIds
     * @param array<int, mixed> $types    relation-type labels, positionally matching $targetIds
     */
    private function saveLinks(Database $db, int $entryId, int $fieldId, array $targetIds, array $types = []): void
    {
        $db->run(
            'DELETE FROM entry_links WHERE entry_id = :eid AND field_id = :fid',
            ['eid' => $entryId, 'fid' => $fieldId]
        );

        $seen = [];
        $position = 0;

        foreach ($targetIds as $i => $targetId) {
            $targetId = (int) $targetId;
            if ($targetId <= 0 || $targetId === $entryId || isset($seen[$targetId])) {
                continue;
            }

            // A stale picker selection must not break the whole save.
            $exists = (int) $db->value(
                'SELECT COUNT(*) FROM entries WHERE id = :id',
                ['id' => $targetId]
            );
            if ($exists === 0) {
                continue;
            }

            $seen[$targetId] = true;
            $relationType = trim((string) ($types[$i] ?? ''));

            $db->insert('entry_links', [
                'entry_id'        => $entryId,
                'field_id'        => $fieldId,
                'target_entry_id' => $targetId,
                'sort_order'      => $position++,
                'relation_type'   => $relationType === '' ? null : mb_substr($relationType, 0, 80),
            ]);
        }
    }

    /**
     * Moves an entry into another archive, rebuilding it on that archive's
     * layout. Values follow wherever a field of the same label exists in the
     * destination; $labelMap renames on the way across, and mapping a label to
     * null drops it deliberately. The entry keeps its id.
     *
     * @param array<string, string|null> $labelMap old label => new label
     * @return array<int, string> labels whose content could not be carried over
     */
    public function moveToCategory(
        int $entryId,
        int $categoryId,
        int $layoutId,
        array $labelMap = []
    ): array {
        return $this->db->transaction(function (Database $db) use (
            $entryId, $categoryId, $layoutId, $labelMap
        ): array {
            $entry = $this->find($entryId);
            if ($entry === null) {
                throw new RuntimeException(t('That entry no longer exists.'));
            }

            $layouts = new LayoutRepo($db);

            $oldById = [];
            foreach ($layouts->fields((int) $entry['layout_id']) as $field) {
                $oldById[(int) $field['id']] = $field;
            }

            $newByLabel = [];
            foreach ($layouts->fields($layoutId) as $field) {
                $newByLabel[$field['label']] = $field;
            }

            $lost = [];
            $claimed = [];

            /** The destination field for an old one, or null if there is none. */
            $destination = static function (array $oldField) use ($labelMap, $newByLabel): ?array {
                $label = $oldField['label'];
                if (array_key_exists($label, $labelMap)) {
                    $label = $labelMap[$label];
                }

                return $label === null ? null : ($newByLabel[$label] ?? null);
            };

            // A field that changes between scalar and relation cannot carry
            // its content across, since they live in different tables.
            foreach ($db->all(
                'SELECT id, field_id FROM entry_values WHERE entry_id = :eid',
                ['eid' => $entryId]
            ) as $row) {
                $old = $oldById[(int) $row['field_id']] ?? null;
                $target = $old === null ? null : $destination($old);

                $usable = $target !== null
                    && !FieldTypes::isRelation((string) $target['field_type'])
                    && !isset($claimed[(int) $target['id']]);

                if (!$usable) {
                    if ($old !== null) {
                        $lost[] = $old['label'];
                    }
                    $db->delete('entry_values', (int) $row['id']);
                    continue;
                }

                $claimed[(int) $target['id']] = true;
                $db->update('entry_values', (int) $row['id'], ['field_id' => (int) $target['id']]);
            }

            foreach ($db->all(
                'SELECT id, field_id FROM entry_links WHERE entry_id = :eid',
                ['eid' => $entryId]
            ) as $row) {
                $old = $oldById[(int) $row['field_id']] ?? null;
                $target = $old === null ? null : $destination($old);

                if ($target === null || !FieldTypes::isRelation((string) $target['field_type'])) {
                    if ($old !== null) {
                        $lost[] = $old['label'];
                    }
                    $db->delete('entry_links', (int) $row['id']);
                    continue;
                }

                $db->update('entry_links', (int) $row['id'], ['field_id' => (int) $target['id']]);
            }

            $db->update('entries', $entryId, [
                'category_id' => $categoryId,
                'layout_id'   => $layoutId,
                // Slugs are unique per archive, so the old one may be taken over there.
                'slug'        => $this->uniqueSlug($entry['title'], $categoryId, $entryId),
                'updated_at'  => now(),
            ]);

            return array_values(array_unique($lost));
        });
    }

    /**
     * Copies an entry: every field value, every relation it points at, and its
     * free-form connections. Uploaded files are shared by reference rather than
     * copied. Stays within the entry's own archive and layout unless a target
     * is given — the caller (EntryController) only ever passes a target layout
     * whose fields exactly match the source, so nothing is lost carrying values
     * across by label.
     *
     * @return int the new entry's id
     */
    public function duplicate(
        int $entryId,
        ?string $newTitle = null,
        ?int $targetCategoryId = null,
        ?int $targetLayoutId = null
    ): int {
        return $this->db->transaction(function (Database $db) use (
            $entryId, $newTitle, $targetCategoryId, $targetLayoutId
        ): int {
            $entry = $this->find($entryId);
            if ($entry === null) {
                throw new RuntimeException(t('That entry no longer exists.'));
            }

            $categoryId = $targetCategoryId ?? (int) $entry['category_id'];
            $layoutId = $targetLayoutId ?? (int) $entry['layout_id'];

            $title = $newTitle !== null && trim($newTitle) !== ''
                ? trim($newTitle)
                : $entry['title'] . ' (copy)';

            $copyId = $db->insert('entries', [
                'category_id' => $categoryId,
                'layout_id'   => $layoutId,
                'title'       => mb_substr($title, 0, 250),
                'slug'        => $this->uniqueSlug($title, $categoryId),
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);

            $fieldMap = $layoutId === (int) $entry['layout_id']
                ? null
                : $this->fieldMapByLabel((int) $entry['layout_id'], $layoutId, $db);

            foreach ($db->all(
                'SELECT field_id, value_text, value_number FROM entry_values WHERE entry_id = :eid',
                ['eid' => $entryId]
            ) as $row) {
                $fieldId = $fieldMap === null ? (int) $row['field_id'] : ($fieldMap[(int) $row['field_id']] ?? null);
                if ($fieldId === null) {
                    continue;
                }

                $db->insert('entry_values', [
                    'entry_id'     => $copyId,
                    'field_id'     => $fieldId,
                    'value_text'   => $row['value_text'],
                    'value_number' => $row['value_number'],
                ]);
            }

            foreach ($db->all(
                'SELECT field_id, target_entry_id, sort_order, relation_type FROM entry_links WHERE entry_id = :eid',
                ['eid' => $entryId]
            ) as $row) {
                $fieldId = $fieldMap === null ? (int) $row['field_id'] : ($fieldMap[(int) $row['field_id']] ?? null);
                if ($fieldId === null) {
                    continue;
                }

                $db->insert('entry_links', [
                    'entry_id'        => $copyId,
                    'field_id'        => $fieldId,
                    'target_entry_id' => (int) $row['target_entry_id'],
                    'sort_order'      => (int) $row['sort_order'],
                    'relation_type'   => $row['relation_type'],
                ]);
            }

            $connections = new ConnectionRepo($db);
            foreach ($connections->forTarget(ConnectionRepo::ENTRY, $entryId) as $connection) {
                $connections->connect(
                    ConnectionRepo::ENTRY,
                    $copyId,
                    $connection['type'],
                    (int) $connection['id']
                );
            }

            return $copyId;
        });
    }

    /** @return array<int, int> source field id => destination field id, matched by label */
    private function fieldMapByLabel(int $fromLayoutId, int $toLayoutId, Database $db): array
    {
        $layouts = new LayoutRepo($db);

        $byLabel = [];
        foreach ($layouts->fields($toLayoutId) as $field) {
            $byLabel[$field['label']] = (int) $field['id'];
        }

        $map = [];
        foreach ($layouts->fields($fromLayoutId) as $field) {
            if (isset($byLabel[$field['label']])) {
                $map[(int) $field['id']] = $byLabel[$field['label']];
            }
        }

        return $map;
    }

    /**
     * Every distinct tag already used in one field, for the tag input's suggestions.
     *
     * @return array<int, string>
     */
    public function tagsInUse(int $fieldId): array
    {
        $rows = $this->db->all(
            'SELECT DISTINCT value_text FROM entry_values
              WHERE field_id = :fid AND value_text IS NOT NULL',
            ['fid' => $fieldId]
        );

        $tags = [];
        foreach ($rows as $row) {
            foreach ((array) (json_decode((string) $row['value_text'], true) ?: []) as $tag) {
                $tag = trim((string) $tag);
                if ($tag !== '') {
                    $tags[mb_strtolower($tag)] = $tag;
                }
            }
        }

        ksort($tags, SORT_NATURAL | SORT_FLAG_CASE);

        return array_values($tags);
    }

    public function delete(int $entryId): void
    {
        $this->db->transaction(function (Database $db) use ($entryId): void {
            $entry = $this->find($entryId);
            if ($entry !== null) {
                // Recorded so the delete can be undone from the change history.
                $revisions = new EntryRevisionRepo($db);
                $snapshot = $revisions->snapshot($entryId, (string) $entry['title']);
                $revisions->record($entry, $snapshot, 'delete');
            }

            $this->deleteUploads($entryId);
            // Connections are polymorphic, so no foreign key cleans them up.
            (new ConnectionRepo($db))->removeAllFor(ConnectionRepo::ENTRY, $entryId);
            $db->delete('entries', $entryId);

            // Nulled out so a later, unrelated entry reusing this id doesn't inherit this history.
            $db->run('UPDATE entry_revisions SET entry_id = NULL WHERE entry_id = :id', ['id' => $entryId]);
        });
    }

    /** Removes the files behind an entry's image fields. Called before the row disappears. */
    public function deleteUploads(int $entryId): void
    {
        $rows = $this->db->all(
            'SELECT v.value_text
               FROM entry_values v
               JOIN layout_fields f ON f.id = v.field_id
              WHERE v.entry_id = :eid AND f.field_type IN (:image, :banner)',
            ['eid' => $entryId, 'image' => FieldTypes::IMAGE, 'banner' => FieldTypes::BANNER]
        );

        foreach ($rows as $row) {
            $path = (string) $row['value_text'];
            if ($path === '') {
                continue;
            }

            // A duplicated entry points at the same file; only remove it once nothing else refers to it.
            $others = (int) $this->db->value(
                'SELECT COUNT(*) FROM entry_values
                  WHERE value_text = :path AND entry_id <> :eid',
                ['path' => $path, 'eid' => $entryId]
            );

            if ($others === 0) {
                Uploads::remove($path);
            }
        }
    }

    public function uniqueSlug(string $title, int $categoryId, ?int $ignoreId = null): string
    {
        $base = slugify($title, 'entry');
        $base = substr($base, 0, 180);
        $slug = $base;
        $suffix = 2;

        while ($this->slugTaken($slug, $categoryId, $ignoreId)) {
            $slug = $base . '-' . $suffix++;
        }

        return $slug;
    }

    private function slugTaken(string $slug, int $categoryId, ?int $ignoreId): bool
    {
        $sql = 'SELECT COUNT(*) FROM entries WHERE category_id = :cid AND slug = :slug';
        $params = ['cid' => $categoryId, 'slug' => $slug];

        if ($ignoreId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $ignoreId;
        }

        return (int) $this->db->value($sql, $params) > 0;
    }
}
