<?php

namespace App;

use RuntimeException;

/**
 * Chapters of the book. They live outside the archive system — no category,
 * no layout — because their shape is fixed.
 */
final class ChapterRepo
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::instance();
    }

    /** Every chapter, drafts included, in reading order. */
    public function all(): array
    {
        return $this->db->all(
            'SELECT * FROM chapters
              ORDER BY CASE WHEN chapter_number IS NULL THEN 1 ELSE 0 END,
                       chapter_number ASC,
                       created_at ASC'
        );
    }

    /** Only what the Story section is allowed to show. */
    public function published(): array
    {
        return $this->db->all(
            'SELECT * FROM chapters
              WHERE is_visible = 1
              ORDER BY CASE WHEN chapter_number IS NULL THEN 1 ELSE 0 END,
                       chapter_number ASC,
                       created_at ASC'
        );
    }

    /** Every part name currently in use, for the editor's autocomplete. */
    public function parts(): array
    {
        return array_column($this->db->all(
            "SELECT DISTINCT part FROM chapters
              WHERE part IS NOT NULL AND part <> ''
              ORDER BY part ASC"
        ), 'part');
    }

    /**
     * Chapters in reading order, split wherever a part label starts or ends. A
     * chapter with no part sits in a group of its own with no heading, rather
     * than being folded into whichever part came before it.
     *
     * @param array<int, array> $chapters already in reading order
     * @return array<int, array{part: ?string, chapters: array<int, array>}>
     */
    public static function grouped(array $chapters): array
    {
        $groups = [];
        $currentPart = false;

        foreach ($chapters as $chapter) {
            $part = self::partOf($chapter);
            if ($groups === [] || $part !== $currentPart) {
                $groups[] = ['part' => $part, 'chapters' => []];
                $currentPart = $part;
            }
            $groups[count($groups) - 1]['chapters'][] = $chapter;
        }

        return $groups;
    }

    /** null for an unparted chapter, its trimmed label otherwise. */
    public static function partOf(array $chapter): ?string
    {
        $part = trim((string) ($chapter['part'] ?? ''));

        return $part === '' ? null : $part;
    }

    public function find(int $id): ?array
    {
        return $this->db->first('SELECT * FROM chapters WHERE id = :id', ['id' => $id]);
    }

    public function findBySlug(string $slug): ?array
    {
        return $this->db->first('SELECT * FROM chapters WHERE slug = :slug', ['slug' => $slug]);
    }

    public function countAll(): int
    {
        return (int) $this->db->value('SELECT COUNT(*) FROM chapters');
    }

    public function countVisible(): int
    {
        return (int) $this->db->value('SELECT COUNT(*) FROM chapters WHERE is_visible = 1');
    }

    /**
     * The chapter before and after this one within the published sequence, for
     * the Story reader's navigation.
     *
     * @return array{prev: ?array, next: ?array}
     */
    public function neighbours(int $chapterId): array
    {
        $published = $this->published();
        $index = null;

        foreach ($published as $position => $chapter) {
            if ((int) $chapter['id'] === $chapterId) {
                $index = $position;
                break;
            }
        }

        if ($index === null) {
            return ['prev' => null, 'next' => null];
        }

        return [
            'prev' => $published[$index - 1] ?? null,
            'next' => $published[$index + 1] ?? null,
        ];
    }

    public function save(?int $chapterId, array $input): int
    {
        $title = trim((string) ($input['title'] ?? ''));
        if ($title === '') {
            throw new RuntimeException(t('A chapter needs a title.'));
        }

        $rawNumber = trim((string) ($input['chapter_number'] ?? ''));
        $number = null;
        if ($rawNumber !== '') {
            $normalised = str_replace(',', '.', $rawNumber);
            // A float, so 12.5 can sit between chapters 12 and 13.
            $number = is_numeric($normalised) ? (float) $normalised : null;
        }

        $part = trim((string) ($input['part'] ?? ''));

        $data = [
            'title'          => mb_substr($title, 0, 250),
            'chapter_number' => $number,
            'part'           => $part === '' ? null : mb_substr($part, 0, 120),
            'content'        => Sanitizer::clean((string) ($input['content'] ?? '')) ?: null,
            'notes'          => Sanitizer::clean((string) ($input['notes'] ?? '')) ?: null,
            'is_visible'     => empty($input['is_visible']) ? 0 : 1,
            'updated_at'     => now(),
        ];

        if ($chapterId === null) {
            $data['slug'] = $this->uniqueSlug($title);
            $data['created_at'] = now();

            return $this->db->insert('chapters', $data);
        }

        $current = $this->find($chapterId);
        if ($current === null) {
            throw new RuntimeException(t('That chapter no longer exists.'));
        }

        if ($title !== $current['title']) {
            $data['slug'] = $this->uniqueSlug($title, $chapterId);
        }

        $this->db->update('chapters', $chapterId, $data);

        return $chapterId;
    }

    public function setVisibility(int $chapterId, bool $visible): void
    {
        $this->db->update('chapters', $chapterId, [
            'is_visible' => $visible ? 1 : 0,
            'updated_at' => now(),
        ]);
    }

    public function delete(int $chapterId): void
    {
        (new ConnectionRepo($this->db))->removeAllFor(ConnectionRepo::CHAPTER, $chapterId);
        $this->db->delete('chapters', $chapterId);
    }

    /**
     * Chapters offered by the connection picker.
     *
     * @param array<int, int> $excludeIds chapters already connected, left out of the results
     */
    public function lookup(
        string $search,
        int $excludeId = 0,
        int $limit = 20,
        array $excludeIds = []
    ): array {
        $sql = 'SELECT id, title, chapter_number FROM chapters WHERE id <> :exclude';
        $params = ['exclude' => $excludeId];

        if (trim($search) !== '') {
            $sql .= ' AND LOWER(title) LIKE :needle';
            $params['needle'] = '%' . mb_strtolower(trim($search)) . '%';
        }

        $excludeIds = array_values(array_unique(array_filter(array_map('intval', $excludeIds))));
        if ($excludeIds !== []) {
            $placeholders = [];
            foreach ($excludeIds as $index => $id) {
                $placeholders[] = ':x' . $index;
                $params['x' . $index] = $id;
            }
            $sql .= ' AND id NOT IN (' . implode(', ', $placeholders) . ')';
        }

        $sql .= ' ORDER BY CASE WHEN chapter_number IS NULL THEN 1 ELSE 0 END,
                           chapter_number ASC, title ASC';

        return array_slice($this->db->all($sql, $params), 0, $limit);
    }

    /** Chapters whose title, text or notes contain the search, for the sitewide search box. */
    public function search(string $needle): array
    {
        $needle = trim($needle);
        if ($needle === '') {
            return [];
        }

        $like = '%' . mb_strtolower($needle) . '%';

        return $this->db->all(
            'SELECT * FROM chapters
              WHERE LOWER(title) LIKE :t OR LOWER(content) LIKE :c OR LOWER(notes) LIKE :n
              ORDER BY CASE WHEN chapter_number IS NULL THEN 1 ELSE 0 END,
                       chapter_number ASC, title ASC',
            ['t' => $like, 'c' => $like, 'n' => $like]
        );
    }

    /** "12" rather than "12.0", but "12.5" stays "12.5". */
    public static function formatNumber(?float $number): string
    {
        if ($number === null) {
            return '';
        }

        return rtrim(rtrim(number_format($number, 2, '.', ''), '0'), '.');
    }

    private function uniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = substr(slugify($title, 'chapter'), 0, 180);
        $slug = $base;
        $suffix = 2;

        while ($this->slugTaken($slug, $ignoreId)) {
            $slug = $base . '-' . $suffix++;
        }

        return $slug;
    }

    private function slugTaken(string $slug, ?int $ignoreId): bool
    {
        $sql = 'SELECT COUNT(*) FROM chapters WHERE slug = :slug';
        $params = ['slug' => $slug];

        if ($ignoreId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $ignoreId;
        }

        return (int) $this->db->value($sql, $params) > 0;
    }
}
