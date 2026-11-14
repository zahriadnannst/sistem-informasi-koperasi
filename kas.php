<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_admin();

$errors = [];
$form = [
    'anggota' => '',
    'bulan' => bulan_ini(),
    'tahun' => date('Y'),
    'jumlah' => (string) (int) IURAN_WAJIB,
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
    } elseif ($jumlah > 1000000000) {
        $errors[] = 'Jumlah iuran tidak wajar.';
    } elseif ($jumlah > IURAN_WAJIB) {
        $errors[] = 'Jumlah iuran melebihi iuran wajib ' . rupiah(IURAN_WAJIB) . ' per bulan.';
    }

    if ($anggota !== null && $errors === []) {
        $sudahDibayar = total_iuran_periode($conn, $anggotaId, $form['bulan'], $tahun);

        if ($sudahDibayar >= IURAN_WAJIB) {
            $errors[] = 'Iuran ' . $anggota['nama'] . ' untuk ' . $form['bulan'] . ' ' . $tahun
                . ' sudah lunas. Gunakan menu Riwayat Iuran untuk mengedit data.';
        } elseif (($sudahDibayar + $jumlah) > IURAN_WAJIB) {
            $errors[] = 'Jumlah melebihi sisa iuran. Sisa yang belum dibayar: '
                . rupiah(IURAN_WAJIB - $sudahDibayar) . '.';
        }
    }

    if ($errors === []) {
        try {
            $conn->begin_transaction();

            // Serialisasi perubahan per anggota untuk mencegah dua transaksi
            // bersamaan melampaui batas iuran wajib.
            $terkunci = db_one(
                $conn,
                'SELECT id FROM anggota WHERE id = ? FOR UPDATE',
                [$anggotaId]
            );

            if ($terkunci === null) {
                throw new RuntimeException('Anggota tidak ditemukan.');
            }

            $sudahDibayar = total_iuran_periode($conn, $anggotaId, $form['bulan'], $tahun);

            if (($sudahDibayar + $jumlah) > IURAN_WAJIB) {
                $conn->rollback();
                $errors[] = 'Iuran periode tersebut sudah lunas atau melebihi batas '
                    . rupiah(IURAN_WAJIB) . '.';
            } else {
                $stmt = db_run(
                    $conn,
                    'INSERT INTO kas (anggota_id, bulan, tahun, jumlah) VALUES (?, ?, ?, ?)',
                    [$anggotaId, $form['bulan'], $tahun, $jumlah]
                );
                $stmt->close();

                $conn->commit();
                flash('success', 'Iuran ' . $anggota['nama'] . ' untuk ' . $form['bulan'] . ' ' . $tahun . ' berhasil disimpan.');
                redirect('kas.php');
            }
        } catch (Throwable $exception) {
            $conn->rollback();
            error_log('[KOPERASI] Gagal menyimpan iuran: ' . $exception->getMessage());
            $errors[] = 'Iuran gagal disimpan. Silakan coba lagi.';
        }
    }
}

$anggotaList = anggota_options($conn);
$transaksiTerbaru = db_all(
    $conn,
    'SELECT kas.id, kas.bulan, kas.tahun, kas.jumlah, kas.tanggal,
            anggota.nama, anggota.kode_anggota
     FROM kas
     LEFT JOIN anggota ON anggota.id = kas.anggota_id
     ORDER BY kas.tanggal DESC, kas.id DESC
     LIMIT 8'
);

render_app_start(
    'Input Iuran',
    'kas.php',
    'Input Iuran',
    'Catat pembayaran iuran anggota ke kas koperasi.'
);
?>

<div class="grid-2 wide-left">
    <section class="panel">
        <div class="panel-head">
            <div>
                <h2>Form Pembayaran Iuran</h2>
                <p>Iuran wajib <?= rupiah(IURAN_WAJIB) ?> per anggota setiap bulan.</p>
            </div>
        </div>

        <?= render_errors($errors) ?>

        <form method="post">
            <?= csrf_field() ?>

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
                    <small class="field-hint">Maksimal <?= rupiah(IURAN_WAJIB) ?> per bulan. Pembayaran sebagian diperbolehkan.</small>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><?= icon('save') ?> Simpan Iuran</button>
                <a class="btn btn-ghost" href="riwayat_iuran.php"><?= icon('history') ?> Lihat Riwayat</a>
            </div>
        </form>
    </section>

    <aside class="panel panel-muted">
        <h2>Panduan Singkat</h2>
        <ol class="steps">
            <li>Pilih anggota yang membayar iuran.</li>
            <li>Tentukan bulan dan tahun periode iuran.</li>
            <li>Masukkan nominal, lalu simpan.</li>
        </ol>

        <div class="info-box">
            <?= icon('info') ?>
            <p>Sistem menolak pembayaran ganda untuk anggota, bulan, dan tahun yang sama bila iuran sudah lunas.</p>
        </div>

        <div class="info-box">
            <?= icon('wallet') ?>
            <p>Pembayaran sebagian akan berstatus <span class="badge badge-warning">Sebagian</span> sampai totalnya mencapai <?= rupiah(IURAN_WAJIB) ?>.</p>
        </div>
    </aside>
</div>

<section class="panel">
    <div class="panel-head">
        <div>
            <h2>Transaksi Terbaru</h2>
            <p>Delapan pembayaran terakhir yang tercatat.</p>
        </div>
        <a class="btn btn-ghost btn-sm" href="riwayat_iuran.php">Lihat semua <?= icon('arrow-right') ?></a>
    </div>

    <?php if ($transaksiTerbaru === []): ?>
        <div class="empty-state compact">
            <?= icon('history') ?>
            <h3>Belum ada transaksi</h3>
            <p>Gunakan form di atas untuk mencatat pembayaran iuran pertama.</p>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Anggota</th>
                        <th>Periode</th>
                        <th class="text-right">Jumlah</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transaksiTerbaru as $row): ?>
                        <tr>
                            <td class="text-muted"><?= e(tanggal_indo($row['tanggal'])) ?></td>
                            <td>
                                <strong><?= e($row['nama'] ?? 'Anggota dihapus') ?></strong>
                                <div class="table-sub"><?= e($row['kode_anggota'] ?? '-') ?></div>
                            </td>
                            <td><?= e($row['bulan']) ?> <?= e((string) $row['tahun']) ?></td>
                            <td class="text-right"><strong><?= rupiah($row['jumlah']) ?></strong></td>
                            <td class="text-right">
                                <div class="table-actions">
                                    <a class="btn btn-icon btn-ghost" href="edit_iuran.php?id=<?= (int) $row['id'] ?>"
                                       title="Edit transaksi" aria-label="Edit transaksi">
                                        <?= icon('edit') ?>
                                    </a>
                                    <form method="post" action="hapus_iuran.php" class="inline-form"
                                          data-confirm="Hapus transaksi iuran ini?">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                        <input type="hidden" name="redirect_to" value="kas.php">
                                        <button type="submit" class="btn btn-icon btn-danger-soft"
                                                title="Hapus transaksi" aria-label="Hapus transaksi">
                                            <?= icon('trash') ?>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php render_app_end(); ?>
