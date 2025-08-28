<?php
// File: config/config.php

/**
 * Load file .env ke environment
 */
if (!function_exists('load_env')) {
    function load_env(string $path = __DIR__ . '/.env'): void {
        if (!file_exists($path)) return;

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);

            // skip komentar
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            // parse KEY=VALUE
            [$key, $value] = explode('=', $line, 2);
            putenv(trim($key) . '=' . trim($value));
        }
    }
}

/**
 * Get environment variable
 */
if (!function_exists('env')) {
    function env(string $key, ?string $default = null): ?string {
        return getenv($key) ?: $default;
    }
}

// === load otomatis saat config.php di-include ===
load_env();
