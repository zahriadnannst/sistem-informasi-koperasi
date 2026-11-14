<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_admin();

$totalAnggota = (int) db_value($conn, 'SELECT COUNT(*) FROM anggota');
$totalTransaksi = (int) db_value($conn, 'SELECT COUNT(*) FROM kas');
$totalKas = (float) db_value($conn, 'SELECT COALESCE(SUM(jumlah), 0) FROM kas');
$tahunIni = (int) date('Y');
$iuranTahunIni = (float) db_value(
    $conn,
    'SELECT COALESCE(SUM(jumlah), 0) FROM kas WHERE tahun = ?',
    [$tahunIni]
);

$bulanIni = bulan_ini();
$sudahBayar = (int) db_value(
    $conn,
    'SELECT COUNT(*) FROM (
        SELECT anggota_id
        FROM kas
        WHERE bulan = ? AND tahun = ?
        GROUP BY anggota_id
        HAVING SUM(jumlah) >= ?
    ) AS lunas',
    [$bulanIni, $tahunIni, IURAN_WAJIB]
);
$belumBayar = max(0, $totalAnggota - $sudahBayar);
$persen = $totalAnggota > 0 ? (int) round($sudahBayar / $totalAnggota * 100) : 0;
$pendingVerifikasi = jumlah_pending_verifikasi($conn);

$transaksiTerbaru = db_all(
    $conn,
    'SELECT kas.id, kas.anggota_id, kas.bulan, kas.tahun, kas.jumlah, kas.tanggal,
            anggota.nama, anggota.kode_anggota
     FROM kas
     LEFT JOIN anggota ON anggota.id = kas.anggota_id
     ORDER BY kas.tanggal DESC, kas.id DESC
     LIMIT 6'
);

render_app_start(
    'Dashboard Admin',
    'dashboard_admin.php',
    'Dashboard Admin',
    'Ringkasan anggota, iuran, dan transaksi koperasi.'
);
?>

<div class="stats">
    <article class="stat">
        <span class="stat-icon"><?= icon('users') ?></span>
        <div>
            <p class="stat-label">Total Anggota</p>
            <h2><?= number_format($totalAnggota, 0, ',', '.') ?></h2>
            <p class="stat-hint">Anggota terdaftar</p>
        </div>
    </article>

    <article class="stat">
        <span class="stat-icon stat-icon-green"><?= icon('wallet') ?></span>
        <div>
            <p class="stat-label">Total Kas</p>
            <h2><?= rupiah($totalKas) ?></h2>
            <p class="stat-hint">Akumulasi seluruh iuran</p>
        </div>
    </article>

    <article class="stat">
        <span class="stat-icon stat-icon-amber"><?= icon('calendar') ?></span>
        <div>
            <p class="stat-label">Iuran <?= e((string) $tahunIni) ?></p>
            <h2><?= rupiah($iuranTahunIni) ?></h2>
            <p class="stat-hint">Total iuran tahun berjalan</p>
        </div>
    </article>

    <article class="stat">
        <span class="stat-icon stat-icon-blue"><?= icon('history') ?></span>
        <div>
            <p class="stat-label">Transaksi</p>
            <h2><?= number_format($totalTransaksi, 0, ',', '.') ?></h2>
            <p class="stat-hint">Pembayaran tercatat</p>
        </div>
    </article>
</div>

<div class="grid-2 wide-left">
    <section class="panel">
        <div class="panel-head">
            <div>
                <h2>Transaksi Terbaru</h2>
                <p>Enam pembayaran terakhir yang masuk.</p>
            </div>
            <a class="btn btn-ghost btn-sm" href="riwayat_iuran.php">
                Lihat semua <?= icon('arrow-right') ?>
            </a>
        </div>

        <?php if ($transaksiTerbaru === []): ?>
            <div class="empty-state compact">
                <?= icon('history') ?>
                <h3>Belum ada transaksi</h3>
                <p>Transaksi akan tampil setelah iuran anggota dicatat.</p>
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
                                              data-confirm="Hapus transaksi iuran ini? Data yang dihapus tidak dapat dikembalikan.">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                            <input type="hidden" name="redirect_to" value="dashboard_admin.php">
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

    <div class="stack">
        <section class="panel">
            <div class="panel-head">
                <div>
                    <h2>Status <?= e($bulanIni) ?> <?= e((string) $tahunIni) ?></h2>
                    <p>Anggota yang sudah membayar lunas bulan ini.</p>
                </div>
            </div>

            <div class="progress-label">
                <strong><?= $sudahBayar ?> dari <?= $totalAnggota ?> anggota</strong>
                <span><?= $persen ?>%</span>
            </div>
            <div class="progress" role="progressbar" aria-valuenow="<?= $persen ?>" aria-valuemin="0" aria-valuemax="100">
                <span style="width: <?= $persen ?>%"></span>
            </div>

            <dl class="summary-list">
                <div><dt>Sudah lunas</dt><dd class="text-success"><?= $sudahBayar ?> anggota</dd></div>
                <div><dt>Belum lunas</dt><dd class="text-danger"><?= $belumBayar ?> anggota</dd></div>
                <div><dt>Iuran wajib</dt><dd><?= rupiah(IURAN_WAJIB) ?> / bulan</dd></div>
            </dl>

            <a class="btn btn-primary btn-block" href="kas.php"><?= icon('plus') ?> Input Iuran</a>
        </section>

        <section class="panel">
            <h2>Aksi Cepat</h2>
            <div class="quick-actions">
                <a href="tambah_anggota.php" class="quick-action">
                    <span class="stat-icon"><?= icon('users') ?></span>
                    <span><strong>Tambah Anggota</strong><small>Daftarkan anggota baru</small></span>
                </a>
                <a href="laporan.php" class="quick-action">
                    <span class="stat-icon stat-icon-amber"><?= icon('report') ?></span>
                    <span><strong>Buat Laporan</strong><small>Filter dan cetak laporan</small></span>
                </a>
                <a href="verifikasi_pembayaran.php" class="quick-action">
                    <span class="stat-icon stat-icon-green"><?= icon('verify') ?></span>
                    <span>
                        <strong>Verifikasi Pembayaran<?= $pendingVerifikasi > 0 ? ' (' . $pendingVerifikasi . ')' : '' ?></strong>
                        <small><?= $pendingVerifikasi > 0 ? $pendingVerifikasi . ' pengajuan menunggu' : 'Setujui / tolak pengajuan' ?></small>
                    </span>
                </a>
            </div>
        </section>
    </div>
</div>

<?php render_app_end(); ?>
