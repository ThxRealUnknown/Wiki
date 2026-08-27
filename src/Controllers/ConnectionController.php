<?php

namespace App\Controllers;

use App\ChapterRepo;
use App\ConnectionRepo;
use App\EntryRepo;

final class ConnectionController
{
    private ConnectionRepo $connections;

    public function __construct()
    {
        $this->connections = new ConnectionRepo();
    }

    public function store(): never
    {
        $fromType = self::type($_POST['from_type'] ?? '');
        $toType = self::type($_POST['to_type'] ?? '');
        $fromId = (int) ($_POST['from_id'] ?? 0);
        $toId = (int) ($_POST['to_id'] ?? 0);

        if ($fromType === null || $toType === null || $fromId <= 0 || $toId <= 0) {
            flash(t('Pick something to connect to first.'), 'error');
            $this->back();
        }

        if (!$this->exists($fromType, $fromId) || !$this->exists($toType, $toId)) {
            flash(t('One of those no longer exists.'), 'error');
            $this->back();
        }

        $made = $this->connections->connect(
            $fromType,
            $fromId,
            $toType,
            $toId,
            (string) ($_POST['note'] ?? '')
        );

        flash($made ? t('Connected.') : t('Those two are already connected.'), $made ? 'success' : 'error');
        $this->back();
    }

    public function update(int $connectionId): never
    {
        $this->connections->updateNote($connectionId, (string) ($_POST['note'] ?? ''));
        flash(t('Connection updated.'));
        $this->back();
    }

    public function destroy(int $connectionId): never
    {
        $this->connections->remove($connectionId);
        flash(t('Connection removed.'));
        $this->back();
    }

    private function exists(string $type, int $id): bool
    {
        return $type === ConnectionRepo::ENTRY
            ? (new EntryRepo())->find($id) !== null
            : (new ChapterRepo())->find($id) !== null;
    }

    private static function type(mixed $value): ?string
    {
        return in_array($value, [ConnectionRepo::ENTRY, ConnectionRepo::CHAPTER], true)
            ? (string) $value
            : null;
    }

    /** Redirects back to return_to if it's a same-site path, so it can't be used to bounce the browser off-site. */
    private function back(): never
    {
        $target = (string) ($_POST['return_to'] ?? '');

        if ($target === '' || !str_starts_with($target, '/') || str_starts_with($target, '//')) {
            redirect('/');
        }

        header('Location: ' . $target);
        exit;
    }
}
