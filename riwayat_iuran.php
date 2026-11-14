<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_admin();

$filter = laporan_filter($_GET);
$filterParams = laporan_query_params($filter);

$page = max(1, get_int('hal'));
$perPage = 15;

$total = laporan_detail_total($conn, $filter);
$pagination = paginate($total, $perPage, $page);
$ringkasan = laporan_ringkasan($conn, $filter);
$transaksi = laporan_detail($conn, $filter, (int) $pagination['per_page'], (int) $pagination['offset']);

$anggotaList = anggota_options($conn);
$tahunList = tahun_tersedia($conn);

render_app_start(
    'Riwayat Iuran',
    'riwayat_iuran.php',
    'Riwayat Iuran',
    'Telusuri, edit, dan hapus transaksi pembayaran iuran.'
);
?>

<section class="panel">
    <div class="panel-head">
        <div>
            <h2>Filter Transaksi</h2>
            <p>Gunakan filter untuk mempersempit daftar riwayat.</p>
        </div>
        <a class="btn btn-secondary" href="cetak.php?<?= e(http_build_query($filterParams)) ?>" target="_blank" rel="noopener">
            <?= icon('print') ?> Cetak
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
            <a class="btn btn-ghost" href="riwayat_iuran.php"><?= icon('x') ?> Reset</a>
        <?php endif; ?>
    </form>

    <div class="mini-stats">
        <div class="mini-stat">
            <span>Transaksi</span>
            <strong><?= number_format($ringkasan['jumlah_transaksi'], 0, ',', '.') ?></strong>
        </div>
        <div class="mini-stat">
            <span>Total Iuran</span>
            <strong><?= rupiah($ringkasan['total']) ?></strong>
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

    <?php if ($transaksi === []): ?>
        <div class="empty-state">
            <?= icon('history') ?>
            <h3>Tidak ada transaksi</h3>
            <p>Tidak ada pembayaran yang cocok dengan filter yang dipilih.</p>
            <a class="btn btn-primary" href="kas.php"><?= icon('plus') ?> Input Iuran</a>
        </div>
    <?php else: ?>
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
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transaksi as $index => $row): ?>
                        <tr>
                            <td><?= (int) $pagination['offset'] + $index + 1 ?></td>
                            <td class="text-muted"><?= e(tanggal_indo($row['tanggal'])) ?></td>
                            <td>
                                <strong><?= e($row['nama'] ?? 'Anggota dihapus') ?></strong>
                                <div class="table-sub"><?= e($row['kode_anggota'] ?? '-') ?></div>
                            </td>
                            <td><?= e($row['bulan']) ?> <?= e((string) $row['tahun']) ?></td>
                            <td class="text-right"><strong><?= rupiah($row['jumlah']) ?></strong></td>
                            <td><?= status_badge((float) ($row['total_periode'] ?? $row['jumlah'])) ?></td>
                            <td class="text-right">
                                <div class="table-actions">
                                    <a class="btn btn-icon btn-ghost" href="edit_iuran.php?id=<?= (int) $row['id'] ?>"
                                       title="Edit transaksi" aria-label="Edit transaksi">
                                        <?= icon('edit') ?>
                                    </a>
                                    <form method="post" action="hapus_iuran.php" class="inline-form"
                                          data-confirm="Hapus transaksi iuran <?= e($row['nama'] ?? 'ini') ?> periode <?= e($row['bulan']) ?> <?= e((string) $row['tahun']) ?>?">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                        <input type="hidden" name="redirect_to" value="riwayat_iuran.php">
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

        <div class="table-footer">
            <span class="text-muted text-sm">
                Menampilkan <?= (int) $pagination['offset'] + 1 ?>–<?= min($total, (int) $pagination['offset'] + $pagination['per_page']) ?>
                dari <?= number_format($total, 0, ',', '.') ?> transaksi
            </span>
            <?= render_pagination($pagination, $filterParams) ?>
        </div>
    <?php endif; ?>
</section>

<?php render_app_end(); ?>
