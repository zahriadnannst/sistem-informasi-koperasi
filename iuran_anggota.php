<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_admin();

$tahun = get_int('tahun');
if ($tahun < 2000 || $tahun > 2100) {
    $tahun = (int) date('Y');
}

$q = trim(get('q'));
if (mb_strlen($q) > 100) {
    $q = mb_substr($q, 0, 100);
}

$bulanList = bulan_list();
$selectBulan = [];
$params = [];

foreach ($bulanList as $index => $bulan) {
    $selectBulan[] = 'COALESCE(SUM(CASE WHEN kas.bulan = ? THEN kas.jumlah ELSE 0 END), 0) AS m' . ($index + 1);
    $params[] = $bulan;
}

$params[] = $tahun;

$whereSql = '';
if ($q !== '') {
    $whereSql = ' WHERE (anggota.nama LIKE ? OR anggota.kode_anggota LIKE ?)';
    $keyword = '%' . $q . '%';
    $params[] = $keyword;
    $params[] = $keyword;
}

$sql = 'SELECT anggota.id, anggota.kode_anggota, anggota.nama,
               COUNT(kas.id) AS jumlah_transaksi,
               ' . implode(",\n               ", $selectBulan) . '
        FROM anggota
        LEFT JOIN kas ON kas.anggota_id = anggota.id AND kas.tahun = ?'
        . $whereSql . '
        GROUP BY anggota.id, anggota.kode_anggota, anggota.nama
        ORDER BY anggota.nama ASC';

$rows = db_all($conn, $sql, $params);

$rekap = [];
$totalPerBulan = array_fill(1, 12, 0.0);
$grandTotal = 0.0;
$totalTransaksi = 0;

foreach ($rows as $row) {
    $cells = [];
    $barisTotal = 0.0;

    for ($i = 1; $i <= 12; $i++) {
        $nilai = (float) $row['m' . $i];
        $cells[$i] = $nilai;
        $barisTotal += $nilai;
        $totalPerBulan[$i] += $nilai;
    }

    $rekap[] = [
        'id' => (int) $row['id'],
        'kode_anggota' => $row['kode_anggota'],
        'nama' => $row['nama'],
        'jumlah_transaksi' => (int) $row['jumlah_transaksi'],
        'cells' => $cells,
        'total' => $barisTotal,
    ];

    $grandTotal += $barisTotal;
    $totalTransaksi += (int) $row['jumlah_transaksi'];
}

$rataRata = $rekap !== [] ? $grandTotal / count($rekap) : 0;
$tahunList = tahun_tersedia($conn);

render_app_start(
    'Rekap Iuran',
    'iuran_anggota.php',
    'Rekap Iuran Anggota',
    'Matriks pembayaran iuran seluruh anggota per bulan.'
);
?>

<section class="panel">
    <div class="panel-head">
        <div>
            <h2>Rekap Tahun <?= e((string) $tahun) ?></h2>
            <p>Klik nama anggota untuk melihat rincian transaksinya.</p>
        </div>
        <a class="btn btn-secondary" href="cetak.php?tahun=<?= $tahun ?>" target="_blank" rel="noopener">
            <?= icon('print') ?> Cetak Rekap
        </a>
    </div>

    <form method="get" class="filter-bar">
        <div class="search-input">
            <?= icon('search') ?>
            <input type="text" name="q" value="<?= e($q) ?>" placeholder="Cari nama atau kode anggota..." maxlength="100">
        </div>

        <select name="tahun" aria-label="Pilih tahun">
            <?php foreach ($tahunList as $opsiTahun): ?>
                <option value="<?= (int) $opsiTahun ?>" <?= $tahun === (int) $opsiTahun ? 'selected' : '' ?>>
                    <?= (int) $opsiTahun ?>
                </option>
            <?php endforeach; ?>
        </select>

        <button type="submit" class="btn btn-primary"><?= icon('search') ?> Tampilkan</button>

        <?php if ($q !== ''): ?>
            <a class="btn btn-ghost" href="iuran_anggota.php?tahun=<?= $tahun ?>"><?= icon('x') ?> Reset</a>
        <?php endif; ?>
    </form>

    <div class="mini-stats">
        <div class="mini-stat">
            <span>Total Iuran <?= e((string) $tahun) ?></span>
            <strong><?= rupiah($grandTotal) ?></strong>
        </div>
        <div class="mini-stat">
            <span>Anggota</span>
            <strong><?= number_format(count($rekap), 0, ',', '.') ?></strong>
        </div>
        <div class="mini-stat">
            <span>Transaksi</span>
            <strong><?= number_format($totalTransaksi, 0, ',', '.') ?></strong>
        </div>
        <div class="mini-stat">
            <span>Rata-rata / Anggota</span>
            <strong><?= rupiah($rataRata) ?></strong>
        </div>
    </div>

    <div class="legend">
        <span><i class="legend-dot is-paid"></i> Lunas (<?= rupiah(IURAN_WAJIB) ?>)</span>
        <span><i class="legend-dot is-partial"></i> Sebagian</span>
        <span><i class="legend-dot is-empty"></i> Belum bayar</span>
    </div>

    <?php if ($rekap === []): ?>
        <div class="empty-state">
            <?= icon('users') ?>
            <h3>Tidak ada anggota</h3>
            <p><?= $q !== '' ? 'Tidak ada anggota yang cocok dengan pencarian.' : 'Belum ada anggota terdaftar.' ?></p>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table-matrix">
                <thead>
                    <tr>
                        <th class="sticky-col">Anggota</th>
                        <?php foreach ($bulanList as $bulan): ?>
                            <th class="text-center" title="<?= e($bulan) ?>"><?= e(bulan_singkat($bulan)) ?></th>
                        <?php endforeach; ?>
                        <th class="text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rekap as $row): ?>
                        <tr>
                            <td class="sticky-col">
                                <a class="table-link" href="riwayat_iuran.php?anggota_id=<?= $row['id'] ?>">
                                    <strong><?= e($row['nama']) ?></strong>
                                </a>
                                <div class="table-sub"><?= e($row['kode_anggota']) ?> &middot; <?= $row['jumlah_transaksi'] ?> transaksi</div>
                            </td>
                            <?php foreach ($bulanList as $index => $bulan): ?>
                                <?php
                                $nilai = $row['cells'][$index + 1];
                                $kelas = $nilai >= IURAN_WAJIB ? 'is-paid' : ($nilai > 0 ? 'is-partial' : 'is-empty');
                                $label = $nilai > 0 ? number_format($nilai / 1000, 0, ',', '.') . 'rb' : '–';
                                ?>
                                <td class="text-center">
                                    <span class="cell-badge <?= $kelas ?>"
                                          title="<?= e($bulan . ': ' . rupiah($nilai)) ?>">
                                        <?= e($label) ?>
                                    </span>
                                </td>
                            <?php endforeach; ?>
                            <td class="text-right"><strong><?= rupiah($row['total']) ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td class="sticky-col"><strong>Total per bulan</strong></td>
                        <?php foreach ($bulanList as $index => $bulan): ?>
                            <td class="text-center">
                                <strong><?= $totalPerBulan[$index + 1] > 0 ? number_format($totalPerBulan[$index + 1] / 1000, 0, ',', '.') . 'rb' : '–' ?></strong>
                            </td>
                        <?php endforeach; ?>
                        <td class="text-right"><strong><?= rupiah($grandTotal) ?></strong></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php render_app_end(); ?>
