<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_admin();

$admin = current_user();
$errors = [];
$sukses = null;

/* -------------------------------------------------------------------------
 * Filter daftar pengajuan
 * ---------------------------------------------------------------------- */
$statusFilter = get('status', 'pending');
if (!in_array($statusFilter, ['pending', 'approved', 'rejected', 'semua'], true)) {
    $statusFilter = 'pending';
}

$anggotaFilter = get_int('anggota_id');
if ($anggotaFilter < 0) {
    $anggotaFilter = 0;
}

$bulanFilter = get('bulan');
if (!bulan_valid($bulanFilter)) {
    $bulanFilter = '';
}

$tahunFilter = get_int('tahun');
if ($tahunFilter < 2000 || $tahunFilter > 2100) {
    $tahunFilter = 0;
}

$filterParams = array_filter([
    'status' => $statusFilter !== 'pending' ? $statusFilter : null,
    'anggota_id' => $anggotaFilter > 0 ? $anggotaFilter : null,
    'bulan' => $bulanFilter !== '' ? $bulanFilter : null,
    'tahun' => $tahunFilter > 0 ? $tahunFilter : null,
], static fn ($value) => $value !== null);
$kembali = 'verifikasi_pembayaran.php' . ($filterParams !== [] ? '?' . http_build_query($filterParams) : '');

/* -------------------------------------------------------------------------
 * Aksi: setujui / tolak
 * ---------------------------------------------------------------------- */
if (is_post()) {
    verify_csrf();

    $aksi = post('aksi');
    $id = post_int('id');
    $redirectTo = post('kembali', 'verifikasi_pembayaran.php');
    if (!str_starts_with($redirectTo, 'verifikasi_pembayaran.php')) {
        $redirectTo = 'verifikasi_pembayaran.php';
    }

    $pengajuan = $id > 0 ? db_one($conn, 'SELECT * FROM pembayaran WHERE id = ? LIMIT 1', [$id]) : null;

    if ($pengajuan === null) {
        flash('danger', 'Pengajuan pembayaran tidak ditemukan.');
        redirect($redirectTo);
    }

    if ($aksi === 'approve') {
        try {
            $conn->begin_transaction();

            $terkunci = db_one($conn, 'SELECT * FROM pembayaran WHERE id = ? FOR UPDATE', [$id]);
            if ($terkunci === null) {
                throw new RuntimeException('Pengajuan tidak ditemukan.');
            }
            if ((string) $terkunci['status'] !== 'pending') {
                $conn->rollback();
                flash('warning', 'Pengajuan tersebut sudah diproses sebelumnya.');
                redirect($redirectTo);
            }

            $anggotaId = (int) $terkunci['anggota_id'];
            $bulan = (string) $terkunci['bulan'];
            $tahun = (int) $terkunci['tahun'];
            $jumlah = (float) $terkunci['jumlah'];

            // Kunci baris anggota agar total iuran periode tidak berubah bersamaan.
            $anggota = db_one($conn, 'SELECT id FROM anggota WHERE id = ? FOR UPDATE', [$anggotaId]);
            if ($anggota === null) {
                throw new RuntimeException('Anggota tidak ditemukan.');
            }

            $sudahDibayar = total_iuran_periode($conn, $anggotaId, $bulan, $tahun);

            if ($sudahDibayar >= IURAN_WAJIB) {
                $conn->rollback();
                flash('warning', 'Iuran periode tersebut sudah lunas. Pengajuan tidak dapat disetujui.');
                redirect($redirectTo);
            }

            if (($sudahDibayar + $jumlah) > IURAN_WAJIB + 0.0001) {
                $conn->rollback();
                flash(
                    'warning',
                    'Nominal pengajuan melebihi sisa tagihan (' . rupiah(max(0.0, IURAN_WAJIB - $sudahDibayar))
                    . '). Sesuaikan nominal atau tolak pengajuan.'
                );
                redirect($redirectTo);
            }

            $stmt = db_run(
                $conn,
                'INSERT INTO kas (anggota_id, bulan, tahun, jumlah) VALUES (?, ?, ?, ?)',
                [$anggotaId, $bulan, $tahun, $jumlah]
            );
            $kasId = (int) $conn->insert_id;
            $stmt->close();

            // Kunci ganda: hanya boleh berubah bila status masih pending.
            $upd = db_run(
                $conn,
                'UPDATE pembayaran
                 SET status = \'approved\', kas_id = ?, verified_by = ?, verified_at = NOW(), alasan_tolak = NULL
                 WHERE id = ? AND status = \'pending\'',
                [$kasId, (int) $admin['id'], $id]
            );
            $terpengaruh = $upd->affected_rows;
            $upd->close();

            if ($terpengaruh !== 1) {
                $conn->rollback();
                flash('warning', 'Pengajuan sudah diproses oleh admin lain.');
                redirect($redirectTo);
            }

            $conn->commit();
            flash('success', 'Pembayaran disetujui dan dicatat ke kas koperasi (' . rupiah($jumlah) . ').');
            redirect($redirectTo);
        } catch (Throwable $exception) {
            try {
                $conn->rollback();
            } catch (Throwable $ignored) {
            }
            error_log('[KOPERASI] Gagal menyetujui pembayaran: ' . $exception->getMessage());
            $errors[] = 'Gagal menyetujui pembayaran. Silakan coba lagi.';
        }
    } elseif ($aksi === 'reject') {
        $alasan = post('alasan_tolak');
        if ($alasan === '') {
            $errors[] = 'Alasan penolakan wajib diisi.';
        } elseif (mb_strlen($alasan) > 255) {
            $errors[] = 'Alasan penolakan maksimal 255 karakter.';
        }

        if ($errors === []) {
            try {
                $conn->begin_transaction();

                $terkunci = db_one($conn, 'SELECT * FROM pembayaran WHERE id = ? FOR UPDATE', [$id]);
                if ($terkunci === null) {
                    throw new RuntimeException('Pengajuan tidak ditemukan.');
                }
                if ((string) $terkunci['status'] !== 'pending') {
                    $conn->rollback();
                    flash('warning', 'Pengajuan tersebut sudah diproses sebelumnya.');
                    redirect($redirectTo);
                }

                $upd = db_run(
                    $conn,
                    'UPDATE pembayaran
                     SET status = \'rejected\', alasan_tolak = ?, verified_by = ?, verified_at = NOW()
                     WHERE id = ? AND status = \'pending\'',
                    [$alasan, (int) $admin['id'], $id]
                );
                $terpengaruh = $upd->affected_rows;
                $upd->close();

                if ($terpengaruh !== 1) {
                    $conn->rollback();
                    flash('warning', 'Pengajuan sudah diproses oleh admin lain.');
                    redirect($redirectTo);
                }

                $conn->commit();
                flash('success', 'Pengajuan pembayaran ditolak.');
                redirect($redirectTo);
            } catch (Throwable $exception) {
                try {
                    $conn->rollback();
                } catch (Throwable $ignored) {
                }
                error_log('[KOPERASI] Gagal menolak pembayaran: ' . $exception->getMessage());
                $errors[] = 'Gagal menolak pembayaran. Silakan coba lagi.';
            }
        }
    }
}

