<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_login();

$pengaturan = pengaturan_pembayaran($conn);
$nama = (string) ($pengaturan['qris_gambar'] ?? '');
$path = path_berkas_storage($nama, 'qris');

if ($path === null) {
    abort_request(404, 'Gambar QRIS belum tersedia.');
}

$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$mime = match ($ext) {
    'png' => 'image/png',
    'webp' => 'image/webp',
    default => 'image/jpeg',
};

$ukuran = @filesize($path);

header('Content-Type: ' . $mime);
header('Content-Disposition: inline; filename="qris-koperasi.' . $ext . '"');
header('X-Content-Type-Options: nosniff');
if ($ukuran !== false) {
    header('Content-Length: ' . $ukuran);
}

readfile($path);
exit;
