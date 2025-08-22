<?php
// File: config/config.php

/**
 * Load file .env ke environment
 */
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
        [$name, $value] = explode('=', $line, 2);
        $name  = trim($name);
        $value = trim($value);

        putenv("$name=$value");   // set environment
        $_ENV[$name]    = $value; // opsional
        $_SERVER[$name] = $value; // opsional
    }
}

/**
 * Ambil value dari .env / environment
 */
function env(string $key, ?string $default = null): ?string {
    $val = getenv($key);
    if ($val === false) {
        return $default;
    }
    return $val;
}

// === load otomatis saat config.php di-include ===
load_env();
