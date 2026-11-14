<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_member();

pastikan_storage();

$user = current_user();
$dbUser = db_one(
    $conn,
    'SELECT id, nama, username, role, anggota_id FROM users WHERE id = ? LIMIT 1',
    [$user['id']]
);

if ($dbUser === null) {
    logout_user();
    session_start();
    flash('danger', 'Akun Anda tidak ditemukan. Silakan login kembali.');
    redirect('index.php');
}

$anggotaId = $dbUser['anggota_id'] !== null ? (int) $dbUser['anggota_id'] : null;
$anggota = $anggotaId !== null
    ? db_one($conn, 'SELECT id, kode_anggota, nama FROM anggota WHERE id = ? LIMIT 1', [$anggotaId])
    : null;

$errors = [];

/* -------------------------------------------------------------------------
 * Periode terpilih (dari GET; dipakai untuk melihat tagihan)
 * ---------------------------------------------------------------------- */
$bulanPilih = get('bulan');
if (!bulan_valid($bulanPilih)) {
    $bulanPilih = bulan_ini();
}

$tahunPilih = get_int('tahun');
if ($tahunPilih < 2000 || $tahunPilih > 2100) {
    $tahunPilih = (int) date('Y');
}

$pengaturan = pengaturan_pembayaran($conn);

/* -------------------------------------------------------------------------
 * Proses pengajuan pembayaran
 * ---------------------------------------------------------------------- */
$form = [
    'metode' => 'transfer',
    'jumlah' => (string) (int) IURAN_WAJIB,
    'catatan' => '',
];

if (is_post() && $anggota !== null) {
    verify_csrf();

    $bulanPost = post('bulan');
    $tahunPost = (int) post('tahun');
    $form['metode'] = post('metode', 'transfer');
    $form['jumlah'] = post('jumlah');
    $form['catatan'] = post('catatan');

    if (!bulan_valid($bulanPost)) {
        $errors[] = 'Bulan pembayaran tidak valid.';
        $bulanPost = $bulanPilih;
    }
    if ($tahunPost < 2000 || $tahunPost > 2100) {
        $errors[] = 'Tahun pembayaran tidak valid.';
        $tahunPost = $tahunPilih;
    }
    if (!metode_pembayaran_valid($form['metode'])) {
        $errors[] = 'Metode pembayaran tidak valid.';
    }
    if (mb_strlen($form['catatan']) > 200) {
        $errors[] = 'Catatan maksimal 200 karakter.';
    }

    $jumlahRaw = str_replace(',', '.', $form['jumlah']);
    $jumlah = (float) $jumlahRaw;
    if ($form['jumlah'] === '' || !is_numeric($jumlahRaw) || $jumlah <= 0) {
        $errors[] = 'Nominal pembayaran harus berupa angka lebih dari 0.';
    } elseif ($jumlah > IURAN_WAJIB) {
        $errors[] = 'Nominal melebihi iuran wajib ' . rupiah(IURAN_WAJIB) . ' per bulan.';
    }

    // Bukti transfer wajib diunggah.
    $upload = null;
    if ($errors === []) {
        $file = $_FILES['bukti'] ?? null;
        if (!is_array($file)) {
            $errors[] = 'Bukti transfer wajib diunggah.';
        } else {
            $upload = simpan_upload_gambar($file, 'bukti');
            if (!$upload['ok']) {
                $errors[] = (string) $upload['pesan'];
            }
        }
    }

    if ($errors === []) {
        $berhasil = false;
        try {
            $conn->begin_transaction();

            // Kunci baris anggota untuk serialisasi pengajuan per anggota.
            $terkunci = db_one($conn, 'SELECT id FROM anggota WHERE id = ? FOR UPDATE', [$anggotaId]);
            if ($terkunci === null) {
                throw new RuntimeException('Anggota tidak ditemukan.');
            }

            $ringkas = ringkasan_tagihan($conn, $anggotaId, $bulanPost, $tahunPost);

            if ($ringkas['sisa'] <= 0.0) {
                $conn->rollback();
                $errors[] = 'Iuran periode ' . $bulanPost . ' ' . $tahunPost
                    . ' sudah lunas atau sedang menunggu verifikasi.';
            } elseif ($jumlah > $ringkas['sisa'] + 0.0001) {
                $conn->rollback();
                $errors[] = 'Nominal melebihi sisa tagihan. Sisa yang dapat dibayar: '
                    . rupiah($ringkas['sisa']) . '.';
            } else {
                $stmt = db_run(
                    $conn,
                    'INSERT INTO pembayaran
                        (anggota_id, bulan, tahun, jumlah, metode, bukti, bukti_asli, bukti_mime, catatan, status)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, \'pending\')',
                    [
                        $anggotaId,
                        $bulanPost,
                        $tahunPost,
                        $jumlah,
                        $form['metode'],
                        $upload['nama'],
                        $upload['asli'],
                        $upload['mime'],
                        $form['catatan'] !== '' ? $form['catatan'] : null,
                    ]
                );
                $stmt->close();

                $conn->commit();
                $berhasil = true;

                flash(
                    'success',
                    'Pengajuan pembayaran ' . $bulanPost . ' ' . $tahunPost . ' sebesar '
                    . rupiah($jumlah) . ' berhasil dikirim dan menunggu verifikasi admin.'
                );
                redirect('pembayaran_iuran.php?bulan=' . urlencode($bulanPost) . '&tahun=' . $tahunPost);
            }
        } catch (Throwable $exception) {
            try {
                $conn->rollback();
            } catch (Throwable $ignored) {
            }
            error_log('[KOPERASI] Gagal menyimpan pengajuan pembayaran: ' . $exception->getMessage());
            $errors[] = 'Pengajuan pembayaran gagal disimpan. Silakan coba lagi.';
        }

        if (!$berhasil && $upload !== null && $upload['ok']) {
            hapus_berkas_storage($upload['nama'], 'bukti');
        }
    }

    if ($errors !== []) {
        $bulanPilih = $bulanPost;
        $tahunPilih = $tahunPost;
    }
}

