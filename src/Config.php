<?php

namespace App;

final class Config
{
    private static ?array $data = null;

    public static function all(): array
    {
        if (self::$data === null) {
            $base = require dirname(__DIR__) . '/config/config.php';
            $localPath = dirname(__DIR__) . '/config/config.local.php';
            if (is_file($localPath)) {
                $local = require $localPath;
                if (is_array($local)) {
                    $base = self::mergeDeep($base, $local);
                }
            }
            self::$data = $base;
        }

        return self::$data;
    }

    /**
     * Dot-notation lookup: Config::get('sqlite.path').
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $value = self::all();
        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    private static function mergeDeep(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            if (is_array($value) && isset($base[$key]) && is_array($base[$key])) {
                $base[$key] = self::mergeDeep($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }

        return $base;
    }
}
