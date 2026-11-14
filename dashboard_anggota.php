<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_member();

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
    ? db_one(
        $conn,
        'SELECT id, kode_anggota, nama, alamat, telepon FROM anggota WHERE id = ? LIMIT 1',
        [$anggotaId]
    )
    : null;

$tahunIni = (int) date('Y');
$bulanIni = bulan_ini();

$totalIuran = 0.0;
$iuranTahunIni = 0.0;
$totalTransaksi = 0;
$bayarBulanIni = 0.0;
$rekapTahun = [];
$pembayaranTerbaru = [];

if ($anggota !== null) {
    $totalIuran = (float) db_value(
        $conn,
        'SELECT COALESCE(SUM(jumlah), 0) FROM kas WHERE anggota_id = ?',
        [$anggotaId]
    );

    $iuranTahunIni = (float) db_value(
        $conn,
        'SELECT COALESCE(SUM(jumlah), 0) FROM kas WHERE anggota_id = ? AND tahun = ?',
        [$anggotaId, $tahunIni]
    );

    $totalTransaksi = (int) db_value(
        $conn,
        'SELECT COUNT(*) FROM kas WHERE anggota_id = ?',
        [$anggotaId]
    );

    $bayarBulanIni = (float) db_value(
        $conn,
        'SELECT COALESCE(SUM(jumlah), 0) FROM kas WHERE anggota_id = ? AND bulan = ? AND tahun = ?',
        [$anggotaId, $bulanIni, $tahunIni]
    );

    $rows = db_all(
        $conn,
        'SELECT bulan, COALESCE(SUM(jumlah), 0) AS total
         FROM kas
         WHERE anggota_id = ? AND tahun = ?
         GROUP BY bulan',
        [$anggotaId, $tahunIni]
    );

    foreach ($rows as $row) {
        $rekapTahun[$row['bulan']] = (float) $row['total'];
    }

    $pembayaranTerbaru = db_all(
        $conn,
        'SELECT id, bulan, tahun, jumlah, tanggal,
                (SELECT COALESCE(SUM(k2.jumlah), 0)
                 FROM kas k2
                 WHERE k2.anggota_id = kas.anggota_id
                   AND k2.bulan = kas.bulan
                   AND k2.tahun = kas.tahun) AS total_periode
         FROM kas
         WHERE anggota_id = ?
         ORDER BY tahun DESC, tanggal DESC, id DESC
         LIMIT 6',
        [$anggotaId]
    );
}

$persenBulanIni = $anggota !== null
    ? min(100, (int) round($bayarBulanIni / IURAN_WAJIB * 100))
    : 0;

render_app_start(
    'Dashboard Anggota',
    'dashboard_anggota.php',
    'Dashboard Anggota',
    'Selamat datang, ' . $dbUser['nama'] . '.'
);
?>

<?php if ($anggota === null): ?>
    <div class="alert alert-warning" role="alert">
        <?= icon('alert') ?>
        <div>
            <strong>Akun belum terhubung ke data anggota.</strong>
            <p>Hubungi admin agar akun <b><?= e($dbUser['username']) ?></b> dihubungkan dengan data anggota koperasi.</p>
        </div>
    </div>

    <div class="panel">
        <h2>Informasi Akun</h2>
        <dl class="summary-list">
            <div><dt>Nama akun</dt><dd><?= e($dbUser['nama']) ?></dd></div>
            <div><dt>Username</dt><dd><?= e($dbUser['username']) ?></dd></div>
            <div><dt>Hak akses</dt><dd><span class="badge badge-info">Anggota</span></dd></div>
        </dl>
        <p class="text-muted">Setelah dihubungkan, riwayat iuran dan status pembayaran Anda akan tampil di halaman ini.</p>
    </div>
