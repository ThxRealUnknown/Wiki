<?php

/**
 * Diffs every lang/language_*.php file against the English catalog and
 * reports which keys are missing (or, for a non-English file, still equal
 * to their English text — a likely sign nobody has translated it yet).
 *
 *   php bin\check_translations.php
 */

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Locales;

$langDir = APP_ROOT . '/lang';
$english = require $langDir . '/language_en.php';

$exitCode = 0;

foreach (Locales::all() as $code => $name) {
    if ($code === 'en') {
        continue;
    }

    $path = $langDir . '/language_' . $code . '.php';
    if (!is_file($path)) {
        echo "✗ {$name} ({$code}): no {$path}\n";
        $exitCode = 1;
        continue;
    }

    $catalog = require $path;
    $missing = array_diff_key($english, $catalog);
    $untranslated = [];
    foreach ($catalog as $key => $value) {
        if ($value === $key) {
            $untranslated[] = $key;
        }
    }

    echo "{$name} ({$code}): " . count($english) . " keys total\n";

    if ($missing !== []) {
        echo '  ✗ ' . count($missing) . " missing entirely:\n";
        foreach (array_keys($missing) as $key) {
            echo "    - {$key}\n";
        }
        $exitCode = 1;
    }

    if ($untranslated !== []) {
        echo '  ⚠ ' . count($untranslated) . " left equal to the English text:\n";
        foreach ($untranslated as $key) {
            echo "    - {$key}\n";
        }
    }

    if ($missing === [] && $untranslated === []) {
        echo "  ✓ fully translated\n";
    }
}

exit($exitCode);