/* -------------------------------------------------------------------------
 * Form penolakan (GET ?tolak=ID)
 * ---------------------------------------------------------------------- */
$tolakId = get_int('tolak');
$tolakPengajuan = null;
if ($tolakId > 0) {
    $tolakPengajuan = db_one(
        $conn,
        'SELECT p.*, a.nama, a.kode_anggota
         FROM pembayaran p
         LEFT JOIN anggota a ON a.id = p.anggota_id
         WHERE p.id = ? AND p.status = \'pending\' LIMIT 1',
        [$tolakId]
    );
    if ($tolakPengajuan === null) {
        flash('warning', 'Pengajuan yang ingin ditolak tidak ditemukan atau sudah diproses.');
        redirect($kembali);
    }
}

/* -------------------------------------------------------------------------
 * Data daftar pengajuan
 * ---------------------------------------------------------------------- */
$where = [];
$params = [];

if ($statusFilter !== 'semua') {
    $where[] = 'p.status = ?';
    $params[] = $statusFilter;
}
if ($anggotaFilter > 0) {
    $where[] = 'p.anggota_id = ?';
    $params[] = $anggotaFilter;
}
if ($bulanFilter !== '') {
    $where[] = 'p.bulan = ?';
    $params[] = $bulanFilter;
}
if ($tahunFilter > 0) {
    $where[] = 'p.tahun = ?';
    $params[] = $tahunFilter;
}

$whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);

$page = max(1, get_int('hal'));
$perPage = 15;
$total = (int) db_value($conn, 'SELECT COUNT(*) FROM pembayaran p' . $whereSql, $params);
$pagination = paginate($total, $perPage, $page);

$daftar = db_all(
    $conn,
    'SELECT p.*, a.nama, a.kode_anggota,
            v.nama AS verifikator
     FROM pembayaran p
     LEFT JOIN anggota a ON a.id = p.anggota_id
     LEFT JOIN users v ON v.id = p.verified_by'
    . $whereSql
    . ' ORDER BY (p.status = \'pending\') DESC, p.created_at DESC, p.id DESC
        LIMIT ' . (int) $pagination['per_page'] . ' OFFSET ' . (int) $pagination['offset'],
    $params
);

