<?php

namespace App\Controllers;

use App\ChapterRepo;
use App\ConnectionRepo;
use App\EntryRepo;

final class ApiController
{
    /**
     * Feeds both pickers: /api/lookup?q=&category=&exclude=&scope=
     *
     * scope=entries  — relation fields, which only ever point at entries
     * scope=all      — the connection rail, which reaches chapters as well
     */
    public function lookup(): never
    {
        $query = trim((string) ($_GET['q'] ?? ''));
        // One or more archive ids; empty means search everywhere.
        $categoryIds = self::idList($_GET['category'] ?? '');
        $exclude = (int) ($_GET['exclude'] ?? 0);
        $scope = ($_GET['scope'] ?? 'entries') === 'all' ? 'all' : 'entries';
        $excludeType = ($_GET['exclude_type'] ?? ConnectionRepo::ENTRY) === ConnectionRepo::CHAPTER
            ? ConnectionRepo::CHAPTER
            : ConnectionRepo::ENTRY;

        // Already-picked things are left out entirely rather than offered and
        // then silently refused.
        $takenEntries = self::idList($_GET['taken_entries'] ?? '');
        $takenChapters = self::idList($_GET['taken_chapters'] ?? '');

        $results = [];

        $entryExclude = $excludeType === ConnectionRepo::ENTRY ? $exclude : 0;
        foreach ((new EntryRepo())->lookup($query, $categoryIds, $entryExclude, 20, $takenEntries) as $row) {
            $results[] = [
                'type'     => ConnectionRepo::ENTRY,
                'id'       => (int) $row['id'],
                // The link picker writes the guid into the text and needs an
                // address to show meanwhile; the other pickers ignore both.
                'guid'     => $row['guid'],
                'url'      => url('/c/' . $row['category_slug'] . '/e/' . $row['slug']),
                'title'    => $row['title'],
                'category' => $row['category_name'],
                'icon'     => $row['category_icon'] ?: '•',
            ];
        }

        if ($scope === 'all') {
            $chapterExclude = $excludeType === ConnectionRepo::CHAPTER ? $exclude : 0;
            foreach ((new ChapterRepo())->lookup($query, $chapterExclude, 20, $takenChapters) as $row) {
                $number = ChapterRepo::formatNumber(
                    $row['chapter_number'] === null ? null : (float) $row['chapter_number']
                );

                $results[] = [
                    'type'     => ConnectionRepo::CHAPTER,
                    'id'       => (int) $row['id'],
                    'title'    => ($number === '' ? '' : $number . '. ') . $row['title'],
                    'category' => t('Draft'),
                    'icon'     => '✍',
                ];
            }
        }

        json_response(['results' => $results]);
    }

    /**
     * A comma-separated list of ids from the query string.
     *
     * @return array<int, int>
     */
    private static function idList(mixed $raw): array
    {
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }

        return array_values(array_unique(array_filter(
            array_map('intval', explode(',', $raw)),
            static fn (int $id) => $id > 0
        )));
    }
}
