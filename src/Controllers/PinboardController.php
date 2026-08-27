<?php

namespace App\Controllers;

use App\CategoryRepo;
use App\ConnectionRepo;
use App\EntryRepo;
use App\PinboardRepo;

/**
 * The pinboard: one entry and its connections, expanded further as the reader
 * clicks. Built client-side — the page loads with a single pin, and expanding
 * a hub only adds what's clicked, not its whole neighbourhood at once.
 */
final class PinboardController
{
    private PinboardRepo $pinboard;
    private EntryRepo $entries;

    public function __construct()
    {
        $this->pinboard = new PinboardRepo();
        $this->entries = new EntryRepo();
    }

    /** GET /pinboard[?entry=] */
    public function index(): never
    {
        $entryId = (int) ($_GET['entry'] ?? 0);
        $start = $entryId > 0 ? $this->entries->find($entryId) : null;

        render('pinboard/index', [
            'pageTitle' => t('Pinboard'),
            'section'   => 'pinboard',
            'startId'   => $start === null ? 0 : (int) $start['id'],
            'startName' => $start === null ? '' : (string) $start['title'],
            'busiest'   => $start === null ? $this->pinboard->busiest(8) : [],
            // Every archive, for the filter column; which are hidden is tracked client-side.
            'archives'  => (new CategoryRepo())->treeWithCounts(),
        ]);
    }

    /**
     * GET /pinboard/graph?pins=1,2,3[&open=4&take=12]
     *
     * Returns the full board state (not a diff) for the given pins, optionally
     * expanding one more step from `open`. `open` returns only a batch (`take`,
     * busiest first) rather than a hub's whole neighbourhood.
     */
    public function graph(): never
    {
        $pins = self::idList($_GET['pins'] ?? '');
        $open = (int) ($_GET['open'] ?? 0);
        // Bounded by remaining board room, not a fixed batch size.
        $take = max(1, min(PinboardRepo::MAX_PINS, (int) ($_GET['take'] ?? 12)));

        // Hidden archives are excluded before batching, not filtered after.
        $hidden = self::idList($_GET['hidden'] ?? '');
        $more = 0;

        if ($open > 0) {
            $pins[] = $open;
            $pins = array_values(array_unique($pins));

            $found = array_values(array_diff($this->pinboard->neighbours($open, $hidden), $pins));
            $found = $this->pinboard->rank($found);

            $room = min($take, PinboardRepo::MAX_PINS - count($pins));
            $more = max(0, count($found) - max(0, $room));
            $found = array_slice($found, 0, max(0, $room));

            $pins = array_merge($pins, $found);
        }

        $board = $this->pinboard->board($pins, $hidden);

        json_response([
            'ok'    => true,
            'more'  => $more, // neighbours of the opened pin that did not fit
            'full'  => count($pins) >= PinboardRepo::MAX_PINS,
            'limit' => PinboardRepo::MAX_PINS,
            'nodes' => $board['nodes'],
            'edges' => $board['edges'],
        ]);
    }

    /** POST /pinboard/connect — ties two entries together, writing the same rows the connections rail uses. */
    public function connect(): never
    {
        $a = (int) ($_POST['a'] ?? 0);
        $b = (int) ($_POST['b'] ?? 0);

        if ($a <= 0 || $b <= 0 || $a === $b) {
            json_response(['ok' => false, 'error' => t('Two different entries are needed.')], 400);
        }

        if ($this->entries->find($a) === null || $this->entries->find($b) === null) {
            json_response(['ok' => false, 'error' => t('One of those entries is gone.')], 404);
        }

        $made = (new ConnectionRepo())->connect(
            ConnectionRepo::ENTRY, $a,
            ConnectionRepo::ENTRY, $b,
            $_POST['note'] ?? null
        );

        json_response([
            'ok'    => true,
            'added' => $made, // false just means it already existed, not an error
        ]);
    }

    /**
     * POST /pinboard/link — points one entry at another through a relation field.
     * Re-validates the field server-side, since the client's copy of the rules may be stale.
     */
    public function link(): never
    {
        $entryId = (int) ($_POST['entry'] ?? 0);
        $fieldId = (int) ($_POST['field'] ?? 0);
        $targetId = (int) ($_POST['target'] ?? 0);

        if ($entryId <= 0 || $targetId <= 0 || $entryId === $targetId) {
            json_response(['ok' => false, 'error' => t('Two different entries are needed.')], 400);
        }

        $entry = $this->entries->find($entryId);
        $target = $this->entries->find($targetId);

        if ($entry === null || $target === null) {
            json_response(['ok' => false, 'error' => t('One of those entries is gone.')], 404);
        }

        $field = $this->pinboard->relationField($entryId, $fieldId);
        if ($field === null) {
            json_response([
                'ok'    => false,
                'error' => t('That entry has no such field to point with.'),
            ], 422);
        }

        if ($field['targets'] !== []
            && !in_array((int) $target['category_id'], $field['targets'], true)) {
            json_response([
                'ok'    => false,
                'error' => t('“%s” cannot point at that archive.', $field['label']),
            ], 422);
        }

        $replaced = $this->pinboard->addLink($entryId, $field, $targetId);

        json_response(['ok' => true, 'replaced' => $replaced]);
    }

    /** POST /pinboard/unlink — removes one target from a relation field. */
    public function unlink(): never
    {
        $id = (int) ($_POST['link'] ?? 0);
        if ($id <= 0) {
            json_response(['ok' => false, 'error' => t('Nothing to unlink.')], 400);
        }

        $this->pinboard->removeLink($id);

        json_response(['ok' => true]);
    }

    /** POST /pinboard/disconnect — cuts a connection. */
    public function disconnect(): never
    {
        $id = (int) ($_POST['connection'] ?? 0);
        if ($id <= 0) {
            json_response(['ok' => false, 'error' => t('Nothing to cut.')], 400);
        }

        (new ConnectionRepo())->remove($id);

        json_response(['ok' => true]);
    }

    /** POST /pinboard/note — changes what a connection's string is labelled. */
    public function note(): never
    {
        $id = (int) ($_POST['connection'] ?? 0);
        if ($id <= 0) {
            json_response(['ok' => false, 'error' => t('Nothing to update.')], 400);
        }

        (new ConnectionRepo())->updateNote($id, (string) ($_POST['note'] ?? ''));

        json_response(['ok' => true]);
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