$jumlahPending = jumlah_pending_verifikasi($conn);
$anggotaList = anggota_options($conn);

render_app_start(
    'Verifikasi Pembayaran',
    'verifikasi_pembayaran.php',
    'Verifikasi Pembayaran',
    'Tinjau bukti transfer anggota, lalu setujui atau tolak pengajuan.'
);
?>

<?= render_errors($errors) ?>

<?php if ($tolakPengajuan !== null): ?>
    <section class="panel panel-danger">
        <div class="panel-head">
            <div>
                <h2>Tolak Pengajuan Pembayaran</h2>
                <p>Berikan alasan yang jelas agar anggota dapat memperbaiki pengajuannya.</p>
            </div>
            <a class="btn btn-ghost" href="<?= e($kembali) ?>"><?= icon('back') ?> Batal</a>
        </div>

        <dl class="summary-list">
            <div><dt>Anggota</dt><dd><?= e($tolakPengajuan['nama'] ?? 'Anggota dihapus') ?> (<?= e($tolakPengajuan['kode_anggota'] ?? '-') ?>)</dd></div>
            <div><dt>Periode</dt><dd><?= e($tolakPengajuan['bulan']) ?> <?= e((string) $tolakPengajuan['tahun']) ?></dd></div>
            <div><dt>Nominal</dt><dd><?= rupiah($tolakPengajuan['jumlah']) ?></dd></div>
            <div><dt>Metode</dt><dd><?= e(metode_pembayaran_label((string) $tolakPengajuan['metode'])) ?></dd></div>
            <div><dt>Bukti</dt><dd><a class="table-link" href="bukti.php?id=<?= (int) $tolakPengajuan['id'] ?>" target="_blank" rel="noopener">Lihat bukti transfer</a></dd></div>
        </dl>

        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="reject">
            <input type="hidden" name="id" value="<?= (int) $tolakPengajuan['id'] ?>">
            <input type="hidden" name="kembali" value="<?= e($kembali) ?>">

            <div class="field">
                <label for="alasan_tolak">Alasan Penolakan <span class="required">*</span></label>
                <textarea id="alasan_tolak" name="alasan_tolak" maxlength="255" required
                          placeholder="Contoh: bukti transfer tidak terbaca / nominal tidak sesuai"></textarea>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-danger"><?= icon('x') ?> Tolak Pengajuan</button>
                <a class="btn btn-ghost" href="<?= e($kembali) ?>"><?= icon('back') ?> Batal</a>
            </div>
        </form>
    </section>
<?php endif; ?>

<section class="stats">
    <article class="stat">
        <span class="stat-icon stat-icon-amber"><?= icon('clock') ?></span>
        <div>
            <p class="stat-label">Menunggu Verifikasi</p>
            <h2><?= number_format($jumlahPending, 0, ',', '.') ?></h2>
            <p class="stat-hint">Pengajuan pending</p>
        </div>
    </article>
    <article class="stat">
        <span class="stat-icon stat-icon-green"><?= icon('check') ?></span>
        <div>
            <p class="stat-label">Disetujui</p>
            <h2><?= number_format((int) db_value($conn, 'SELECT COUNT(*) FROM pembayaran WHERE status = \'approved\''), 0, ',', '.') ?></h2>
            <p class="stat-hint">Tercatat ke kas</p>
        </div>
    </article>
    <article class="stat">
        <span class="stat-icon"><?= icon('x') ?></span>
        <div>
            <p class="stat-label">Ditolak</p>
            <h2><?= number_format((int) db_value($conn, 'SELECT COUNT(*) FROM pembayaran WHERE status = \'rejected\''), 0, ',', '.') ?></h2>
            <p class="stat-hint">Tidak masuk kas</p>
        </div>
    </article>
</section>

