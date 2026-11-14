<?php

declare(strict_types=1);

$configPath = __DIR__ . '/config.local.php';
$localConfig = is_file($configPath) ? require $configPath : [];
if (!is_array($localConfig)) {
    $localConfig = [];
}

$dbHost = getenv('KOPERASI_DB_HOST') ?: (string) ($localConfig['host'] ?? '127.0.0.1');
$dbPort = (int) (getenv('KOPERASI_DB_PORT') ?: ($localConfig['port'] ?? 3306));
$dbUser = getenv('KOPERASI_DB_USER') ?: (string) ($localConfig['user'] ?? 'root');
$dbPass = getenv('KOPERASI_DB_PASS') ?: (string) ($localConfig['pass'] ?? '');
$dbName = getenv('KOPERASI_DB_NAME') ?: (string) ($localConfig['name'] ?? 'db_koperasi');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName, $dbPort);
    $conn->set_charset('utf8mb4');
} catch (mysqli_sql_exception $exception) {
    http_response_code(500);
    error_log('Koneksi database gagal: ' . $exception->getMessage());

    $detail = getenv('KOPERASI_DEBUG') === '1'
        ? '<br><small>' . htmlspecialchars($exception->getMessage(), ENT_QUOTES, 'UTF-8') . '</small>'
        : '';

    exit(
        '<!doctype html><html lang="id"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>Koneksi Gagal</title></head>'
        . '<body style="font-family:Arial,sans-serif;background:#f1f5f9;margin:0;padding:40px">'
        . '<div style="max-width:560px;margin:auto;background:#fff;border:1px solid #e2e8f0;'
        . 'border-radius:14px;padding:28px;box-shadow:0 10px 30px rgba(15,23,42,.08)">'
        . '<h1 style="margin:0 0 12px;font-size:22px;color:#0f172a">Koneksi database gagal</h1>'
        . '<p style="color:#475569;line-height:1.6">Pastikan MySQL/MariaDB sudah berjalan dan '
        . 'database <strong>' . htmlspecialchars($dbName, ENT_QUOTES, 'UTF-8') . '</strong> sudah diimpor.</p>'
        . $detail
        . '</div></body></html>'
    );
}