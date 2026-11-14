<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_admin();

$id = get_int('id');
if ($id <= 0) {
    render_not_found('ID transaksi tidak valid.');
}

$transaksi = db_one(
    $conn,
    'SELECT id, anggota_id, bulan, tahun, jumlah, tanggal FROM kas WHERE id = ? LIMIT 1',
    [$id]
);

if ($transaksi === null) {
    render_not_found('Transaksi iuran tidak ditemukan atau sudah dihapus.');
}

$errors = [];
$form = [
    'anggota' => (string) ($transaksi['anggota_id'] ?? ''),
    'bulan' => (string) $transaksi['bulan'],
    'tahun' => (string) $transaksi['tahun'],
    'jumlah' => (string) (int) $transaksi['jumlah'],
];

if (is_post()) {
    verify_csrf();

    $form['anggota'] = (string) post_int('anggota');
    $form['bulan'] = post('bulan');
    $form['tahun'] = post('tahun');
    $form['jumlah'] = post('jumlah');

    $anggotaId = (int) $form['anggota'];
    $anggota = $anggotaId > 0
        ? db_one($conn, 'SELECT id, nama FROM anggota WHERE id = ? LIMIT 1', [$anggotaId])
        : null;

    if ($anggota === null) {
        $errors[] = 'Pilih anggota yang valid.';
    }

    if (!bulan_valid($form['bulan'])) {
        $errors[] = 'Bulan tidak valid.';
    }

    $tahun = (int) $form['tahun'];
    if ($tahun < 2000 || $tahun > 2100) {
        $errors[] = 'Tahun harus berada di antara 2000 dan 2100.';
    }

    $jumlah = (float) str_replace(',', '.', $form['jumlah']);
    if ($form['jumlah'] === '' || !is_numeric($form['jumlah']) || $jumlah <= 0) {
        $errors[] = 'Jumlah iuran harus berupa angka lebih dari 0.';
    } elseif ($jumlah > IURAN_WAJIB) {
        $errors[] = 'Jumlah iuran melebihi iuran wajib ' . rupiah(IURAN_WAJIB) . ' per bulan.';
    }

    // Selalu periksa total periode tujuan, termasuk saat hanya nominal yang
    // berubah. Transaksi yang sedang diedit dikecualikan dari penjumlahan.
    if ($anggota !== null && $errors === []) {
        $sudahDibayar = total_iuran_periode($conn, $anggotaId, $form['bulan'], $tahun, $id);

        if ($sudahDibayar + $jumlah > IURAN_WAJIB) {
            if ($sudahDibayar >= IURAN_WAJIB) {
                $errors[] = 'Periode tujuan sudah lunas untuk anggota tersebut (total '
                    . rupiah($sudahDibayar) . '). Hapus transaksi duplikat terlebih dahulu bila ada.';
            } else {
                $errors[] = 'Jumlah melebihi sisa iuran pada periode tujuan: '
                    . rupiah(IURAN_WAJIB - $sudahDibayar) . '.';
            }
        }
    }

    if ($errors === []) {
        try {
            $conn->begin_transaction();

            // Kunci baris anggota agar dua permintaan bersamaan tidak dapat
            // sama-sama lolos validasi batas iuran.
            $terkunci = db_one(
                $conn,
                'SELECT id FROM anggota WHERE id = ? FOR UPDATE',
                [$anggotaId]
            );

            if ($terkunci === null) {
                throw new RuntimeException('Anggota tujuan tidak ditemukan.');
            }

            $sudahDibayar = total_iuran_periode($conn, $anggotaId, $form['bulan'], $tahun, $id);

            if ($sudahDibayar + $jumlah > IURAN_WAJIB) {
                $conn->rollback();
                $errors[] = 'Iuran periode tujuan sudah lunas atau melebihi batas '
                    . rupiah(IURAN_WAJIB) . '.';
            } else {
                $stmt = db_run(
                    $conn,
                    'UPDATE kas SET anggota_id = ?, bulan = ?, tahun = ?, jumlah = ? WHERE id = ?',
                    [$anggotaId, $form['bulan'], $tahun, $jumlah, $id]
                );
                $stmt->close();

                $conn->commit();
                flash('success', 'Transaksi iuran berhasil diperbarui.');
                redirect('riwayat_iuran.php');
            }
        } catch (Throwable $exception) {
            $conn->rollback();
            error_log('[KOPERASI] Gagal memperbarui iuran: ' . $exception->getMessage());
            $errors[] = 'Transaksi gagal diperbarui. Silakan coba lagi.';
        }
    }
}

