<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_admin();

pastikan_storage();

$admin = current_user();
$errors = [];
$pengaturan = pengaturan_pembayaran($conn);

if (is_post()) {
    verify_csrf();

    $namaBank = post('nama_bank');
    $nomorRekening = post('nomor_rekening');
    $namaPemilik = post('nama_pemilik');
    $qrisNama = post('qris_nama');
    $catatan = post('catatan');
    $hapusQris = post('hapus_qris') === '1';

    if (mb_strlen($namaBank) > 100) {
        $errors[] = 'Nama bank maksimal 100 karakter.';
    }
    if (mb_strlen($nomorRekening) > 60) {
        $errors[] = 'Nomor rekening maksimal 60 karakter.';
    }
    if (mb_strlen($namaPemilik) > 100) {
        $errors[] = 'Nama pemilik rekening maksimal 100 karakter.';
    }
    if (mb_strlen($qrisNama) > 100) {
        $errors[] = 'Nama merchant QRIS maksimal 100 karakter.';
    }
    if (mb_strlen($catatan) > 500) {
        $errors[] = 'Catatan maksimal 500 karakter.';
    }

    $qrisGambar = (string) ($pengaturan['qris_gambar'] ?? '');
    $qrisGambarAsli = (string) ($pengaturan['qris_gambar_asli'] ?? '');
    $qrisLama = $qrisGambar;
    $upload = null;

    if ($hapusQris && $qrisGambar !== '') {
        $qrisGambar = '';
        $qrisGambarAsli = '';
    }

    if ($errors === [] && isset($_FILES['qris_gambar']) && is_array($_FILES['qris_gambar'])
        && (int) ($_FILES['qris_gambar']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $upload = simpan_upload_gambar($_FILES['qris_gambar'], 'qris');
        if (!$upload['ok']) {
            $errors[] = (string) $upload['pesan'];
        } else {
            $qrisGambar = (string) $upload['nama'];
            $qrisGambarAsli = (string) $upload['asli'];
        }
    }

    if ($errors === []) {
        try {
            $stmt = db_run(
                $conn,
                'INSERT INTO pengaturan_pembayaran
                    (id, nama_bank, nomor_rekening, nama_pemilik, qris_nama, qris_gambar, qris_gambar_asli, catatan, updated_by)
                 VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                    nama_bank = VALUES(nama_bank),
                    nomor_rekening = VALUES(nomor_rekening),
                    nama_pemilik = VALUES(nama_pemilik),
                    qris_nama = VALUES(qris_nama),
                    qris_gambar = VALUES(qris_gambar),
                    qris_gambar_asli = VALUES(qris_gambar_asli),
                    catatan = VALUES(catatan),
                    updated_by = VALUES(updated_by)',
                [
                    $namaBank !== '' ? $namaBank : null,
                    $nomorRekening !== '' ? $nomorRekening : null,
                    $namaPemilik !== '' ? $namaPemilik : null,
                    $qrisNama !== '' ? $qrisNama : null,
                    $qrisGambar !== '' ? $qrisGambar : null,
                    $qrisGambarAsli !== '' ? $qrisGambarAsli : null,
                    $catatan !== '' ? $catatan : null,
                    (int) $admin['id'],
                ]
            );
            $stmt->close();

            // Hapus gambar lama hanya setelah konfigurasi baru berhasil tersimpan.
            if ($qrisLama !== '' && $qrisLama !== $qrisGambar) {
                hapus_berkas_storage($qrisLama, 'qris');
            }

            flash('success', 'Informasi rekening dan QRIS berhasil disimpan.');
            redirect('pengaturan_pembayaran.php');
        } catch (Throwable $exception) {
            if ($upload !== null && $upload['ok']) {
                hapus_berkas_storage((string) $upload['nama'], 'qris');
            }
            error_log('[KOPERASI] Gagal menyimpan pengaturan pembayaran: ' . $exception->getMessage());
            $errors[] = 'Pengaturan gagal disimpan. Silakan coba lagi.';
        }
    } elseif ($upload !== null && $upload['ok']) {
        hapus_berkas_storage((string) $upload['nama'], 'qris');
    }

    $pengaturan = array_merge($pengaturan, [
        'nama_bank' => $namaBank,
        'nomor_rekening' => $nomorRekening,
        'nama_pemilik' => $namaPemilik,
        'qris_nama' => $qrisNama,
        'qris_gambar' => $qrisGambar,
        'qris_gambar_asli' => $qrisGambarAsli,
        'catatan' => $catatan,
    ]);
}

render_app_start(
    'Pengaturan Pembayaran',
    'pengaturan_pembayaran.php',
    'Pengaturan Pembayaran',
    'Atur rekening bank dan QRIS koperasi yang ditampilkan ke anggota.'
);
?>

<?= render_errors($errors) ?>

<div class="grid-2 wide-left">
    <section class="panel">
        <div class="panel-head">
            <div>
                <h2>Rekening & QRIS Koperasi</h2>
                <p>Informasi ini tampil pada halaman Pembayaran Iuran anggota.</p>
            </div>
        </div>

        <form method="post" enctype="multipart/form-data">
            <?= csrf_field() ?>

            <div class="form-grid">
                <div class="field">
                    <label for="nama_bank">Nama Bank</label>
                    <input id="nama_bank" type="text" name="nama_bank" maxlength="100"
                           value="<?= e($pengaturan['nama_bank']) ?>" placeholder="Contoh: Bank Mandiri">
                </div>

                <div class="field">
                    <label for="nomor_rekening">Nomor Rekening</label>
                    <input id="nomor_rekening" type="text" name="nomor_rekening" maxlength="60"
                           value="<?= e($pengaturan['nomor_rekening']) ?>" placeholder="Contoh: 1234567890">
                </div>

                <div class="field">
                    <label for="nama_pemilik">Nama Pemilik Rekening</label>
                    <input id="nama_pemilik" type="text" name="nama_pemilik" maxlength="100"
                           value="<?= e($pengaturan['nama_pemilik']) ?>" placeholder="Contoh: Koperasi Sejahtera">
                </div>

                <div class="field">
                    <label for="qris_nama">Nama Merchant QRIS</label>
                    <input id="qris_nama" type="text" name="qris_nama" maxlength="100"
                           value="<?= e($pengaturan['qris_nama']) ?>" placeholder="Contoh: Koperasi Sejahtera">
                </div>

                <div class="field span-2">
                    <label for="qris_gambar">Gambar QRIS</label>
                    <input id="qris_gambar" type="file" name="qris_gambar" accept="image/jpeg,image/png,image/webp">
                    <small class="field-hint">Format JPG, JPEG, PNG, atau WebP. Maksimal 2 MB. Biarkan kosong bila tidak diubah.</small>
                </div>

                <div class="field span-2">
                    <label for="catatan">Catatan untuk Anggota</label>
                    <textarea id="catatan" name="catatan" maxlength="500"
                              placeholder="Contoh: Konfirmasi ke bendahara setelah transfer."><?= e($pengaturan['catatan']) ?></textarea>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><?= icon('save') ?> Simpan Pengaturan</button>
                <a class="btn btn-ghost" href="verifikasi_pembayaran.php"><?= icon('verify') ?> Ke Verifikasi</a>
            </div>
        </form>
    </section>

    <aside class="panel panel-muted">
        <h2>Pratinjau</h2>
        <p class="text-muted text-sm" style="margin-top:4px">Tampilan informasi yang akan dilihat anggota.</p>

        <div class="info-akun">
            <span class="info-akun-icon"><?= icon('bank') ?></span>
            <div>
                <span class="tagihan-label">Transfer Bank</span>
                <strong><?= e($pengaturan['nama_bank'] !== '' ? $pengaturan['nama_bank'] : 'Belum diatur') ?></strong>
                <?php if ($pengaturan['nomor_rekening'] !== ''): ?>
                    <div class="info-akun-nomor"><?= e($pengaturan['nomor_rekening']) ?></div>
                <?php endif; ?>
                <?php if ($pengaturan['nama_pemilik'] !== ''): ?>
                    <div class="table-sub">a.n. <?= e($pengaturan['nama_pemilik']) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="info-akun">
            <span class="info-akun-icon"><?= icon('qr') ?></span>
            <div>
                <span class="tagihan-label">QRIS</span>
                <strong><?= e($pengaturan['qris_nama'] !== '' ? $pengaturan['qris_nama'] : 'Belum diatur') ?></strong>
                <?php if ($pengaturan['qris_gambar'] !== ''): ?>
                    <div style="margin-top:8px">
                        <img class="qris-thumb" src="qris.php" alt="Pratinjau QRIS">
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($pengaturan['qris_gambar'] !== ''): ?>
            <form method="post" class="inline-form" data-confirm="Hapus gambar QRIS yang tersimpan?">
                <?= csrf_field() ?>
                <input type="hidden" name="nama_bank" value="<?= e($pengaturan['nama_bank']) ?>">
                <input type="hidden" name="nomor_rekening" value="<?= e($pengaturan['nomor_rekening']) ?>">
                <input type="hidden" name="nama_pemilik" value="<?= e($pengaturan['nama_pemilik']) ?>">
                <input type="hidden" name="qris_nama" value="<?= e($pengaturan['qris_nama']) ?>">
                <input type="hidden" name="catatan" value="<?= e($pengaturan['catatan']) ?>">
                <input type="hidden" name="hapus_qris" value="1">
                <button type="submit" class="btn btn-sm btn-danger-soft"><?= icon('trash') ?> Hapus Gambar QRIS</button>
            </form>
        <?php endif; ?>

        <div class="info-box">
            <?= icon('shield') ?>
            <p>Berkas QRIS disimpan di direktori terlindungi dan hanya dapat diakses pengguna yang sudah login.</p>
        </div>
    </aside>
</div>

<?php render_app_end(); ?>
