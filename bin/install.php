<?php

/**
 * Creates the SQLite database file, applies the schema, and seeds a starter
 * set of archives. Safe to run more than once: it skips anything that already
 * exists.
 *
 *   php bin\install.php
 *   php bin\install.php --fresh    (drops everything first)
 */

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Config;
use App\Database;
use App\FieldTypes;

$fresh = in_array('--fresh', $argv ?? [], true);
$dbFile = (string) Config::get('sqlite.path', 'data/worldbuilder.sqlite');

say("File: {$dbFile}");
say('');

// Opening the connection creates the file if it is not there yet.
$db = Database::connect();
say('✓ Database file ready.');

// ------------------------------------------------------------------- schema

if ($fresh) {
    say('… dropping existing tables (--fresh)');
    $tables = [
        'schema_migrations', 'connections', 'chapters', 'entry_revisions', 'world_maps', 'settings',
        'entry_links', 'entry_values', 'entries', 'layout_fields', 'layouts', 'categories',
    ];
    foreach ($tables as $table) {
        $db->pdo()->exec('DROP TABLE IF EXISTS ' . $table);
    }
}

$migrator = new App\Migrator($db);
$appliedNow = $migrator->run(static fn (string $version) => say("  · applied {$version}"));

say($appliedNow === []
    ? '✓ ' . t('Schema already up to date.')
    : '✓ ' . tn(count($appliedNow), 'Schema updated (%d migration).', 'Schema updated (%d migrations).'));

// Rows created before guids existed, or by a migration that cannot generate
// them, get one here.
$filled = App\Guid::backfill($db, static fn (string $line) => say('  · ' . $line));
if ($filled > 0) {
    say("✓ Backfilled {$filled} identifier(s).");
}

// --------------------------------------------------------------------- seed

$entryCount = (int) $db->value('SELECT COUNT(*) FROM categories');
if ($entryCount > 0) {
    say('✓ Data already present — nothing seeded.');
    say('');
    say('Done. Open ' . rtrim((string) Config::get('base_path'), '/') . '/ in your browser.');
    exit(0);
}

say('… seeding starter archives');

$seed = require APP_ROOT . '/bin/seed_data.php';

// Categories first, so a relation field can be pointed at an archive that is
// defined further down the seed file.
$categoryIds = [];
foreach ($seed as $categorySeed) {
    $categoryIds[$categorySeed['name']] = $db->insert('categories', [
        'name'        => $categorySeed['name'],
        'slug'        => slugify($categorySeed['name']),
        'icon'        => $categorySeed['icon'],
        'color'       => $categorySeed['color'],
        'description' => $categorySeed['description'] ?? null,
        'sort_order'  => $categorySeed['sort_order'],
        'created_at'  => now(),
        'updated_at'  => now(),
    ]);
}

foreach ($seed as $categorySeed) {
    $categoryId = $categoryIds[$categorySeed['name']];

    // Most categories carry one layout under 'layout'; one with more than one
    // (Locations, say) lists them all under 'layouts' instead.
    $layoutDefs = $categorySeed['layouts'] ?? [$categorySeed['layout']];

    foreach ($layoutDefs as $index => $layoutDef) {
        $layoutId = $db->insert('layouts', [
            'category_id' => $categoryId,
            'name'        => $layoutDef['name'],
            'is_default'  => $index === 0 ? 1 : 0,
            'sort_order'  => $index,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $sortOrder = 0;
        foreach ($layoutDef['fields'] as $field) {
            $type = $field['type'];
            $rawConfig = $field['config'] ?? [];

            // The seed names its relation targets; translate to the real id.
            if (isset($rawConfig['target'])) {
                $rawConfig['target_category_id'] = $categoryIds[$rawConfig['target']] ?? null;
                unset($rawConfig['target']);
            }

            $config = FieldTypes::normaliseConfig($type, $rawConfig);

            $db->insert('layout_fields', [
                'layout_id'  => $layoutId,
                'field_key'  => str_replace('-', '_', slugify($field['label'], 'field')),
                'label'      => $field['label'],
                'field_type' => $type,
                'help'       => $field['help'] ?? null,
                'width'      => $field['width'] ?? 'full',
                'config'     => json_encode($config, JSON_UNESCAPED_UNICODE),
                'sort_order' => $sortOrder++,
            ]);
        }

        say("  · {$categorySeed['icon']}  {$categorySeed['name']} — layout \"{$layoutDef['name']}\"");
    }
}

say('');
say('✓ Seeded ' . count($seed) . ' archives.');
say('');
say('Done. Open http://localhost' . rtrim((string) Config::get('base_path'), '/') . '/ in your browser.');

// ------------------------------------------------------------------ helpers

/** @return array<int, string> */
function splitStatements(string $sql): array
{
    $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;

    return array_values(array_filter(
        array_map('trim', explode(';', $sql)),
        static fn (string $s) => $s !== ''
    ));
}

function say(string $line): void
{
    echo $line, PHP_EOL;
}

function fail(string $message): never
{
    echo PHP_EOL, '✗ ', $message, PHP_EOL;
    exit(1);
}