/* -------------------------------------------------------------------------
 * Data tampilan
 * ---------------------------------------------------------------------- */
$ringkasan = $anggota !== null
    ? ringkasan_tagihan($conn, $anggotaId, $bulanPilih, $tahunPilih)
    : ['wajib' => (float) IURAN_WAJIB, 'dibayar' => 0.0, 'pending' => 0.0, 'sisa' => (float) IURAN_WAJIB];

$persenDibayar = $ringkasan['wajib'] > 0
    ? min(100, (int) round($ringkasan['dibayar'] / $ringkasan['wajib'] * 100))
    : 0;

$page = max(1, get_int('hal'));
$perPage = 10;
$totalRiwayat = 0;
$pagination = paginate(0, $perPage, 1);
$riwayat = [];

if ($anggota !== null) {
    $totalRiwayat = (int) db_value($conn, 'SELECT COUNT(*) FROM pembayaran WHERE anggota_id = ?', [$anggotaId]);
    $pagination = paginate($totalRiwayat, $perPage, $page);
    $riwayat = db_all(
        $conn,
        'SELECT id, bulan, tahun, jumlah, metode, bukti, bukti_asli, status, alasan_tolak, created_at, verified_at
         FROM pembayaran
         WHERE anggota_id = ?
         ORDER BY created_at DESC, id DESC
         LIMIT ' . (int) $pagination['per_page'] . ' OFFSET ' . (int) $pagination['offset'],
        [$anggotaId]
    );
}

render_app_start(
    'Pembayaran Iuran',
    'pembayaran_iuran.php',
    'Pembayaran Iuran',
    'Ajukan pembayaran iuran dan pantau status verifikasinya.'
);
?>

<?php if ($anggota === null): ?>
    <div class="alert alert-warning" role="alert">
        <?= icon('alert') ?>
        <div>
            <strong>Akun belum terhubung ke data anggota.</strong>
            <p>Hubungi admin agar akun <b><?= e($dbUser['username']) ?></b> dihubungkan dengan data anggota koperasi sebelum mengajukan pembayaran.</p>
        </div>
    </div>
