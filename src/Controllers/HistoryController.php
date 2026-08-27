<?php

namespace App\Controllers;

use App\EntryRevisionRepo;
use Throwable;

/**
 * Every entry edit and deletion on record, and the one place to undo either.
 */
final class HistoryController
{
    private const PAGE_SIZE = 25;

    public function index(): never
    {
        $revisions = new EntryRevisionRepo();

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $total = $revisions->count();
        $pages = max(1, (int) ceil($total / self::PAGE_SIZE));
        $page = min($page, $pages);

        render('history/index', [
            'pageTitle' => t('Change history'),
            'section'   => 'history',
            'revisions' => $revisions->recent(self::PAGE_SIZE, ($page - 1) * self::PAGE_SIZE),
            'total'     => $total,
            'page'      => $page,
            'pages'     => $pages,
        ]);
    }

    /** POST /history/{id}/restore — brings back what that version held. */
    public function restore(int $revisionId): never
    {
        try {
            $result = (new EntryRevisionRepo())->restore($revisionId);
        } catch (Throwable $e) {
            flash($e->getMessage(), 'error');
            redirect('/history');
        }

        flash($result['recreated']
            ? t('Restored (and re-created) "%s".', $result['title'])
            : t('Restored "%s".', $result['title']));
        redirect('/history');
    }

    /** GET /history/{id}/diff — what that edit actually changed. */
    public function diff(int $revisionId): never
    {
        try {
            $diff = (new EntryRevisionRepo())->diff($revisionId);
        } catch (Throwable $e) {
            flash($e->getMessage(), 'error');
            redirect('/history');
        }

        render('history/diff', [
            'pageTitle' => t('What changed'),
            'section'   => 'history',
            'diff'      => $diff,
        ]);
    }

    /** POST /history/clear — wipes every recorded revision. */
    public function clear(): never
    {
        (new EntryRevisionRepo())->clear();

        flash(t('Change history cleared.'));
        redirect('/history');
    }
}
