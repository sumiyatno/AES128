<?php
function load_env(string $path = __DIR__ . '/.env'): void {
    if (!file_exists($path)) return;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#')) continue;
        [$name, $value] = explode('=', $line, 2);
        $name  = trim($name);
        $value = trim($value);
        putenv("$name=$value");       // set environment
        $_ENV[$name] = $value;        // opsional
        $_SERVER[$name] = $value;     // opsional
    }
}
