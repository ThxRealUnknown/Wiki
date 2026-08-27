<?php

namespace App\Controllers;

use App\CategoryRepo;
use App\ChapterRepo;
use App\EntryRepo;
use App\Settings;

final class HomeController
{
    public function dashboard(): never
    {
        $categories = new CategoryRepo();
        $entries = new EntryRepo();

        render('dashboard', [
            'pageTitle'  => t('Overview'),
            'section'    => 'overview',
            'tree'       => $categories->treeWithCounts(),
            'categories' => $categories->allWithCounts(),
            'recent'     => $entries->recent(10),
        ]);
    }

    public function search(): never
    {
        $query = trim((string) ($_GET['q'] ?? ''));
        $results = $query === '' ? [] : (new EntryRepo())->searchEverywhere($query);

        // Group by archive so the results read like the sidebar.
        $grouped = [];
        foreach ($results as $row) {
            $grouped[$row['category_name']][] = $row;
        }

        $chapters = ($query !== '' && Settings::flag(Settings::FEATURE_BOOK))
            ? (new ChapterRepo())->search($query)
            : [];

        render('search', [
            'pageTitle' => $query === '' ? t('Search') : t('Search: %s', $query),
            'query'     => $query,
            'grouped'   => $grouped,
            'chapters'  => $chapters,
            'total'     => count($results) + count($chapters),
        ]);
    }
}
