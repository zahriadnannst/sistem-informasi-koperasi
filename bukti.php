<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_login();

$user = current_user();
$id = get_int('id');

if ($id <= 0) {
    abort_request(404, 'Bukti pembayaran tidak ditemukan.');
}

if (is_admin()) {
    $pembayaran = db_one($conn, 'SELECT id, bukti, bukti_mime FROM pembayaran WHERE id = ? LIMIT 1', [$id]);
} else {
    $dbUser = db_one($conn, 'SELECT anggota_id FROM users WHERE id = ? LIMIT 1', [$user['id']]);
    $anggotaId = $dbUser !== null && $dbUser['anggota_id'] !== null ? (int) $dbUser['anggota_id'] : 0;

    if ($anggotaId <= 0) {
        abort_request(403, 'Anda tidak berhak mengakses bukti pembayaran ini.');
    }

    // Hanya boleh mengakses bukti miliknya sendiri.
    $pembayaran = db_one(
        $conn,
        'SELECT id, bukti, bukti_mime FROM pembayaran WHERE id = ? AND anggota_id = ? LIMIT 1',
        [$id, $anggotaId]
    );
}

if ($pembayaran === null) {
    abort_request(404, 'Bukti pembayaran tidak ditemukan atau bukan milik Anda.');
}

$nama = (string) ($pembayaran['bukti'] ?? '');
$path = path_berkas_storage($nama, 'bukti');

if ($path === null) {
    abort_request(404, 'Berkas bukti pembayaran tidak tersedia.');
}

$mime = (string) ($pembayaran['bukti_mime'] ?? '');
$diizinkan = upload_mime_diizinkan();
if (!isset($diizinkan[$mime])) {
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $mime = match ($ext) {
        'png' => 'image/png',
        'webp' => 'image/webp',
        default => 'image/jpeg',
    };
}

$ukuran = @filesize($path);

header('Content-Type: ' . $mime);
header('Content-Disposition: inline; filename="bukti-pembayaran-' . $id . '.' . ($diizinkan[$mime] ?? 'jpg') . '"');
header('X-Content-Type-Options: nosniff');
header('Content-Security-Policy: default-src \'none\'; img-src \'self\'; sandbox');
if ($ukuran !== false) {
    header('Content-Length: ' . $ukuran);
}

readfile($path);
exit;
