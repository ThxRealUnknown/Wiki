<?php

namespace App;

/**
 * The middle column's current sort order and page size, remembered per
 * session (sort is remembered per archive) so navigating away and back
 * doesn't reset it.
 */
final class ListState
{
    /**
     * An explicit ?sort= wins and is remembered; otherwise the archive's last
     * sort, falling back to its default.
     *
     * @param array $sortFields the archive's sortable choice fields, so a sort
     *                          by a field that's since been removed is forgotten
     */
    public static function sort(array $category, array $sortFields = []): string
    {
        $categoryId = (int) ($category['id'] ?? 0);
        $requested = (string) ($_GET['sort'] ?? '');

        if (!isset($_SESSION['sort']) || !is_array($_SESSION['sort'])) {
            $_SESSION['sort'] = [];
        }

        $fallback = CategoryRepo::cleanSort($category['default_sort'] ?? 'title');

        if ($requested !== '') {
            $sort = CategoryRepo::cleanSort($requested);
        } else {
            $sort = CategoryRepo::cleanSort($_SESSION['sort'][$categoryId] ?? $fallback);
        }

        if (!self::stillOffered($sort, $sortFields)) {
            unset($_SESSION['sort'][$categoryId]);

            return self::stillOffered($fallback, $sortFields) ? $fallback : 'title';
        }

        $_SESSION['sort'][$categoryId] = $sort;

        return $sort;
    }

    /** An explicit ?per_page= wins and is remembered, for every archive alike. */
    public static function perPage(): int
    {
        if (isset($_GET['per_page'])) {
            $_SESSION['per_page'] = EntryRepo::cleanPerPage($_GET['per_page']);
        }

        return EntryRepo::cleanPerPage($_SESSION['per_page'] ?? EntryRepo::PAGE_SIZE_DEFAULT);
    }

    /** The built-in orders always stand; a choice field only while it is offered. */
    private static function stillOffered(string $sort, array $sortFields): bool
    {
        if (!str_starts_with($sort, 'field:')) {
            return true;
        }

        $wanted = (int) substr($sort, 6);

        foreach ($sortFields as $group) {
            if (in_array($wanted, $group['ids'] ?? [], true)) {
                return true;
            }
        }

        return false;
    }
}
