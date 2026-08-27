<?php

namespace App;

/**
 * The pinboard's view of the world: entries as pins, and the strings between
 * them.
 *
 * Two kinds of string: a connection is drawn by hand and reads the same from
 * both ends; a field link belongs to a relation field, points one way, and can
 * only be changed by editing the entry that holds it. Chapters are left out
 * entirely — a board of entries stays a board of entries.
 */
final class PinboardRepo
{
    public const CONNECTION = 'connection';
    public const FIELD = 'field';

    /** Past this many pins the board is a hairball, not a picture. */
    public const MAX_PINS = 240;

    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::instance();
    }

    /**
     * The board around a set of pins: every pin resolved, every string among
     * them, and each pin's total neighbour count.
     *
     * @param array<int, int> $ids entries already pinned
     * @return array{nodes: array<int, array<string, mixed>>, edges: array<int, array<string, mixed>>}
     */
    public function board(array $ids, array $hidden = []): array
    {
        $ids = self::clean($ids);
        if ($ids === []) {
            return ['nodes' => [], 'edges' => []];
        }

        $hidden = self::clean($hidden);
        $nodes = $this->resolve($ids);

        // A hidden archive's entries leave the board entirely, strings and
        // neighbour counts included, rather than just being painted over.
        if ($hidden !== []) {
            $nodes = array_values(array_filter(
                $nodes,
                static fn (array $node) => !in_array($node['category'], $hidden, true)
            ));
            $ids = array_map(static fn (array $node) => $node['id'], $nodes);

            if ($ids === []) {
                return ['nodes' => [], 'edges' => []];
            }
        }

        $touching = $this->touching($ids, $hidden);
        $inside = array_flip($ids);

        // Two entries joined by both a connection and a relation field count
        // as one neighbour, shown by two strings.
        $neighbours = [];
        $edges = [];

        foreach ($touching as $row) {
            $a = (int) $row['a'];
            $b = (int) $row['b'];

            $neighbours[$a][$b] = true;
            $neighbours[$b][$a] = true;

            if (!isset($inside[$a]) || !isset($inside[$b])) {
                continue;
            }

            $edges[] = $row['kind'] === self::CONNECTION
                ? [
                    'kind' => self::CONNECTION,
                    'id'   => (int) $row['id'],
                    'a'    => $a,
                    'b'    => $b,
                    'note' => $row['note'],
                ]
                : [
                    // A field link points one way: a character has an allegiance.
                    'kind'  => self::FIELD,
                    'id'    => (int) $row['id'],
                    'a'     => (int) $row['from'],
                    'b'     => (int) $row['to'],
                    'label' => $row['label'],
                ];
        }

        foreach ($nodes as $index => $node) {
            $nodes[$index]['degree'] = count($neighbours[(int) $node['id']] ?? []);
        }

        return ['nodes' => $nodes, 'edges' => $edges];
    }

    /**
     * Everything one step out from a single pin.
     *
     * @return array<int, int> entry ids, the pin itself excluded
     */
    public function neighbours(int $entryId, array $hidden = []): array
    {
        $out = [];

        foreach ($this->touching([$entryId], $hidden) as $row) {
            $a = (int) $row['a'];
            $b = (int) $row['b'];
            $far = $a === $entryId ? $b : $a;

            if ($far !== $entryId) {
                $out[$far] = true;
            }
        }

        return array_keys($out);
    }

    /**
     * Every string with at least one end among these entries, of both kinds, in
     * one pass — used for both the strings drawn and the out-of-sight count.
     *
     * @param array<int, int> $ids
     * @return array<int, array<string, mixed>>
     */
    private function touching(array $ids, array $hidden = []): array
    {
        $ids = self::clean($ids);
        if ($ids === []) {
            return [];
        }

        // The same id list is needed on both sides of an OR, so each
        // placeholder is written once, named for the side it belongs to.
        [$left, $leftParams] = self::inClause($ids, 'l');
        [$right, $rightParams] = self::inClause($ids, 'r');

        // Filtered here rather than afterwards, so the neighbour counts stay honest.
        $keep = ' AND ea.archived_at IS NULL AND eb.archived_at IS NULL';
        $keepParams = [];

        if (self::clean($hidden) !== []) {
            [$out, $keepParams] = self::inClause(self::clean($hidden), 'h');
            $keep .= ' AND ea.category_id NOT IN (' . $out . ')'
                   . ' AND eb.category_id NOT IN (' . $out . ')';
        }

        $rows = [];

        foreach ($this->db->all(
            'SELECT c.id, c.a_id, c.b_id, c.note
               FROM connections c
               JOIN entries ea ON ea.id = c.a_id
               JOIN entries eb ON eb.id = c.b_id
              WHERE c.a_type = :atype AND c.b_type = :btype
                AND (c.a_id IN (' . $left . ') OR c.b_id IN (' . $right . '))'
                . $keep,
            ['atype' => ConnectionRepo::ENTRY, 'btype' => ConnectionRepo::ENTRY]
                + $leftParams + $rightParams + $keepParams
        ) as $row) {
            $rows[] = [
                'kind' => self::CONNECTION,
                'id'   => (int) $row['id'],
                'a'    => (int) $row['a_id'],
                'b'    => (int) $row['b_id'],
                'note' => $row['note'],
            ];
        }

        // A link through an archived field is not shown on the entry either, so
        // it is not a string here.
        foreach ($this->db->all(
            'SELECT k.id, k.entry_id, k.target_entry_id, f.label
               FROM entry_links k
               JOIN layout_fields f ON f.id = k.field_id
               JOIN entries ea ON ea.id = k.entry_id
               JOIN entries eb ON eb.id = k.target_entry_id
              WHERE f.archived_at IS NULL
                AND (k.entry_id IN (' . $left . ') OR k.target_entry_id IN (' . $right . '))'
                . $keep,
            $leftParams + $rightParams + $keepParams
        ) as $row) {
            $rows[] = [
                'kind'  => self::FIELD,
                'id'    => (int) $row['id'],
                'a'     => (int) $row['entry_id'],
                'b'     => (int) $row['target_entry_id'],
                'from'  => (int) $row['entry_id'],
                'to'    => (int) $row['target_entry_id'],
                'label' => $row['label'],
            ];
        }

        return $rows;
    }

    /**
     * Entries as pins: what it takes to draw one and follow it.
     *
     * @param array<int, int> $ids
     * @return array<int, array<string, mixed>>
     */
    private function resolve(array $ids): array
    {
        [$in, $params] = self::inClause($ids, 'e');

        $rows = $this->db->all(
            'SELECT e.id, e.title, e.slug, e.category_id, e.favorited_at,
                    c.name AS category_name, c.slug AS category_slug,
                    c.icon AS category_icon, c.color AS category_color
               FROM entries e
               JOIN categories c ON c.id = e.category_id
              WHERE e.id IN (' . $in . ')
                AND e.archived_at IS NULL',
            $params
        );

        $fields = $this->relationFields($ids);

        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'id'        => (int) $row['id'],
                'title'     => $row['title'],
                'archive'   => $row['category_name'],
                'category'  => (int) $row['category_id'],
                'icon'      => $row['category_icon'] ?: '•',
                'color'     => $row['category_color'] ?: '',
                'url'       => url('/c/' . $row['category_slug'] . '/e/' . $row['slug']),
                'favourite' => $row['favorited_at'] !== null,
                'fields'    => $fields[(int) $row['id']] ?? [],
            ];
        }

        return $out;
    }

    /**
     * The relation fields each entry actually has, with what they are allowed
     * to point at and what they already hold — so the board can grey out an
     * impossible target while a string is being dragged. Only live fields of
     * the entry's current layout count.
     *
     * @param array<int, int> $ids
     * @return array<int, array<int, array<string, mixed>>> entry id => fields
     */
    private function relationFields(array $ids): array
    {
        [$in, $params] = self::inClause($ids, 'f');

        $rows = $this->db->all(
            'SELECT e.id AS entry_id, k.id AS field_id, k.label, k.config, k.sort_order
               FROM entries e
               JOIN layout_fields k ON k.layout_id = e.layout_id
              WHERE k.field_type = :type
                AND k.archived_at IS NULL
                AND e.id IN (' . $in . ')
              ORDER BY k.sort_order ASC, k.id ASC',
            ['type' => FieldTypes::RELATION] + $params
        );

        if ($rows === []) {
            return [];
        }

        // What each field already points at, so the board can say "replaces"
        // rather than quietly overwriting.
        $holds = [];
        foreach ($this->db->all(
            'SELECT l.entry_id, l.field_id, l.target_entry_id
               FROM entry_links l
              WHERE l.entry_id IN (' . $in . ')',
            $params
        ) as $link) {
            $holds[(int) $link['entry_id']][(int) $link['field_id']][] = (int) $link['target_entry_id'];
        }

        $out = [];
        foreach ($rows as $row) {
            $entryId = (int) $row['entry_id'];
            $fieldId = (int) $row['field_id'];
            $config = json_decode((string) $row['config'], true) ?: [];

            $out[$entryId][] = [
                'id'       => $fieldId,
                'label'    => $row['label'],
                // An empty list means anywhere.
                'targets'  => FieldTypes::relationTargets($config),
                'multiple' => !empty($config['multiple']),
                'holds'    => $holds[$entryId][$fieldId] ?? [],
            ];
        }

        return $out;
    }

    /**
     * One live relation field of an entry's current layout, with its rules.
     * Null if the entry has no such field — including when the field exists but
     * belongs to some other layout, or has since been archived.
     *
     * @return array<string, mixed>|null
     */
    public function relationField(int $entryId, int $fieldId): ?array
    {
        foreach ($this->relationFields([$entryId])[$entryId] ?? [] as $field) {
            if ((int) $field['id'] === $fieldId) {
                return $field;
            }
        }

        return null;
    }

    /**
     * Points an entry at another through one of its relation fields. A
     * single-value field is emptied first; a field already pointing at this
     * target is left as it is rather than growing a duplicate.
     *
     * @param array<string, mixed> $field from relationField()
     * @return bool whether something was displaced to make room
     */
    public function addLink(int $entryId, array $field, int $targetId): bool
    {
        $fieldId = (int) $field['id'];

        if (in_array($targetId, $field['holds'], true)) {
            return false;
        }

        return (bool) $this->db->transaction(
            function (Database $db) use ($entryId, $fieldId, $targetId, $field): bool {
                $replaced = false;

                if (empty($field['multiple'])) {
                    $replaced = $field['holds'] !== [];
                    $db->run(
                        'DELETE FROM entry_links WHERE entry_id = :eid AND field_id = :fid',
                        ['eid' => $entryId, 'fid' => $fieldId]
                    );
                }

                // Onto the end of whatever the field already holds.
                $next = (int) $db->value(
                    'SELECT COALESCE(MAX(sort_order), -1) + 1 FROM entry_links
                      WHERE entry_id = :eid AND field_id = :fid',
                    ['eid' => $entryId, 'fid' => $fieldId]
                );

                $db->insert('entry_links', [
                    'entry_id'        => $entryId,
                    'field_id'        => $fieldId,
                    'target_entry_id' => $targetId,
                    'sort_order'      => $next,
                ]);

                return $replaced;
            }
        );
    }

    /** Takes one target back out of a relation field. */
    public function removeLink(int $linkId): void
    {
        $this->db->delete('entry_links', $linkId);
    }

    /**
     * Puts entry ids in the order a board should meet them: the most tied-up
     * first, since those are the ones that explain a neighbourhood.
     *
     * @param array<int, int> $ids
     * @return array<int, int>
     */
    public function rank(array $ids): array
    {
        $counts = $this->neighbourCounts();

        usort($ids, static function (int $x, int $y) use ($counts): int {
            return ($counts[$y] ?? 0) <=> ($counts[$x] ?? 0);
        });

        return $ids;
    }

    /**
     * Somewhere to start: the entries with the most strings on them, which are
     * the ones a board is usually about.
     *
     * @return array<int, array<string, mixed>>
     */
    public function busiest(int $limit = 8): array
    {
        $counts = $this->neighbourCounts();
        arsort($counts);

        $wanted = array_slice(array_keys($counts), 0, max(1, $limit));
        if ($wanted === []) {
            return [];
        }

        $nodes = $this->resolve($wanted);
        foreach ($nodes as $index => $node) {
            $nodes[$index]['degree'] = $counts[(int) $node['id']] ?? 0;
        }

        usort($nodes, static function (array $x, array $y): int {
            return [$y['degree'], mb_strtolower($x['title'])]
               <=> [$x['degree'], mb_strtolower($y['title'])];
        });

        return $nodes;
    }

    /**
     * How many other entries each entry is tied to, counted the same way the
     * board counts: two entries joined twice are one neighbour.
     *
     * @return array<int, int> entry id => neighbours
     */
    private function neighbourCounts(): array
    {
        $neighbours = [];

        $note = function (int $a, int $b) use (&$neighbours): void {
            if ($a === $b) {
                return;
            }
            $neighbours[$a][$b] = true;
            $neighbours[$b][$a] = true;
        };

        foreach ($this->db->all(
            'SELECT c.a_id, c.b_id
               FROM connections c
               JOIN entries ea ON ea.id = c.a_id
               JOIN entries eb ON eb.id = c.b_id
              WHERE c.a_type = :at AND c.b_type = :bt
                AND ea.archived_at IS NULL AND eb.archived_at IS NULL',
            ['at' => ConnectionRepo::ENTRY, 'bt' => ConnectionRepo::ENTRY]
        ) as $row) {
            $note((int) $row['a_id'], (int) $row['b_id']);
        }

        foreach ($this->db->all(
            'SELECT k.entry_id, k.target_entry_id
               FROM entry_links k
               JOIN layout_fields f ON f.id = k.field_id
               JOIN entries ea ON ea.id = k.entry_id
               JOIN entries eb ON eb.id = k.target_entry_id
              WHERE f.archived_at IS NULL
                AND ea.archived_at IS NULL AND eb.archived_at IS NULL'
        ) as $row) {
            $note((int) $row['entry_id'], (int) $row['target_entry_id']);
        }

        return array_map('count', $neighbours);
    }

    /**
     * @param array<int, int> $ids
     * @return array{0: string, 1: array<string, int>}
     */
    private static function inClause(array $ids, string $prefix): array
    {
        $placeholders = [];
        $params = [];

        foreach (array_values($ids) as $index => $id) {
            $name = $prefix . $index;
            $placeholders[] = ':' . $name;
            $params[$name] = $id;
        }

        return [implode(', ', $placeholders), $params];
    }

    /**
     * @param array<int, mixed> $ids
     * @return array<int, int>
     */
    private static function clean(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(
            array_map('intval', $ids),
            static fn (int $id) => $id > 0
        )));

        return array_slice($ids, 0, self::MAX_PINS);
    }
}
