<?php

/**
 * Shown when the database is unreachable or the schema has not been created.
 * Deliberately standalone: it must render without touching the database.
 *
 * @var Throwable $e
 */

$reason = isset($e) ? $e->getMessage() : '';
$notInstalled = str_contains($reason, 'not installed');
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Setup needed · Worldbuilder</title>
    <link rel="stylesheet" href="<?= e(asset('assets/css/app.css')) ?>">
</head>
<body>
<div class="single-col" style="height:100vh; overflow:auto">
    <div class="page">
        <div class="page-head">
            <h1 class="page-title">Almost there</h1>
            <p class="lede">
                <?= $notInstalled
                    ? 'The database is reachable, but the tables have not been created yet.'
                    : 'The site cannot reach its database.' ?>
            </p>
        </div>

        <?php if (!$notInstalled && $reason !== ''): ?>
            <div class="notice notice--warn"><?= e($reason) ?></div>
        <?php endif; ?>

        <h2 class="section-title">Run the installer</h2>
        <p>From the project folder, in a terminal:</p>
        <pre class="prose" style="background:var(--bg-panel); padding:14px 16px; border-radius:9px; border:1px solid var(--border)">php bin\install.php</pre>

        <div class="section">
            <h2 class="section-title">Checklist</h2>
            <ul class="row-list">
                <li class="row-item">
                    <div class="row-main">
                        <div class="row-title">The data folder is writable</div>
                        <div class="row-sub">
                            SQLite creates <code>data/worldbuilder.sqlite</code> itself the first
                            time it connects — check the folder isn't read-only.
                        </div>
                    </div>
                </li>
            </ul>
        </div>
    </div>
</div>
</body>
</html>