<?php else: ?>
    <?= render_errors($errors) ?>

    <section class="panel">
        <div class="panel-head">
            <div>
                <h2>Tagihan Iuran</h2>
                <p>Pilih periode untuk melihat rincian tagihan Anda.</p>
            </div>
            <form method="get" class="filter-bar" style="margin:0">
                <select name="bulan" aria-label="Bulan">
                    <?php foreach (bulan_list() as $bulan): ?>
                        <option value="<?= e($bulan) ?>" <?= $bulan === $bulanPilih ? 'selected' : '' ?>><?= e($bulan) ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="number" name="tahun" value="<?= e((string) $tahunPilih) ?>" min="2000" max="2100" step="1" aria-label="Tahun" style="max-width:110px">
                <button type="submit" class="btn btn-secondary"><?= icon('search') ?> Tampilkan</button>
            </form>
        </div>

        <div class="tagihan-grid">
            <div class="tagihan-item">
                <span class="tagihan-label">Periode</span>
                <strong><?= e($bulanPilih) ?> <?= e((string) $tahunPilih) ?></strong>
            </div>
            <div class="tagihan-item">
                <span class="tagihan-label">Total Tagihan</span>
                <strong><?= rupiah($ringkasan['wajib']) ?></strong>
            </div>
            <div class="tagihan-item">
                <span class="tagihan-label">Sudah Dibayar</span>
                <strong class="text-success"><?= rupiah($ringkasan['dibayar']) ?></strong>
            </div>
            <div class="tagihan-item">
                <span class="tagihan-label">Menunggu Verifikasi</span>
                <strong class="text-warning"><?= rupiah($ringkasan['pending']) ?></strong>
            </div>
            <div class="tagihan-item is-highlight">
                <span class="tagihan-label">Sisa Tagihan</span>
                <strong><?= rupiah($ringkasan['sisa']) ?></strong>
            </div>
        </div>

        <div class="progress-block">
            <div class="progress-label">
                <strong>Progress pembayaran</strong>
                <span><?= $persenDibayar ?>%</span>
            </div>
            <div class="progress" role="progressbar" aria-valuenow="<?= $persenDibayar ?>" aria-valuemin="0" aria-valuemax="100">
                <span style="width: <?= $persenDibayar ?>%"></span>
            </div>
            <?php if ($ringkasan['sisa'] <= 0): ?>
                <p class="text-success text-sm"><?= icon('check') ?> Iuran periode ini sudah lunas atau sedang menunggu verifikasi.</p>
            <?php else: ?>
                <p class="text-muted text-sm"><?= icon('info') ?> Sisa yang dapat dibayar: <strong><?= rupiah($ringkasan['sisa']) ?></strong>.</p>
            <?php endif; ?>
        </div>
    </section>

    <div class="grid-2 wide-left">
        <section class="panel">
            <div class="panel-head">
                <div>
                    <h2>Ajukan Pembayaran</h2>
                    <p>Unggah bukti transfer agar admin dapat memverifikasi.</p>
                </div>
            </div>

            <?php if ($ringkasan['sisa'] <= 0): ?>
                <div class="empty-state compact">
                    <?= icon('check') ?>
                    <h3>Tidak ada tagihan tersisa</h3>
                    <p>Iuran <?= e($bulanPilih) ?> <?= e((string) $tahunPilih) ?> sudah lunas atau pengajuan Anda sedang menunggu verifikasi.</p>
                </div>
            <?php else: ?>
                <form method="post" enctype="multipart/form-data" class="payment-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="bulan" value="<?= e($bulanPilih) ?>">
                    <input type="hidden" name="tahun" value="<?= e((string) $tahunPilih) ?>">

                    <div class="form-grid">
                        <div class="field">
                            <label for="metode">Metode Pembayaran <span class="required">*</span></label>
                            <select id="metode" name="metode" required>
                                <option value="transfer" <?= $form['metode'] === 'transfer' ? 'selected' : '' ?>>Transfer Bank</option>
                                <option value="qris" <?= $form['metode'] === 'qris' ? 'selected' : '' ?>>QRIS</option>
                            </select>
                        </div>

                        <div class="field">
                            <label for="jumlah">Nominal Pembayaran <span class="required">*</span></label>
                            <input id="jumlah" type="number" name="jumlah" value="<?= e($form['jumlah']) ?>"
                                   min="1" max="<?= (int) $ringkasan['sisa'] ?>" step="1" required>
                            <small class="field-hint">Maksimal <?= rupiah($ringkasan['sisa']) ?>. Pembayaran sebagian diperbolehkan.</small>
                        </div>

                        <div class="field span-2">
                            <label for="bukti">Bukti Transfer <span class="required">*</span></label>
                            <input id="bukti" type="file" name="bukti" accept="image/jpeg,image/png,image/webp" required>
                            <small class="field-hint">Format JPG, JPEG, PNG, atau WebP. Maksimal 2 MB.</small>
                        </div>

                        <div class="field span-2">
                            <label for="catatan">Catatan (opsional)</label>
                            <input id="catatan" type="text" name="catatan" value="<?= e($form['catatan']) ?>"
                                   maxlength="200" placeholder="Contoh: transfer dari rekening istri">
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary"><?= icon('upload') ?> Kirim Pengajuan</button>
                        <a class="btn btn-ghost" href="pembayaran_iuran.php"><?= icon('x') ?> Reset</a>
                    </div>
                </form>
            <?php endif; ?>
        </section>

        <aside class="panel panel-muted">
            <h2>Informasi Pembayaran</h2>
            <p class="text-muted text-sm" style="margin-top:4px">Gunakan rekening atau QRIS resmi koperasi berikut.</p>

            <?php if ($pengaturan['nama_bank'] !== '' || $pengaturan['nomor_rekening'] !== ''): ?>
                <div class="info-akun">
                    <span class="info-akun-icon"><?= icon('bank') ?></span>
                    <div>
                        <span class="tagihan-label">Transfer Bank</span>
                        <strong><?= e($pengaturan['nama_bank'] !== '' ? $pengaturan['nama_bank'] : 'Bank') ?></strong>
                        <?php if ($pengaturan['nomor_rekening'] !== ''): ?>
                            <div class="info-akun-nomor"><?= e($pengaturan['nomor_rekening']) ?></div>
                        <?php endif; ?>
                        <?php if ($pengaturan['nama_pemilik'] !== ''): ?>
                            <div class="table-sub">a.n. <?= e($pengaturan['nama_pemilik']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($pengaturan['qris_gambar'] !== '' || $pengaturan['qris_nama'] !== ''): ?>
                <div class="info-akun">
                    <span class="info-akun-icon"><?= icon('qr') ?></span>
                    <div>
                        <span class="tagihan-label">QRIS</span>
                        <strong><?= e($pengaturan['qris_nama'] !== '' ? $pengaturan['qris_nama'] : 'QRIS Koperasi') ?></strong>
                        <?php if ($pengaturan['qris_gambar'] !== ''): ?>
                            <div style="margin-top:8px">
                                <a href="qris.php" target="_blank" rel="noopener" class="qris-thumb-link" title="Lihat QRIS">
                                    <img class="qris-thumb" src="qris.php" alt="QRIS Koperasi">
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($pengaturan['nama_bank'] === '' && $pengaturan['nomor_rekening'] === '' && $pengaturan['qris_gambar'] === '' && $pengaturan['qris_nama'] === ''): ?>
                <div class="info-box">
                    <?= icon('info') ?>
                    <p>Admin belum mengatur informasi rekening/QRIS. Silakan hubungi admin koperasi.</p>
                </div>
            <?php endif; ?>

            <?php if ($pengaturan['catatan'] !== ''): ?>
                <div class="info-box">
                    <?= icon('info') ?>
                    <p><?= nl2br(e($pengaturan['catatan'])) ?></p>
                </div>
            <?php endif; ?>

            <div class="info-box">
                <?= icon('shield') ?>
                <p>Pembayaran hanya dicatat ke kas koperasi setelah admin menyetujui pengajuan Anda.</p>
            </div>
        </aside>
    </div>

    <section class="panel">
        <div class="panel-head">
            <div>
                <h2>Riwayat Pengajuan Pembayaran</h2>
                <p><?= number_format($totalRiwayat, 0, ',', '.') ?> pengajuan tercatat.</p>
            </div>
        </div>

        <?php if ($riwayat === []): ?>
            <div class="empty-state compact">
                <?= icon('history') ?>
                <h3>Belum ada pengajuan</h3>
                <p>Pengajuan pembayaran yang Anda kirim akan tampil di sini beserta statusnya.</p>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Periode</th>
                            <th class="text-right">Nominal</th>
                            <th>Metode</th>
                            <th>Status</th>
                            <th>Bukti</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($riwayat as $row): ?>
                            <tr>
                                <td class="text-muted"><?= e(tanggal_indo($row['created_at'], true)) ?></td>
                                <td><?= e($row['bulan']) ?> <?= e((string) $row['tahun']) ?></td>
                                <td class="text-right"><strong><?= rupiah($row['jumlah']) ?></strong></td>
                                <td><?= e(metode_pembayaran_label((string) $row['metode'])) ?></td>
                                <td>
                                    <?= badge_status_pembayaran((string) $row['status']) ?>
                                    <?php if ($row['status'] === 'rejected' && (string) $row['alasan_tolak'] !== ''): ?>
                                        <div class="reject-note"><?= icon('alert') ?> <?= e($row['alasan_tolak']) ?></div>
                                    <?php endif; ?>
                                    <?php if ($row['status'] === 'approved' && $row['verified_at'] !== null): ?>
                                        <div class="table-sub"><?= e(tanggal_indo($row['verified_at'], true)) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a class="btn btn-sm btn-ghost" href="bukti.php?id=<?= (int) $row['id'] ?>" target="_blank" rel="noopener">
                                        <?= icon('image') ?> Lihat
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="table-footer">
                <span class="text-muted text-sm">
                    Menampilkan <?= (int) $pagination['offset'] + 1 ?>–<?= min($totalRiwayat, (int) $pagination['offset'] + $pagination['per_page']) ?>
                    dari <?= number_format($totalRiwayat, 0, ',', '.') ?> pengajuan
                </span>
                <?= render_pagination($pagination, ['bulan' => $bulanPilih, 'tahun' => $tahunPilih]) ?>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>

<?php render_app_end(); ?>
