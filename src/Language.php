<?php

namespace App;

/**
 * The app's own UI text, in whichever language is set in Settings. Every
 * translatable string is keyed by its own English text — language_en.php is
 * an identity map (every value equals its key) so the lookup never needs to
 * special-case English, and a missing key in any other language falls back
 * to that same English text automatically.
 */
final class Language
{
    private static ?array $cache = null;
    private static ?string $cachedLocale = null;

    public static function locale(): string
    {
        return Locales::resolve(Settings::get(Settings::LOCALE));
    }

    /** @return array<string, string> */
    public static function catalog(): array
    {
        $locale = self::locale();
        if (self::$cache !== null && self::$cachedLocale === $locale) {
            return self::$cache;
        }

        $path = APP_ROOT . '/lang/language_' . $locale . '.php';
        self::$cache = is_file($path) ? (require $path) : [];
        self::$cachedLocale = $locale;

        return self::$cache;
    }

    public static function t(string $key, mixed ...$args): string
    {
        $text = self::catalog()[$key] ?? $key;

        return $args === [] ? $text : vsprintf($text, $args);
    }

    /** Binary plural — correct for both English and German, which are both simple two-form languages. */
    public static function tn(int $count, string $singular, string $plural, mixed ...$args): string
    {
        return self::t($count === 1 ? $singular : $plural, $count, ...$args);
    }

    /** The current locale's whole catalog, for embedding into a page as JSON for app.js. */
    public static function forJs(): array
    {
        return self::catalog();
    }
}
