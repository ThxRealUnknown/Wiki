<?php

namespace App\Controllers;

use App\EntryRepo;
use App\TagRepo;

/** Everything carrying one tag, reached by clicking it wherever it appears. */
final class TagController
{
    /** GET /tags/{tag} */
    public function show(string $tag): never
    {
        $ids = (new TagRepo())->entryIdsWith($tag);
        $results = $ids === [] ? [] : (new EntryRepo())->findManyWithCategory($ids);

        $grouped = [];
        foreach ($results as $row) {
            $grouped[$row['category_name']][] = $row;
        }

        render('tags/show', [
            'pageTitle' => t('Tag: %s', $tag),
            'tag'       => $tag,
            'grouped'   => $grouped,
            'total'     => count($results),
        ]);
    }
}
