<?php

/**
 * Wipes the whole database and rebuilds it from bin/seed_data.php — the same
 * end state as `php bin\install.php --fresh`, but asks for confirmation
 * first and writes a full backup before touching anything.
 *
 *   php bin\reset_to_seed.php
 *   php bin\reset_to_seed.php --yes    (skip the confirmation prompt)
 *
 * Only the database is in scope: uploaded files under public/uploads are
 * left alone (some may end up unused if nothing still points at them).
 */

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Export\JsonBackup;

$skipConfirm = in_array('--yes', $argv ?? [], true) || in_array('-y', $argv ?? [], true);

echo "This deletes EVERY entry, chapter, connection, archive and layout in this\n";
echo "wiki, and replaces them with the starter archives from bin/seed_data.php.\n";
echo "A full backup is written first — everything can be restored from it — but\n";
echo "this is not otherwise reversible.\n\n";

if (!$skipConfirm) {
    echo "Type y to continue, anything else to cancel: ";
    $answer = strtolower(trim((string) fgets(STDIN)));

    if ($answer !== 'y' && $answer !== 'yes') {
        echo "Cancelled. Nothing was changed.\n";
        exit(0);
    }
    echo "\n";
}

echo "Backing up...\n";
$json = (new JsonBackup())->export();
$backupDir = dirname(__DIR__) . '/backup';
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0777, true);
}
$backupName = 'worldbuilder-backup-' . date('Y-m-d-Hi') . '-before-reset.json';
file_put_contents($backupDir . '/' . $backupName, $json);
echo '✓ Backup written to backup/' . $backupName . ' (' . number_format(strlen($json)) . " bytes).\n\n";

echo "Resetting...\n\n";
$exitCode = 0;
passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/install.php') . ' --fresh', $exitCode);

exit($exitCode);