<section class="panel">
    <div class="panel-head">
        <div>
            <h2>Daftar Pengajuan</h2>
            <p><?= number_format($total, 0, ',', '.') ?> pengajuan sesuai filter.</p>
        </div>
        <a class="btn btn-ghost btn-sm" href="pengaturan_pembayaran.php"><?= icon('bank') ?> Pengaturan Rekening/QRIS</a>
    </div>

    <form method="get" class="filter-bar">
        <select name="status" aria-label="Status">
            <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Menunggu verifikasi</option>
            <option value="approved" <?= $statusFilter === 'approved' ? 'selected' : '' ?>>Disetujui</option>
            <option value="rejected" <?= $statusFilter === 'rejected' ? 'selected' : '' ?>>Ditolak</option>
            <option value="semua" <?= $statusFilter === 'semua' ? 'selected' : '' ?>>Semua status</option>
        </select>

        <select name="anggota_id" aria-label="Anggota">
            <option value="">— Semua anggota —</option>
            <?php foreach ($anggotaList as $a): ?>
                <option value="<?= (int) $a['id'] ?>" <?= $anggotaFilter === (int) $a['id'] ? 'selected' : '' ?>>
                    <?= e($a['nama']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select name="bulan" aria-label="Bulan">
            <option value="">— Semua bulan —</option>
            <?php foreach (bulan_list() as $bulan): ?>
                <option value="<?= e($bulan) ?>" <?= $bulanFilter === $bulan ? 'selected' : '' ?>><?= e($bulan) ?></option>
            <?php endforeach; ?>
        </select>

        <input type="number" name="tahun" value="<?= $tahunFilter > 0 ? e((string) $tahunFilter) : '' ?>" min="2000" max="2100" step="1" placeholder="Tahun" aria-label="Tahun">

        <button type="submit" class="btn btn-secondary"><?= icon('search') ?> Filter</button>
        <?php if ($filterParams !== []): ?>
            <a class="btn btn-ghost" href="verifikasi_pembayaran.php"><?= icon('x') ?> Reset</a>
        <?php endif; ?>
    </form>

    <?php if ($daftar === []): ?>
        <div class="empty-state compact">
            <?= icon('verify') ?>
            <h3>Tidak ada pengajuan</h3>
            <p>Belum ada pengajuan pembayaran yang cocok dengan filter ini.</p>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Diajukan</th>
                        <th>Anggota</th>
                        <th>Periode</th>
                        <th class="text-right">Nominal</th>
                        <th>Metode</th>
                        <th>Bukti</th>
                        <th>Status</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($daftar as $row): ?>
                        <tr>
                            <td class="text-muted"><?= e(tanggal_indo($row['created_at'], true)) ?></td>
                            <td>
                                <strong><?= e($row['nama'] ?? 'Anggota dihapus') ?></strong>
                                <div class="table-sub"><?= e($row['kode_anggota'] ?? '-') ?></div>
                            </td>
                            <td><?= e($row['bulan']) ?> <?= e((string) $row['tahun']) ?></td>
                            <td class="text-right"><strong><?= rupiah($row['jumlah']) ?></strong></td>
                            <td><?= e(metode_pembayaran_label((string) $row['metode'])) ?></td>
                            <td>
                                <a class="btn btn-sm btn-ghost" href="bukti.php?id=<?= (int) $row['id'] ?>" target="_blank" rel="noopener">
                                    <?= icon('image') ?> Lihat
                                </a>
                            </td>
                            <td>
                                <?= badge_status_pembayaran((string) $row['status']) ?>
                                <?php if ($row['status'] === 'rejected' && (string) $row['alasan_tolak'] !== ''): ?>
                                    <div class="reject-note"><?= icon('alert') ?> <?= e($row['alasan_tolak']) ?></div>
                                <?php endif; ?>
                                <?php if ($row['status'] !== 'pending' && $row['verified_at'] !== null): ?>
                                    <div class="table-sub">
                                        oleh <?= e($row['verifikator'] ?? 'admin') ?>
                                        · <?= e(tanggal_indo($row['verified_at'], true)) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="text-right">
                                <?php if ($row['status'] === 'pending'): ?>
                                    <div class="table-actions">
                                        <form method="post" class="inline-form"
                                              data-confirm="Setujui pembayaran ini dan catat ke kas?">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="aksi" value="approve">
                                            <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                            <input type="hidden" name="kembali" value="<?= e($kembali) ?>">
                                            <button type="submit" class="btn btn-sm btn-primary">
                                                <?= icon('check') ?> Setujui
                                            </button>
                                        </form>
                                        <a class="btn btn-sm btn-danger-soft" href="verifikasi_pembayaran.php?tolak=<?= (int) $row['id'] ?>">
                                            <?= icon('x') ?> Tolak
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted text-sm">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="table-footer">
            <span class="text-muted text-sm">
                Menampilkan <?= (int) $pagination['offset'] + 1 ?>–<?= min($total, (int) $pagination['offset'] + $pagination['per_page']) ?>
                dari <?= number_format($total, 0, ',', '.') ?> pengajuan
            </span>
            <?= render_pagination($pagination, $filterParams) ?>
        </div>
    <?php endif; ?>
</section>

<?php render_app_end(); ?>
