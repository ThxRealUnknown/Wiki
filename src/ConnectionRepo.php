<?php

namespace App;

/**
 * Free-form connections between anything and anything: entry↔entry,
 * entry↔chapter, chapter↔chapter. Unlike a relation field, a connection is not
 * part of any layout — it is a link the author draws by hand, and it reads the
 * same from both ends.
 */
final class ConnectionRepo
{
    public const ENTRY = 'entry';
    public const CHAPTER = 'chapter';

    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::instance();
    }

    /**
     * Sorts the two ends before writing, so A–B and B–A are the same row and
     * the unique index can do the deduplicating.
     *
     * @return array{0: string, 1: int, 2: string, 3: int}
     */
    private static function canonical(string $aType, int $aId, string $bType, int $bId): array
    {
        $left = $aType . ':' . str_pad((string) $aId, 12, '0', STR_PAD_LEFT);
        $right = $bType . ':' . str_pad((string) $bId, 12, '0', STR_PAD_LEFT);

        return $left <= $right
            ? [$aType, $aId, $bType, $bId]
            : [$bType, $bId, $aType, $aId];
    }

    public function connect(
        string $aType,
        int $aId,
        string $bType,
        int $bId,
        ?string $note = null
    ): bool {
        // Nothing connects to itself.
        if ($aType === $bType && $aId === $bId) {
            return false;
        }

        [$aType, $aId, $bType, $bId] = self::canonical($aType, $aId, $bType, $bId);

        $existing = $this->db->first(
            'SELECT id FROM connections
              WHERE a_type = :at AND a_id = :ai AND b_type = :bt AND b_id = :bi',
            ['at' => $aType, 'ai' => $aId, 'bt' => $bType, 'bi' => $bId]
        );

        if ($existing !== null) {
            return false;
        }

        $note = trim((string) $note);
        $this->db->insert('connections', [
            'a_type'     => $aType,
            'a_id'       => $aId,
            'b_type'     => $bType,
            'b_id'       => $bId,
            'note'       => $note === '' ? null : mb_substr($note, 0, 300),
            'created_at' => now(),
        ]);

        return true;
    }

    public function remove(int $connectionId): void
    {
        $this->db->delete('connections', $connectionId);
    }

    public function updateNote(int $connectionId, ?string $note): void
    {
        $note = trim((string) $note);
        $this->db->update('connections', $connectionId, [
            'note' => $note === '' ? null : mb_substr($note, 0, 300),
        ]);
    }

    /** Called before an entry or chapter row disappears. */
    public function removeAllFor(string $type, int $id): void
    {
        $this->db->run(
            'DELETE FROM connections
              WHERE (a_type = :t1 AND a_id = :i1) OR (b_type = :t2 AND b_id = :i2)',
            ['t1' => $type, 'i1' => $id, 't2' => $type, 'i2' => $id]
        );
    }

    public function countFor(string $type, int $id): int
    {
        return (int) $this->db->value(
            'SELECT COUNT(*) FROM connections
              WHERE (a_type = :t1 AND a_id = :i1) OR (b_type = :t2 AND b_id = :i2)',
            ['t1' => $type, 'i1' => $id, 't2' => $type, 'i2' => $id]
        );
    }

    /**
     * Everything connected to one thing, from its point of view — the far end of
     * each row, resolved to a title, an icon and a link.
     *
     * @return array<int, array<string, mixed>>
     */
    public function forTarget(string $type, int $id): array
    {
        $rows = $this->db->all(
            'SELECT id, a_type, a_id, b_type, b_id, note FROM connections
              WHERE (a_type = :t1 AND a_id = :i1) OR (b_type = :t2 AND b_id = :i2)
              ORDER BY id ASC',
            ['t1' => $type, 'i1' => $id, 't2' => $type, 'i2' => $id]
        );

        if ($rows === []) {
            return [];
        }

        $wanted = [self::ENTRY => [], self::CHAPTER => []];
        $far = [];

        foreach ($rows as $row) {
            $isA = $row['a_type'] === $type && (int) $row['a_id'] === $id;
            $farType = $isA ? $row['b_type'] : $row['a_type'];
            $farId = (int) ($isA ? $row['b_id'] : $row['a_id']);

            $far[] = [
                'connection_id' => (int) $row['id'],
                'type'          => $farType,
                'id'            => $farId,
                'note'          => $row['note'],
            ];

            if (isset($wanted[$farType])) {
                $wanted[$farType][$farId] = true;
            }
        }

        $entries = $this->resolveEntries(array_keys($wanted[self::ENTRY]));
        $chapters = $this->resolveChapters(array_keys($wanted[self::CHAPTER]));

        $out = [];
        foreach ($far as $item) {
            $source = $item['type'] === self::ENTRY ? $entries : $chapters;

            // A row whose far end has vanished is skipped rather than shown broken.
            if (!isset($source[$item['id']])) {
                continue;
            }

            $out[] = $item + $source[$item['id']];
        }

        // Archives in tree order (a sub-archive sits with its parent),
        // alphabetical within each group, chapters last.
        usort($out, static function (array $x, array $y): int {
            return [$x['order'], $x['context'], mb_strtolower($x['title'])]
               <=> [$y['order'], $y['context'], mb_strtolower($y['title'])];
        });

        return $out;
    }

    /** @param array<int, int> $ids */
    private function resolveEntries(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $placeholders = [];
        $params = [];
        foreach (array_values($ids) as $index => $id) {
            $placeholders[] = ':id' . $index;
            $params['id' . $index] = $id;
        }

        $rows = $this->db->all(
            'SELECT e.id, e.title, e.slug, e.category_id,
                    c.name AS category_name, c.slug AS category_slug,
                    c.icon AS category_icon, c.color AS category_color
               FROM entries e
               JOIN categories c ON c.id = e.category_id
              WHERE e.id IN (' . implode(', ', $placeholders) . ')
                AND e.archived_at IS NULL',
            $params
        );

        $order = (new CategoryRepo($this->db))->orderMap();

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row['id']] = [
                'title'   => $row['title'],
                'context' => $row['category_name'],
                'icon'    => $row['category_icon'] ?: '•',
                'color'   => $row['category_color'],
                'url'     => url('/c/' . $row['category_slug'] . '/e/' . $row['slug']),
                'order'   => $order[(int) $row['category_id']] ?? PHP_INT_MAX - 1,
            ];
        }

        return $out;
    }

    /** @param array<int, int> $ids */
    private function resolveChapters(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $placeholders = [];
        $params = [];
        foreach (array_values($ids) as $index => $id) {
            $placeholders[] = ':id' . $index;
            $params['id' . $index] = $id;
        }

        $rows = $this->db->all(
            'SELECT id, title, slug, chapter_number, is_visible FROM chapters
              WHERE id IN (' . implode(', ', $placeholders) . ')',
            $params
        );

        $out = [];
        foreach ($rows as $row) {
            $number = $row['chapter_number'] === null
                ? ''
                : ChapterRepo::formatNumber((float) $row['chapter_number']) . '. ';

            $out[(int) $row['id']] = [
                'title'   => $number . $row['title'],
                'context' => 'Draft',
                'icon'    => '✍',
                'color'   => null,
                'url'     => url('/draft/' . $row['slug']),
                // The book is not an archive, so it sorts after all of them.
                'order'   => PHP_INT_MAX,
            ];
        }

        return $out;
    }
}
