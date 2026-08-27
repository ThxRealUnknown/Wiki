<?php

namespace App;

/**
 * Categories are the archives in the left-hand sidebar: Characters, Species,
 * Magic Systems, and whatever else the world needs.
 */
final class CategoryRepo
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::instance();
    }

    public function all(): array
    {
        return $this->db->all(
            'SELECT * FROM categories ORDER BY sort_order ASC, name ASC'
        );
    }

    /**
     * Sidebar listing: every category plus how many entries it holds.
     */
    public function allWithCounts(): array
    {
        return $this->db->all(
            'SELECT c.*, (SELECT COUNT(*) FROM entries e
                            WHERE e.category_id = c.id AND e.archived_at IS NULL) AS entry_count
               FROM categories c
              ORDER BY c.sort_order ASC, c.name ASC'
        );
    }

    /**
     * The same list arranged for display: top-level archives in order, each
     * carrying its own children under a 'children' key, nested to any depth.
     * A row whose parent is missing, or whose ancestry loops, is treated as
     * top level rather than hanging the sidebar.
     */
    public function treeWithCounts(): array
    {
        $rows = $this->allWithCounts();

        $byId = [];
        foreach ($rows as $row) {
            $byId[(int) $row['id']] = $row;
        }

        $rootIds = [];
        $childrenOf = [];

        foreach ($byId as $id => $row) {
            $parentId = $row['parent_id'] === null ? null : (int) $row['parent_id'];

            if ($parentId === null || !isset($byId[$parentId]) || self::loops($byId, $id)) {
                $rootIds[] = $id;
                continue;
            }

            $childrenOf[$parentId][] = $id;
        }

        $build = static function (int $id) use (&$build, $byId, $childrenOf): array {
            $node = $byId[$id];
            $node['children'] = [];

            foreach ($childrenOf[$id] ?? [] as $childId) {
                $node['children'][] = $build($childId);
            }

            return $node;
        };

        return array_map($build, $rootIds);
    }

    /** Would walking up from this row never reach a top-level archive? */
    private static function loops(array $byId, int $startId): bool
    {
        $seen = [];
        $id = $startId;

        while (isset($byId[$id])) {
            if (isset($seen[$id])) {
                return true;
            }
            $seen[$id] = true;

            $parentId = $byId[$id]['parent_id'];
            if ($parentId === null) {
                return false;
            }
            $id = (int) $parentId;
        }

        return false;
    }

    /**
     * Archives that may become the parent of $excludeId: everything except that
     * archive itself and everything beneath it. Flat, but in tree order and
     * carrying a 'depth', so the picker can indent.
     *
     * @return array<int, array<string, mixed>>
     */
    public function possibleParents(?int $excludeId = null): array
    {
        $forbidden = [];
        if ($excludeId !== null) {
            $forbidden = $this->descendantIds($excludeId);
            $forbidden[] = $excludeId;
        }

        return $this->flatTree($forbidden);
    }

    /**
     * Every archive in tree order, each carrying the 'depth' it sits at, so a
     * flat control can still show the shape.
     *
     * @param array<int, int> $excludeIds these and everything beneath them
     * @return array<int, array<string, mixed>>
     */
    public function flatTree(array $excludeIds = []): array
    {
        $out = [];

        $walk = static function (array $nodes, int $depth) use (&$walk, &$out, $excludeIds): void {
            foreach ($nodes as $node) {
                if (in_array((int) $node['id'], $excludeIds, true)) {
                    continue;   // its whole branch goes with it
                }

                $out[] = [
                    'id'    => $node['id'],
                    'name'  => $node['name'],
                    'icon'  => $node['icon'],
                    'slug'  => $node['slug'],
                    'depth' => $depth,
                ];
                $walk($node['children'], $depth + 1);
            }
        };

        $walk($this->treeWithCounts(), 0);

        return $out;
    }

    /**
     * Archive id => its position in the sidebar, top to bottom, with children
     * following their parent. Skips loading entry counts, unlike treeWithCounts().
     *
     * @return array<int, int>
     */
    public function orderMap(): array
    {
        $rows = $this->db->all(
            'SELECT id, parent_id FROM categories ORDER BY sort_order ASC, name ASC'
        );

        $byId = [];
        foreach ($rows as $row) {
            $byId[(int) $row['id']] = $row;
        }

        $childrenOf = [];
        $rootIds = [];
        foreach ($rows as $row) {
            $id = (int) $row['id'];
            $parentId = $row['parent_id'] === null ? null : (int) $row['parent_id'];

            if ($parentId === null || !isset($byId[$parentId]) || self::loops($byId, $id)) {
                $rootIds[] = $id;
                continue;
            }
            $childrenOf[$parentId][] = $id;
        }

        $order = [];
        $position = 0;

        $walk = static function (array $ids) use (&$walk, &$order, &$position, $childrenOf): void {
            foreach ($ids as $id) {
                $order[$id] = $position++;
                $walk($childrenOf[$id] ?? []);
            }
        };
        $walk($rootIds);

        return $order;
    }

    /**
     * Every archive beneath this one, at any depth.
     *
     * @return array<int, int>
     */
    public function descendantIds(int $categoryId): array
    {
        $childrenOf = [];
        foreach ($this->db->all('SELECT id, parent_id FROM categories') as $row) {
            if ($row['parent_id'] !== null) {
                $childrenOf[(int) $row['parent_id']][] = (int) $row['id'];
            }
        }

        $found = [];
        $queue = $childrenOf[$categoryId] ?? [];

        while ($queue !== []) {
            $id = array_shift($queue);
            if (in_array($id, $found, true)) {
                continue;       // a loop in the data cannot trap this walk
            }

            $found[] = $id;
            foreach ($childrenOf[$id] ?? [] as $childId) {
                $queue[] = $childId;
            }
        }

        return $found;
    }

    /**
     * The chain from the top down to, but not including, this archive.
     *
     * @return array<int, array<string, mixed>>
     */
    public function ancestors(array $category): array
    {
        $chain = [];
        $seen = [];
        $parentId = ($category['parent_id'] ?? null) === null ? null : (int) $category['parent_id'];

        while ($parentId !== null && !isset($seen[$parentId])) {
            $seen[$parentId] = true;

            $parent = $this->find($parentId);
            if ($parent === null) {
                break;
            }

            array_unshift($chain, $parent);
            $parentId = $parent['parent_id'] === null ? null : (int) $parent['parent_id'];
        }

        return $chain;
    }

    public function children(int $categoryId): array
    {
        return $this->db->all(
            'SELECT * FROM categories WHERE parent_id = :pid ORDER BY sort_order ASC, name ASC',
            ['pid' => $categoryId]
        );
    }

    public function hasChildren(int $categoryId): bool
    {
        return (int) $this->db->value(
            'SELECT COUNT(*) FROM categories WHERE parent_id = :pid',
            ['pid' => $categoryId]
        ) > 0;
    }

    public function parentOf(array $category): ?array
    {
        if (($category['parent_id'] ?? null) === null) {
            return null;
        }

        return $this->find((int) $category['parent_id']);
    }

    /**
     * Resolves a requested parent to something legal: an archive cannot be its
     * own parent or be filed under one of its own descendants. An empty request
     * means top level; a disallowed request returns $current unchanged rather
     * than silently promoting the archive to the top.
     */
    private function resolveParent(mixed $requested, ?int $selfId, ?int $current = null): ?int
    {
        // Explicitly "no parent".
        if ($requested === null || $requested === '' || $requested === '0' || $requested === 0) {
            return null;
        }

        $parentId = (int) $requested;

        if ($parentId <= 0 || ($selfId !== null && $parentId === $selfId)) {
            return $current;
        }

        if ($this->find($parentId) === null) {
            return $current;
        }

        // Moving an archive under its own descendant would create a loop.
        if ($selfId !== null && in_array($parentId, $this->descendantIds($selfId), true)) {
            return $current;
        }

        return $parentId;
    }

    public function find(int $id): ?array
    {
        return $this->db->first('SELECT * FROM categories WHERE id = :id', ['id' => $id]);
    }

    public function findBySlug(string $slug): ?array
    {
        return $this->db->first('SELECT * FROM categories WHERE slug = :slug', ['slug' => $slug]);
    }

    public function create(array $input): int
    {
        $name = trim((string) ($input['name'] ?? ''));

        return $this->db->insert('categories', [
            'name'        => $name,
            'slug'        => $this->uniqueSlug($name),
            'icon'        => $this->cleanIcon($input['icon'] ?? null),
            'color'       => $this->cleanColor($input['color'] ?? null),
            'description'  => $this->cleanDescription($input['description'] ?? null),
            'default_sort' => self::cleanSort($input['default_sort'] ?? null),
            'parent_id'    => $this->resolveParent($input['parent_id'] ?? null, null),
            'sort_order'   => $this->nextSortOrder(),
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
    }

    /**
     * Partial update: only the keys actually present in $input are written.
     * Absent means "leave alone"; present-but-empty means "clear it".
     */
    public function update(int $id, array $input): void
    {
        $current = $this->find($id);
        if ($current === null) {
            return;
        }

        $data = ['updated_at' => now()];

        if (array_key_exists('name', $input)) {
            $name = trim((string) $input['name']);
            if ($name !== '') {
                $data['name'] = $name;

                // Only re-slug on an actual rename, so bookmarks survive other edits.
                if ($name !== $current['name']) {
                    $data['slug'] = $this->uniqueSlug($name, $id);
                }
            }
        }

        if (array_key_exists('icon', $input)) {
            $data['icon'] = $this->cleanIcon($input['icon']);
        }
        if (array_key_exists('color', $input)) {
            $data['color'] = $this->cleanColor($input['color']);
        }
        if (array_key_exists('description', $input)) {
            $data['description'] = $this->cleanDescription($input['description']);
        }
        if (array_key_exists('default_sort', $input)) {
            $data['default_sort'] = self::cleanSort($input['default_sort']);
        }
        if (array_key_exists('parent_id', $input)) {
            $data['parent_id'] = $this->resolveParent(
                $input['parent_id'],
                $id,
                $current['parent_id'] === null ? null : (int) $current['parent_id']
            );
        }

        $this->db->update('categories', $id, $data);
    }

    public function delete(int $id): void
    {
        // Entries and layouts cascade; uploaded images are cleaned up first
        // since the filesystem has no foreign keys.
        $entries = $this->db->all(
            'SELECT id FROM entries WHERE category_id = :id',
            ['id' => $id]
        );

        $entryRepo = new EntryRepo($this->db);
        $connections = new ConnectionRepo($this->db);
        foreach ($entries as $entry) {
            $entryRepo->deleteUploads((int) $entry['id']);
            $connections->removeAllFor(ConnectionRepo::ENTRY, (int) $entry['id']);
        }

        // Children move up to take this archive's place, rather than the top
        // level, so the rest of the tree keeps its shape.
        $current = $this->find($id);
        $this->db->run(
            'UPDATE categories SET parent_id = :new WHERE parent_id = :old',
            ['new' => $current['parent_id'] ?? null, 'old' => $id]
        );

        $this->db->delete('categories', $id);
    }

    public function reorder(array $orderedIds): void
    {
        $this->db->transaction(function (Database $db) use ($orderedIds): void {
            foreach (array_values($orderedIds) as $position => $id) {
                $db->update('categories', (int) $id, ['sort_order' => $position]);
            }
        });
    }

    private function nextSortOrder(): int
    {
        return (int) $this->db->value('SELECT COALESCE(MAX(sort_order), -1) + 1 FROM categories');
    }

    private function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = slugify($name, 'archive');
        $slug = $base;
        $suffix = 2;

        while ($this->slugTaken($slug, $ignoreId)) {
            $slug = $base . '-' . $suffix++;
        }

        return $slug;
    }

    private function slugTaken(string $slug, ?int $ignoreId): bool
    {
        if ($ignoreId === null) {
            return (int) $this->db->value(
                'SELECT COUNT(*) FROM categories WHERE slug = :slug',
                ['slug' => $slug]
            ) > 0;
        }

        return (int) $this->db->value(
            'SELECT COUNT(*) FROM categories WHERE slug = :slug AND id <> :id',
            ['slug' => $slug, 'id' => $ignoreId]
        ) > 0;
    }

    /**
     * The order an archive opens in; History wants chronological, most want A–Z.
     * 'field:12' orders by a choice field ticked as sortable in the layout —
     * only the shape is checked here, not whether that field still exists.
     */
    public static function cleanSort(?string $sort): string
    {
        if (in_array($sort, ['title', 'recent', 'created', 'timeline'], true)) {
            return $sort;
        }

        if (is_string($sort) && preg_match('/^field:[1-9][0-9]{0,8}$/', $sort) === 1) {
            return $sort;
        }

        return 'title';
    }

    private function cleanIcon(?string $icon): ?string
    {
        $icon = trim((string) $icon);
        if ($icon === '') {
            return null;
        }

        // One glyph is all the sidebar has room for.
        return mb_substr($icon, 0, 2);
    }

    private function cleanColor(?string $color): ?string
    {
        $color = trim((string) $color);

        return preg_match('/^#[0-9a-fA-F]{6}$/', $color) ? strtolower($color) : null;
    }

    private function cleanDescription(?string $description): ?string
    {
        $description = trim((string) $description);

        return $description === '' ? null : mb_substr($description, 0, 500);
    }
}
