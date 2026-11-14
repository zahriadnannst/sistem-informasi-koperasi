<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_admin();

if (!is_post()) {
    flash('warning', 'Aksi hapus harus dilakukan melalui tombol yang tersedia.');
    redirect('anggota.php');
}

verify_csrf();

$id = post_int('id');
$anggota = $id > 0
    ? db_one($conn, 'SELECT id, nama FROM anggota WHERE id = ? LIMIT 1', [$id])
    : null;

if ($anggota === null) {
    flash('danger', 'Data anggota tidak ditemukan.');
    redirect('anggota.php');
}

$jumlahIuran = (int) db_value($conn, 'SELECT COUNT(*) FROM kas WHERE anggota_id = ?', [$id]);

if ($jumlahIuran > 0) {
    flash(
        'danger',
        'Anggota "' . $anggota['nama'] . '" memiliki ' . $jumlahIuran
        . ' riwayat iuran sehingga tidak dapat dihapus. Hapus riwayat iuran terlebih dahulu bila memang diperlukan.'
    );
    redirect('anggota.php');
}

$conn->begin_transaction();

try {
    $stmt = db_run($conn, 'DELETE FROM users WHERE anggota_id = ?', [$id]);
    $stmt->close();

    $stmt = db_run($conn, 'DELETE FROM anggota WHERE id = ?', [$id]);
    $stmt->close();

    $conn->commit();
    flash('success', 'Anggota "' . $anggota['nama'] . '" berhasil dihapus.');
} catch (Throwable $exception) {
    $conn->rollback();
    error_log('[KOPERASI] Gagal menghapus anggota: ' . $exception->getMessage());
    flash('danger', 'Data anggota gagal dihapus. Silakan coba lagi.');
}

redirect('anggota.php');
