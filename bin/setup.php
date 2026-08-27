<?php

/**
 * Brings an existing site's content onto this copy of the project: applies
 * any pending migrations, then restores the newest backup/*.json if the
 * database is still empty.
 *
 *   php bin\setup.php
 *   php bin\setup.php --restore    also merges the newest backup even if
 *                                  the database already has content
 */

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Database;
use App\Export\JsonBackup;
use App\Guid;
use App\Migrator;

$argv = $argv ?? [];
$restore = in_array('--restore', $argv, true);

say('Worldbuilder setup');
say('  project: ' . APP_ROOT);
say('');

// ------------------------------------------------------ 1. the database

// Opening the connection creates the SQLite file if it is not there yet.
$db = Database::connect();

$applied = (new Migrator($db))->run(static fn (string $v) => say('  · applied ' . $v));
say($applied === [] ? '  ✓ schema up to date' : '  ✓ schema created');

$filled = Guid::backfill($db);
if ($filled > 0) {
    say("  ✓ {$filled} identifier(s) backfilled");
}

$entries = (int) $db->value('SELECT COUNT(*) FROM entries');
say("  entries present: {$entries}");
say('');

// --------------------------------------------------- 2. the content

$newest = newestBackup();

if ($entries > 0 && !$restore) {
    say('This database already has content — no backup was loaded.');
    say('  Pass --restore to merge the newest backup in anyway.');
} elseif ($newest === null) {
    warn('No backup found in ' . APP_ROOT . '\\backup');
    say('  The site will start empty. Copy a backup JSON in there and run:');
    say('    php bin\\setup.php --restore');
} else {
    say('restoring: ' . basename($newest));
    try {
        $tally = (new JsonBackup($db))->import((string) file_get_contents($newest), false);
        $created = $tally['categories_created'] + $tally['layouts_created']
            + $tally['fields_created'] + $tally['entries_created'] + $tally['chapters_created'];
        $updated = $tally['categories_updated'] + $tally['layouts_updated']
            + $tally['fields_updated'] + $tally['entries_updated'] + $tally['chapters_updated'];
        say("  ✓ {$created} created, {$updated} updated, {$tally['connections_added']} connections");
    } catch (Throwable $e) {
        warn('Restore failed, nothing was changed: ' . $e->getMessage());
    }
}

say('');
say('Done. Double-click serve.bat, then open http://localhost:8080/');

// ------------------------------------------------------------------ helpers

/** The most recent JSON backup shipped alongside the project. */
function newestBackup(): ?string
{
    $files = glob(APP_ROOT . '/backup/*.json') ?: [];
    if ($files === []) {
        return null;
    }

    usort($files, static fn ($a, $b) => filemtime($b) <=> filemtime($a));

    return $files[0];
}

function say(string $line): void
{
    echo $line, PHP_EOL;
}

function warn(string $line): void
{
    echo '  ! ', $line, PHP_EOL;
}
