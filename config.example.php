<?php

declare(strict_types=1);

/**
 * Contoh konfigurasi lokal.
 *
 * Salin file ini menjadi config.local.php, lalu sesuaikan nilainya.
 * config.local.php sudah dikecualikan oleh .gitignore dan tidak boleh
 * di-commit atau dibagikan.
 *
 * Environment variable (KOPERASI_DB_*) tetap memiliki prioritas lebih tinggi.
 */

return [
    'host' => '127.0.0.1',
    'port' => 3306,
    'user' => 'root',
    'pass' => '',
    'name' => 'db_koperasi',
];
