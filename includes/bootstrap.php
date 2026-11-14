<?php

declare(strict_types=1);

/**
 * Bootstrap aplikasi: konfigurasi PHP, session, koneksi, helper, dan layout.
 * Semua halaman wajib meng-include file ini paling atas.
 */

if (!defined('APP_DEBUG')) {
    define('APP_DEBUG', getenv('KOPERASI_DEBUG') === '1');
}

error_reporting(E_ALL);
ini_set('display_errors', APP_DEBUG ? '1' : '0');
ini_set('log_errors', '1');

date_default_timezone_set('Asia/Jakarta');

if (session_status() !== PHP_SESSION_ACTIVE) {
    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

if (!headers_sent()) {
    header('Content-Type: text/html; charset=UTF-8');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header('Content-Security-Policy: default-src \'self\'; base-uri \'self\'; '
        . 'form-action \'self\'; frame-ancestors \'self\'; object-src \'none\'; '
        . 'img-src \'self\' data:; style-src \'self\' \'unsafe-inline\'; '
        . 'script-src \'self\' \'unsafe-inline\'');
    // Data koperasi tidak boleh tersimpan di cache browser bersama.
    header('Cache-Control: no-store, private');
}

require_once __DIR__ . '/../koneksi.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/report.php';
require_once __DIR__ . '/upload.php';
require_once __DIR__ . '/pembayaran.php';

/**
 * Tampilkan error yang tidak tertangani dengan pesan yang aman.
 */
set_exception_handler(static function (Throwable $exception): void {
    error_log('[KOPERASI] ' . $exception->getMessage() . ' in ' . $exception->getFile() . ':' . $exception->getLine());

    if (!headers_sent()) {
        http_response_code(500);
    }

    $detail = '';
    if (APP_DEBUG) {
        $detail = '<pre style="white-space:pre-wrap;background:#0f172a;color:#e2e8f0;padding:16px;'
            . 'border-radius:10px;overflow:auto;font-size:12px">'
            . e($exception->getMessage() . "\n\n" . $exception->getTraceAsString())
            . '</pre>';
    }

    echo '<!doctype html><html lang="id"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>Terjadi Kesalahan</title></head>'
        . '<body style="font-family:Arial,sans-serif;background:#f1f5f9;margin:0;padding:40px">'
        . '<div style="max-width:720px;margin:auto;background:#fff;border:1px solid #e2e8f0;'
        . 'border-radius:14px;padding:28px;box-shadow:0 10px 30px rgba(15,23,42,.08)">'
        . '<h1 style="margin:0 0 12px;font-size:22px;color:#0f172a">Terjadi kesalahan pada sistem</h1>'
        . '<p style="color:#475569;line-height:1.6">Silakan kembali dan coba lagi. '
        . 'Jika masalah berlanjut, hubungi administrator.</p>'
        . $detail
        . '<p style="margin-top:20px"><a href="index.php" style="color:#0f766e;font-weight:600">'
        . 'Kembali ke halaman utama</a></p></div></body></html>';
});
