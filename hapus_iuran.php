<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_admin();

$allowedRedirects = ['kas.php', 'riwayat_iuran.php', 'dashboard_admin.php'];

$redirectTo = post('redirect_to', 'riwayat_iuran.php');
if (!in_array($redirectTo, $allowedRedirects, true)) {
    $redirectTo = 'riwayat_iuran.php';
}

if (!is_post()) {
    flash('warning', 'Aksi hapus harus dilakukan melalui tombol yang tersedia.');
    redirect($redirectTo);
}

verify_csrf();

$id = post_int('id');
$transaksi = $id > 0
    ? db_one(
        $conn,
        'SELECT kas.id, kas.bulan, kas.tahun, anggota.nama
         FROM kas
         LEFT JOIN anggota ON anggota.id = kas.anggota_id
         WHERE kas.id = ?
         LIMIT 1',
        [$id]
    )
    : null;

if ($transaksi === null) {
    flash('danger', 'Transaksi iuran tidak ditemukan atau sudah dihapus.');
    redirect($redirectTo);
}

try {
    $stmt = db_run($conn, 'DELETE FROM kas WHERE id = ?', [$id]);
    $stmt->close();

    flash(
        'success',
        'Transaksi iuran ' . ($transaksi['nama'] ?? 'anggota') . ' periode '
        . $transaksi['bulan'] . ' ' . $transaksi['tahun'] . ' berhasil dihapus.'
    );
} catch (Throwable $exception) {
    error_log('[KOPERASI] Gagal menghapus iuran: ' . $exception->getMessage());
    flash('danger', 'Transaksi iuran gagal dihapus. Silakan coba lagi.');
}

redirect($redirectTo);
