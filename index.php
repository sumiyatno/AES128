<?php
// index.php (root project)

// --- Error Logging ---
ini_set('display_errors', 0); // jangan tampilkan ke user
ini_set('log_errors', 1);

// Pastikan folder logs/ ada
$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0777, true);
}

ini_set('error_log', $logDir . '/error.log');

// Custom error handler biar lebih detail
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    $message = "[" . date("Y-m-d H:i:s") . "] ERROR {$errno}: {$errstr} in {$errfile} on line {$errline}\n";
    error_log($message, 3, __DIR__ . "/logs/error.log");
    return true; // jangan biarkan PHP default tampilkan
});

set_exception_handler(function ($exception) {
    $message = "[" . date("Y-m-d H:i:s") . "] UNCAUGHT EXCEPTION: " . $exception->getMessage() .
        " in " . $exception->getFile() . " on line " . $exception->getLine() . "\n";
    error_log($message, 3, __DIR__ . "/logs/error.log");
    http_response_code(500);
    echo "<h3>Terjadi kesalahan. Cek logs/error.log untuk detail.</h3>";
});

// --- Redirect ke dashboard ---
header("Location: views/dashboard.php");
exit;
