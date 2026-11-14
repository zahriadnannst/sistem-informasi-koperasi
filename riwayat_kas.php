<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_member();

$user = current_user();
$dbUser = db_one(
    $conn,
    'SELECT id, nama, username, anggota_id FROM users WHERE id = ? LIMIT 1',
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

$tahun = get_int('tahun');
if ($tahun !== 0 && ($tahun < 2000 || $tahun > 2100)) {
    $tahun = 0;
}

$page = max(1, get_int('hal'));
$perPage = 15;

$total = 0;
$totalNominal = 0.0;
$pagination = paginate(0, $perPage, 1);
$transaksi = [];
$tahunList = [];
$statusBulanIni = 0.0;

if ($anggota !== null) {
    $where = ' WHERE kas.anggota_id = ?';
    $params = [$anggotaId];

    if ($tahun > 0) {
        $where .= ' AND kas.tahun = ?';
        $params[] = $tahun;
    }

    $total = (int) db_value($conn, 'SELECT COUNT(*) FROM kas' . $where, $params);
    $totalNominal = (float) db_value($conn, 'SELECT COALESCE(SUM(kas.jumlah), 0) FROM kas' . $where, $params);

    $pagination = paginate($total, $perPage, $page);
    $transaksi = db_all(
        $conn,
        'SELECT kas.id, kas.bulan, kas.tahun, kas.jumlah, kas.tanggal,
                (SELECT COALESCE(SUM(k2.jumlah), 0)
                 FROM kas k2
                 WHERE k2.anggota_id = kas.anggota_id
                   AND k2.bulan = kas.bulan
                   AND k2.tahun = kas.tahun) AS total_periode
         FROM kas' . $where . '
         ORDER BY kas.tahun DESC, kas.tanggal DESC, kas.id DESC
         LIMIT ' . (int) $pagination['per_page'] . ' OFFSET ' . (int) $pagination['offset'],
        $params
    );

    $statusBulanIni = (float) db_value(
        $conn,
        'SELECT COALESCE(SUM(jumlah), 0) FROM kas WHERE anggota_id = ? AND bulan = ? AND tahun = ?',
        [$anggotaId, bulan_ini(), (int) date('Y')]
    );

    $rows = db_all(
        $conn,
        'SELECT DISTINCT tahun FROM kas WHERE anggota_id = ? AND tahun IS NOT NULL ORDER BY tahun DESC',
        [$anggotaId]
    );
    $tahunList = array_map(static fn (array $row): int => (int) $row['tahun'], $rows);
    if (!in_array((int) date('Y'), $tahunList, true)) {
        $tahunList[] = (int) date('Y');
        rsort($tahunList);
    }
}

render_app_start(
    'Riwayat Iuran Saya',
    'riwayat_kas.php',
    'Riwayat Iuran Saya',
    'Daftar pembayaran iuran yang tercatat atas nama Anda.'
);
?>

<?php if ($anggota === null): ?>
    <div class="alert alert-warning" role="alert">
        <?= icon('alert') ?>
        <div>
            <strong>Akun belum terhubung ke data anggota.</strong>
            <p>Hubungi admin agar riwayat iuran Anda dapat ditampilkan.</p>
        </div>
    </div>
<?php else: ?>
    <div class="stats">
        <article class="stat">
            <span class="stat-icon stat-icon-green"><?= icon('wallet') ?></span>
            <div>
                <p class="stat-label">Total Iuran</p>
                <h2><?= rupiah($totalNominal) ?></h2>
                <p class="stat-hint"><?= $tahun > 0 ? 'Tahun ' . $tahun : 'Seluruh periode' ?></p>
            </div>
        </article>

        <article class="stat">
            <span class="stat-icon stat-icon-blue"><?= icon('history') ?></span>
            <div>
                <p class="stat-label">Transaksi</p>
                <h2><?= number_format($total, 0, ',', '.') ?></h2>
                <p class="stat-hint">Pembayaran tercatat</p>
            </div>
        </article>

        <article class="stat">
            <span class="stat-icon stat-icon-amber"><?= icon('calendar') ?></span>
            <div>
                <p class="stat-label">Status <?= e(bulan_ini()) ?></p>
                <div class="stat-badge"><?= status_badge($statusBulanIni) ?></div>
                <p class="stat-hint"><?= rupiah($statusBulanIni) ?> dari <?= rupiah(IURAN_WAJIB) ?></p>
            </div>
        </article>

        <article class="stat">
            <span class="stat-icon"><?= icon('user') ?></span>
            <div>
                <p class="stat-label">Kode Anggota</p>
                <h2><?= e($anggota['kode_anggota']) ?></h2>
                <p class="stat-hint"><?= e($anggota['nama']) ?></p>
            </div>
        </article>
    </div>

    <section class="panel">
        <div class="panel-head">
            <div>
                <h2>Riwayat Pembayaran</h2>
                <p>Total <?= number_format($total, 0, ',', '.') ?> transaksi ditemukan.</p>
            </div>
            <button type="button" class="btn btn-secondary no-print" data-print>
                <?= icon('print') ?> Cetak Halaman
            </button>
        </div>

        <form method="get" class="filter-bar no-print">
            <select name="tahun" aria-label="Filter tahun">
                <option value="0">Semua tahun</option>
                <?php foreach ($tahunList as $opsiTahun): ?>
                    <option value="<?= $opsiTahun ?>" <?= $tahun === $opsiTahun ? 'selected' : '' ?>>
                        <?= $opsiTahun ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <button type="submit" class="btn btn-primary"><?= icon('search') ?> Tampilkan</button>

            <?php if ($tahun > 0): ?>
                <a class="btn btn-ghost" href="riwayat_kas.php"><?= icon('x') ?> Reset</a>
            <?php endif; ?>
        </form>

        <?php if ($transaksi === []): ?>
            <div class="empty-state">
                <?= icon('history') ?>
                <h3>Belum ada pembayaran</h3>
                <p>Pembayaran iuran yang dicatat admin akan tampil di halaman ini.</p>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th class="w-narrow">No</th>
                            <th>Tanggal</th>
                            <th>Periode</th>
                            <th class="text-right">Jumlah</th>
                            <th>Status Periode</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transaksi as $index => $row): ?>
                            <tr>
                                <td><?= (int) $pagination['offset'] + $index + 1 ?></td>
                                <td class="text-muted"><?= e(tanggal_indo($row['tanggal'])) ?></td>
                                <td><?= e($row['bulan']) ?> <?= e((string) $row['tahun']) ?></td>
                                <td class="text-right"><strong><?= rupiah($row['jumlah']) ?></strong></td>
                                <td><?= status_badge((float) ($row['total_periode'] ?? $row['jumlah'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="table-footer no-print">
                <span class="text-muted text-sm">
                    Menampilkan <?= (int) $pagination['offset'] + 1 ?>–<?= min($total, (int) $pagination['offset'] + $pagination['per_page']) ?>
                    dari <?= number_format($total, 0, ',', '.') ?> transaksi
                </span>
                <?= render_pagination($pagination, $tahun > 0 ? ['tahun' => $tahun] : []) ?>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>

<?php render_app_end(); ?>
