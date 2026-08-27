<?php

namespace App;

/**
 * Settings the site holds about itself and can change from the interface —
 * as opposed to config/config.php, which describes the machine it runs on.
 *
 * Read once per request and cached, since the shell asks for the banner on
 * every single page.
 */
final class Settings
{
    public const SITE_BANNER = 'site_banner';
    public const MAP_TITLE = 'map_title';
    public const MAP_EPOCH = 'map_epoch';
    public const FEATURE_BOOK = 'feature_book';
    public const FEATURE_MAP = 'feature_map';
    public const FEATURE_CONNECTIONS = 'feature_connections';
    public const FEATURE_TIMELINE = 'feature_timeline';
    public const TIMELINE_EPOCH_NAME = 'timeline_epoch_name';
    public const TIMELINE_EPOCH_ABBR = 'timeline_epoch_abbr';
    public const LOCALE = 'locale';
    public const DRAFT_GOAL_WORDS = 'draft_goal_words';
    public const CALENDAR_CONFIG = 'calendar_config';

    private static ?array $cache = null;

    /** @return array<string, string|null> */
    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $db = Database::instance();

        // Table may not exist yet if migrations haven't run.
        if (!$db->tableExists('settings')) {
            return self::$cache = [];
        }

        $out = [];
        foreach ($db->all('SELECT name, value FROM settings') as $row) {
            $out[$row['name']] = $row['value'];
        }

        return self::$cache = $out;
    }

    public static function get(string $name, ?string $default = null): ?string
    {
        $value = self::all()[$name] ?? null;

        return ($value === null || $value === '') ? $default : $value;
    }

    public static function set(string $name, ?string $value): void
    {
        $db = Database::instance();

        $existing = $db->first('SELECT id FROM settings WHERE name = :n', ['n' => $name]);

        if ($existing === null) {
            $db->insert('settings', [
                'name'       => $name,
                'value'      => $value,
                'updated_at' => now(),
            ]);
        } else {
            $db->update('settings', (int) $existing['id'], [
                'value'      => $value,
                'updated_at' => now(),
            ]);
        }

        self::$cache = null;
    }

    /** A setting that is really on/off, defaulting to on until turned off. */
    public static function flag(string $name, bool $default = true): bool
    {
        return self::get($name, $default ? '1' : '0') === '1';
    }

    public static function setFlag(string $name, bool $value): void
    {
        self::set($name, $value ? '1' : '0');
    }

    public static function forget(string $name): void
    {
        Database::instance()->run('DELETE FROM settings WHERE name = :n', ['n' => $name]);
        self::$cache = null;
    }
}
