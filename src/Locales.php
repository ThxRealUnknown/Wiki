<?php

namespace App;

/** The languages the interface can be shown in. New locales are added here, and need a matching lang/language_{code}.php. */
final class Locales
{
    private const LOCALES = [
        'en' => 'English',
        'de' => 'Deutsch',
    ];

    public const DEFAULT = 'en';

    /** @return array<string, string> code => native name */
    public static function all(): array
    {
        return self::LOCALES;
    }

    public static function exists(string $code): bool
    {
        return isset(self::LOCALES[$code]);
    }

    /** A locale code that is certainly real, falling back to the default. */
    public static function resolve(?string $code): string
    {
        return $code !== null && self::exists($code) ? $code : self::DEFAULT;
    }
}