<?php else: ?>
    <div class="stats">
        <article class="stat">
            <span class="stat-icon stat-icon-green"><?= icon('wallet') ?></span>
            <div>
                <p class="stat-label">Total Iuran Saya</p>
                <h2><?= rupiah($totalIuran) ?></h2>
                <p class="stat-hint"><?= $totalTransaksi ?> transaksi tercatat</p>
            </div>
        </article>

        <article class="stat">
            <span class="stat-icon"><?= icon('calendar') ?></span>
            <div>
                <p class="stat-label">Iuran <?= e((string) $tahunIni) ?></p>
                <h2><?= rupiah($iuranTahunIni) ?></h2>
                <p class="stat-hint">Total tahun berjalan</p>
            </div>
        </article>

        <article class="stat">
            <span class="stat-icon stat-icon-amber"><?= icon('info') ?></span>
            <div>
                <p class="stat-label">Status <?= e($bulanIni) ?></p>
                <div class="stat-badge"><?= status_badge($bayarBulanIni) ?></div>
                <p class="stat-hint"><?= rupiah($bayarBulanIni) ?> dari <?= rupiah(IURAN_WAJIB) ?></p>
            </div>
        </article>

        <article class="stat">
            <span class="stat-icon stat-icon-blue"><?= icon('user') ?></span>
            <div>
                <p class="stat-label">Kode Anggota</p>
                <h2><?= e($anggota['kode_anggota']) ?></h2>
                <p class="stat-hint">Status: aktif</p>
            </div>
        </article>
    </div>

    <div class="grid-2 wide-left">
        <section class="panel">
            <div class="panel-head">
                <div>
                    <h2>Rekap Iuran <?= e((string) $tahunIni) ?></h2>
                    <p>Status pembayaran Anda setiap bulan.</p>
                </div>
                <a class="btn btn-ghost btn-sm" href="riwayat_kas.php">
                    Riwayat lengkap <?= icon('arrow-right') ?>
                </a>
            </div>

            <div class="month-grid">
                <?php foreach (bulan_list() as $bulan): ?>
                    <?php
                    $totalBulan = $rekapTahun[$bulan] ?? 0.0;
                    $kelas = $totalBulan >= IURAN_WAJIB ? 'is-paid' : ($totalBulan > 0 ? 'is-partial' : 'is-empty');
                    $label = $totalBulan > 0
                        ? number_format($totalBulan / 1000, 0, ',', '.') . 'rb'
                        : '–';
                    ?>
                    <div class="month-tile <?= $kelas ?>"
                         title="<?= e($bulan . ': ' . rupiah($totalBulan)) ?>">
                        <span><?= e(bulan_singkat($bulan)) ?></span>
                        <strong><?= e($label) ?></strong>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="progress-block">
                <div class="progress-label">
                    <strong>Progress <?= e($bulanIni) ?></strong>
                    <span><?= $persenBulanIni ?>%</span>
                </div>
                <div class="progress" role="progressbar" aria-valuenow="<?= $persenBulanIni ?>" aria-valuemin="0" aria-valuemax="100">
                    <span style="width: <?= $persenBulanIni ?>%"></span>
                </div>
                <?php if ($bayarBulanIni >= IURAN_WAJIB): ?>
                    <p class="text-success text-sm"><?= icon('check') ?> Iuran bulan ini sudah lunas. Terima kasih.</p>
                <?php elseif ($bayarBulanIni > 0): ?>
                    <p class="text-warning text-sm"><?= icon('alert') ?> Kurang <?= rupiah(IURAN_WAJIB - $bayarBulanIni) ?> untuk lunas.</p>
                <?php else: ?>
                    <p class="text-muted text-sm"><?= icon('info') ?> Iuran bulan ini belum dibayar.</p>
                <?php endif; ?>
            </div>
        </section>

        <section class="panel">
            <div class="panel-head">
                <div>
                    <h2>Profil Anggota</h2>
                    <p>Data keanggotaan Anda.</p>
                </div>
                <span class="badge badge-success">Aktif</span>
            </div>

            <dl class="summary-list">
                <div><dt>Nama</dt><dd><?= e($anggota['nama']) ?></dd></div>
                <div><dt>Kode anggota</dt><dd><?= e($anggota['kode_anggota']) ?></dd></div>
                <div><dt>Username</dt><dd><?= e($dbUser['username']) ?></dd></div>
                <div><dt>Telepon</dt><dd><?= e($anggota['telepon'] ?: '-') ?></dd></div>
                <div><dt>Alamat</dt><dd><?= e($anggota['alamat'] ?: '-') ?></dd></div>
            </dl>
        </section>
    </div>

    <section class="panel">
        <div class="panel-head">
            <div>
                <h2>Pembayaran Iuran Online</h2>
                <p>Ajukan pembayaran iuran, unggah bukti transfer, dan pantau verifikasi admin.</p>
            </div>
            <a class="btn btn-primary" href="pembayaran_iuran.php"><?= icon('wallet') ?> Buka Pembayaran Iuran</a>
        </div>
        <div class="info-box">
            <?= icon('shield') ?>
            <p>Pengajuan pembayaran tidak langsung masuk kas. Kas koperasi diperbarui setelah admin menyetujui pengajuan Anda.</p>
        </div>
    </section>

    <section class="panel">
        <div class="panel-head">
            <div>
                <h2>Pembayaran Terbaru</h2>
                <p>Enam transaksi iuran terakhir Anda.</p>
            </div>
        </div>

        <?php if ($pembayaranTerbaru === []): ?>
            <div class="empty-state compact">
                <?= icon('history') ?>
                <h3>Belum ada pembayaran</h3>
                <p>Pembayaran iuran yang dicatat admin akan tampil di sini.</p>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Periode</th>
                            <th class="text-right">Jumlah</th>
                            <th>Status Periode</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pembayaranTerbaru as $row): ?>
                            <tr>
                                <td class="text-muted"><?= e(tanggal_indo($row['tanggal'])) ?></td>
                                <td><?= e($row['bulan']) ?> <?= e((string) $row['tahun']) ?></td>
                                <td class="text-right"><strong><?= rupiah($row['jumlah']) ?></strong></td>
                                <td><?= status_badge((float) ($row['total_periode'] ?? $row['jumlah'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>

<?php render_app_end(); ?>
