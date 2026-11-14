<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_admin();

$filter = laporan_filter($_GET);
$ringkasan = laporan_ringkasan($conn, $filter);
$rekap = laporan_rekap($conn, $filter);

$total = 0.0;
foreach ($rekap as $row) {
    $total += (float) $row['total'];
}

$admin = current_user();
$filterParams = laporan_query_params($filter);

$keteranganFilter = [];
if ($filter['q'] !== '') {
    $keteranganFilter[] = 'Pencarian: "' . $filter['q'] . '"';
}
if ($filter['anggota_id'] > 0) {
    $anggota = db_one($conn, 'SELECT nama, kode_anggota FROM anggota WHERE id = ? LIMIT 1', [$filter['anggota_id']]);
    if ($anggota !== null) {
        $keteranganFilter[] = 'Anggota: ' . $anggota['nama'] . ' (' . $anggota['kode_anggota'] . ')';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cetak Laporan Iuran &middot; <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= e(asset('cetak.css')) ?>">
</head>
<body>
    <div class="print-toolbar no-print">
        <a href="laporan.php?<?= e(http_build_query($filterParams)) ?>" class="toolbar-btn">
            &larr; Kembali ke Laporan
        </a>
        <a href="cetak.php?<?= e(http_build_query($filterParams)) ?>" class="toolbar-btn primary"
           onclick="window.print(); return false;">
            Cetak / Simpan PDF
        </a>
    </div>

    <main class="print-page">
        <header class="kop">
            <div class="kop-logo">K</div>
            <div class="kop-text">
                <h1><?= e(strtoupper(APP_NAME)) ?></h1>
                <p><?= e(APP_TAGLINE) ?></p>
            </div>
        </header>

        <h2 class="doc-title">LAPORAN IURAN ANGGOTA</h2>
        <p class="doc-subtitle">Periode: <?= e(periode_teks($filter)) ?></p>

        <div class="doc-meta">
            <div>
                <span>Dicetak pada</span>
                <strong><?= e(tanggal_panjang()) ?>, <?= e(date('H:i')) ?> WIB</strong>
            </div>
            <div>
                <span>Dicetak oleh</span>
                <strong><?= e($admin['nama'] ?? 'Administrator') ?></strong>
            </div>
            <div>
                <span>Jumlah anggota</span>
                <strong><?= count($rekap) ?> anggota</strong>
            </div>
        </div>

        <?php if ($keteranganFilter !== []): ?>
            <p class="doc-filter">Filter: <?= e(implode(' · ', $keteranganFilter)) ?></p>
        <?php endif; ?>

        <table class="report-table">
            <thead>
                <tr>
                    <th class="col-no">No</th>
                    <th>Kode Anggota</th>
                    <th>Nama Anggota</th>
                    <th class="col-center">Jumlah Transaksi</th>
                    <th class="col-right">Total Iuran</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($rekap === []): ?>
                    <tr>
                        <td colspan="5" class="text-center">Tidak ada data pembayaran pada periode ini.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($rekap as $index => $row): ?>
                        <tr>
                            <td class="col-no"><?= $index + 1 ?></td>
                            <td><?= e($row['kode_anggota']) ?></td>
                            <td><?= e($row['nama']) ?></td>
                            <td class="col-center"><?= (int) $row['jumlah_transaksi'] ?></td>
                            <td class="col-right"><?= rupiah($row['total']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="3">TOTAL</th>
                    <th class="col-center"><?= number_format($ringkasan['jumlah_transaksi'], 0, ',', '.') ?></th>
                    <th class="col-right"><?= rupiah($total) ?></th>
                </tr>
            </tfoot>
        </table>

        <p class="doc-note">
            Total kas pada laporan ini dihitung dari <?= number_format($ringkasan['jumlah_transaksi'], 0, ',', '.') ?>
            transaksi milik <?= number_format($ringkasan['jumlah_anggota'], 0, ',', '.') ?> anggota.
            Data diambil langsung dari database koperasi.
        </p>

        <div class="signature">
            <div class="signature-box">
                <p>Mengetahui,</p>
                <p class="signature-role">Administrator</p>
                <div class="signature-space"></div>
                <p class="signature-name"><?= e($admin['nama'] ?? 'Administrator') ?></p>
            </div>
        </div>

        <footer class="doc-footer">
            <?= e(APP_NAME) ?> &middot; <?= e(APP_TAGLINE) ?> &middot; Dicetak otomatis oleh sistem.
        </footer>
    </main>

    <script>
        window.addEventListener('load', function () {
            window.print();
        });
    </script>
</body>
</html>