$anggotaList = anggota_options($conn);

render_app_start(
    'Edit Iuran',
    'riwayat_iuran.php',
    'Edit Transaksi Iuran',
    'Perbarui data pembayaran iuran anggota.'
);
?>

<div class="breadcrumb">
    <a href="riwayat_iuran.php"><?= icon('back') ?> Riwayat Iuran</a>
    <span>/</span>
    <span>Edit Transaksi #<?= $id ?></span>
</div>

<?= render_errors($errors) ?>

<div class="grid-2 wide-left">
    <form method="post" action="edit_iuran.php?id=<?= $id ?>" class="panel">
        <?= csrf_field() ?>

        <div class="panel-head">
            <div>
                <h2>Data Transaksi</h2>
                <p>Dicatat pada <?= e(tanggal_indo($transaksi['tanggal'], true)) ?>.</p>
            </div>
        </div>

        <div class="form-grid">
            <div class="field span-2">
                <label for="anggota">Anggota <span class="required">*</span></label>
                <select id="anggota" name="anggota" required>
                    <option value="">— Pilih anggota —</option>
                    <?php foreach ($anggotaList as $anggota): ?>
                        <option value="<?= (int) $anggota['id'] ?>"
                            <?= (string) $anggota['id'] === $form['anggota'] ? 'selected' : '' ?>>
                            <?= e($anggota['nama']) ?> (<?= e($anggota['kode_anggota']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label for="bulan">Bulan <span class="required">*</span></label>
                <select id="bulan" name="bulan" required>
                    <?php foreach (bulan_list() as $bulan): ?>
                        <option value="<?= e($bulan) ?>" <?= $bulan === $form['bulan'] ? 'selected' : '' ?>>
                            <?= e($bulan) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label for="tahun">Tahun <span class="required">*</span></label>
                <input id="tahun" type="number" name="tahun" value="<?= e($form['tahun']) ?>"
                       min="2000" max="2100" step="1" required>
            </div>

            <div class="field">
                <label for="jumlah">Jumlah Iuran <span class="required">*</span></label>
                <input id="jumlah" type="number" name="jumlah" value="<?= e($form['jumlah']) ?>"
                       min="1" max="<?= (int) IURAN_WAJIB ?>" step="1" required>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><?= icon('save') ?> Simpan Perubahan</button>
            <a class="btn btn-ghost" href="riwayat_iuran.php"><?= icon('back') ?> Batal</a>
        </div>
    </form>

    <aside class="panel panel-muted">
        <h2>Hapus Transaksi</h2>
        <p class="text-muted">Gunakan tombol berikut jika transaksi ini memang salah input dan harus dihapus.</p>

        <form method="post" action="hapus_iuran.php"
              data-confirm="Hapus transaksi iuran ini secara permanen?">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= $id ?>">
            <input type="hidden" name="redirect_to" value="riwayat_iuran.php">
            <button type="submit" class="btn btn-danger btn-block"><?= icon('trash') ?> Hapus Transaksi</button>
        </form>

        <div class="info-box">
            <?= icon('info') ?>
            <p>Perubahan pada transaksi lama dapat memengaruhi total kas dan laporan. Pastikan data sudah benar.</p>
        </div>
    </aside>
</div>

<?php render_app_end(); ?>
