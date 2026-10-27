<?php

/**
 * ClassPulse - Environment Configuration Loader
 * Loads .env file variables into the environment if present.
 */
class EnvLoader {
    public static function load(string $path = null): void {
        $path = $path ?? dirname(__DIR__) . '/.env';
        if (!file_exists($path)) return;

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if (str_starts_with($line, '#') || !str_contains($line, '=')) continue;
            [$key, $value] = explode('=', $line, 2);
            $key   = trim($key);
            $value = trim($value, " \t\n\r\0\x0B\"'");
            if (!getenv($key)) {
                putenv("{$key}={$value}");
                $_ENV[$key]    = $value;
                $_SERVER[$key] = $value;
            }
        }
    }
}

EnvLoader::load();
