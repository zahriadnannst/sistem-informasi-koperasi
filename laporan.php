<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_admin();

$filter = laporan_filter($_GET);
$filterParams = laporan_query_params($filter);

$ringkasan = laporan_ringkasan($conn, $filter);
$rekap = laporan_rekap($conn, $filter);
$detail = laporan_detail($conn, $filter);

$anggotaList = anggota_options($conn);
$tahunList = tahun_tersedia($conn);
$totalRekap = 0.0;
foreach ($rekap as $row) {
    $totalRekap += (float) $row['total'];
}

render_app_start(
    'Laporan',
    'laporan.php',
    'Laporan Iuran',
    'Ringkasan dan rincian iuran koperasi sesuai filter.'
);
?>

<section class="panel">
    <div class="panel-head">
        <div>
            <h2>Filter Laporan</h2>
            <p>Periode: <strong><?= e(periode_teks($filter)) ?></strong><?= $filter['q'] !== '' ? ' · Pencarian: "' . e($filter['q']) . '"' : '' ?></p>
        </div>
        <a class="btn btn-secondary" href="cetak.php?<?= e(http_build_query($filterParams)) ?>" target="_blank" rel="noopener">
            <?= icon('print') ?> Cetak Laporan
        </a>
    </div>

    <form method="get" class="filter-bar">
        <div class="search-input">
            <?= icon('search') ?>
            <input type="text" name="q" value="<?= e($filter['q']) ?>"
                   placeholder="Cari nama atau kode anggota..." maxlength="100">
        </div>

        <select name="anggota_id" aria-label="Filter anggota">
            <option value="">Semua anggota</option>
            <?php foreach ($anggotaList as $anggota): ?>
                <option value="<?= (int) $anggota['id'] ?>"
                    <?= $filter['anggota_id'] === (int) $anggota['id'] ? 'selected' : '' ?>>
                    <?= e($anggota['nama']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select name="bulan" aria-label="Filter bulan">
            <option value="">Semua bulan</option>
            <?php foreach (bulan_list() as $bulan): ?>
                <option value="<?= e($bulan) ?>" <?= $filter['bulan'] === $bulan ? 'selected' : '' ?>>
                    <?= e($bulan) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select name="tahun" aria-label="Filter tahun">
            <option value="">Semua tahun</option>
            <?php foreach ($tahunList as $tahun): ?>
                <option value="<?= (int) $tahun ?>" <?= $filter['tahun'] === (string) $tahun ? 'selected' : '' ?>>
                    <?= (int) $tahun ?>
                </option>
            <?php endforeach; ?>
        </select>

        <button type="submit" class="btn btn-primary"><?= icon('search') ?> Terapkan</button>

        <?php if ($filterParams !== []): ?>
            <a class="btn btn-ghost" href="laporan.php"><?= icon('x') ?> Reset</a>
        <?php endif; ?>
    </form>

    <div class="mini-stats">
        <div class="mini-stat">
            <span>Total Iuran</span>
            <strong><?= rupiah($ringkasan['total']) ?></strong>
        </div>
        <div class="mini-stat">
            <span>Transaksi</span>
            <strong><?= number_format($ringkasan['jumlah_transaksi'], 0, ',', '.') ?></strong>
        </div>
        <div class="mini-stat">
            <span>Anggota Membayar</span>
            <strong><?= number_format($ringkasan['jumlah_anggota'], 0, ',', '.') ?></strong>
        </div>
        <div class="mini-stat">
            <span>Periode</span>
            <strong><?= e(periode_teks($filter)) ?></strong>
        </div>
    </div>
</section>

<?php if ($rekap === []): ?>
    <section class="panel">
        <div class="empty-state">
            <?= icon('report') ?>
            <h3>Tidak ada data laporan</h3>
            <p>Belum ada pembayaran yang cocok dengan filter yang dipilih.</p>
            <a class="btn btn-primary" href="kas.php"><?= icon('plus') ?> Input Iuran</a>
        </div>
    </section>
<?php else: ?>
    <section class="panel">
        <div class="panel-head">
            <div>
                <h2>Rekap per Anggota</h2>
                <p>Total iuran setiap anggota pada periode terpilih.</p>
            </div>
            <span class="badge badge-info"><?= count($rekap) ?> anggota</span>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th class="w-narrow">No</th>
                        <th>Anggota</th>
                        <th>Kode</th>
                        <th class="text-center">Transaksi</th>
                        <th class="text-right">Total Iuran</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rekap as $index => $row): ?>
                        <tr>
                            <td><?= $index + 1 ?></td>
                            <td><strong><?= e($row['nama']) ?></strong></td>
                            <td><span class="code-pill"><?= e($row['kode_anggota']) ?></span></td>
                            <td class="text-center"><?= (int) $row['jumlah_transaksi'] ?></td>
                            <td class="text-right"><strong><?= rupiah($row['total']) ?></strong></td>
                            <td class="text-right">
                                <a class="btn btn-icon btn-ghost"
                                   href="riwayat_iuran.php?anggota_id=<?= (int) $row['id'] ?><?= $filter['bulan'] !== '' ? '&bulan=' . urlencode($filter['bulan']) : '' ?><?= $filter['tahun'] !== '' ? '&tahun=' . urlencode($filter['tahun']) : '' ?>"
                                   title="Lihat riwayat anggota" aria-label="Lihat riwayat anggota">
                                    <?= icon('history') ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4"><strong>Total</strong></td>
                        <td class="text-right"><strong><?= rupiah($totalRekap) ?></strong></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </section>

    <section class="panel">
        <div class="panel-head">
            <div>
                <h2>Detail Transaksi</h2>
                <p>Seluruh transaksi pada periode dan filter yang sama.</p>
            </div>
            <span class="badge badge-muted"><?= number_format(count($detail), 0, ',', '.') ?> baris</span>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th class="w-narrow">No</th>
                        <th>Tanggal</th>
                        <th>Anggota</th>
                        <th>Periode</th>
                        <th class="text-right">Jumlah</th>
                        <th>Status Periode</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($detail as $index => $row): ?>
                        <tr>
                            <td><?= $index + 1 ?></td>
                            <td class="text-muted"><?= e(tanggal_indo($row['tanggal'])) ?></td>
                            <td>
                                <strong><?= e($row['nama'] ?? 'Anggota dihapus') ?></strong>
                                <div class="table-sub"><?= e($row['kode_anggota'] ?? '-') ?></div>
                            </td>
                            <td><?= e($row['bulan']) ?> <?= e((string) $row['tahun']) ?></td>
                            <td class="text-right"><?= rupiah($row['jumlah']) ?></td>
                            <td><?= status_badge((float) ($row['total_periode'] ?? $row['jumlah'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4"><strong>Total</strong></td>
                        <td class="text-right"><strong><?= rupiah($ringkasan['total']) ?></strong></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </section>
<?php endif; ?>

<?php render_app_end(); ?>
