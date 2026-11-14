<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_admin();

$q = trim(get('q'));
if (mb_strlen($q) > 100) {
    $q = mb_substr($q, 0, 100);
}

$page = max(1, get_int('hal'));
$perPage = 10;

$whereSql = '';
$params = [];
if ($q !== '') {
    $whereSql = ' WHERE (a.nama LIKE ? OR a.kode_anggota LIKE ? OR a.alamat LIKE ? OR a.telepon LIKE ?)';
    $keyword = '%' . $q . '%';
    $params = [$keyword, $keyword, $keyword, $keyword];
}

$total = (int) db_value($conn, 'SELECT COUNT(*) FROM anggota a' . $whereSql, $params);
$pagination = paginate($total, $perPage, $page);

$sql = 'SELECT a.id, a.kode_anggota, a.nama, a.alamat, a.telepon,
               u.username, u.user_id,
               (SELECT COUNT(*) FROM kas k WHERE k.anggota_id = a.id) AS jumlah_iuran,
               (SELECT COALESCE(SUM(k.jumlah), 0) FROM kas k WHERE k.anggota_id = a.id) AS total_iuran
        FROM anggota a
        LEFT JOIN (
            SELECT anggota_id, MIN(id) AS user_id, MIN(username) AS username
            FROM users
            WHERE anggota_id IS NOT NULL
            GROUP BY anggota_id
        ) u ON u.anggota_id = a.id'
        . $whereSql
        . ' ORDER BY a.nama ASC, a.id ASC
            LIMIT ' . (int) $pagination['per_page'] . ' OFFSET ' . (int) $pagination['offset'];

$anggotaList = db_all($conn, $sql, $params);

render_app_start(
    'Data Anggota',
    'anggota.php',
    'Data Anggota',
    'Kelola data anggota dan akun login koperasi.'
);
?>

<section class="panel">
    <div class="panel-head">
        <div>
            <h2>Daftar Anggota</h2>
            <p><?= number_format($total, 0, ',', '.') ?> anggota terdaftar.</p>
        </div>
        <a class="btn btn-primary" href="tambah_anggota.php">
            <?= icon('plus') ?> Tambah Anggota
        </a>
    </div>

    <form method="get" class="filter-bar">
        <div class="search-input">
            <?= icon('search') ?>
            <input type="text" name="q" value="<?= e($q) ?>" placeholder="Cari nama, kode, alamat, atau telepon..." maxlength="100">
        </div>
        <button type="submit" class="btn btn-secondary"><?= icon('search') ?> Cari</button>
        <?php if ($q !== ''): ?>
            <a class="btn btn-ghost" href="anggota.php"><?= icon('x') ?> Reset</a>
        <?php endif; ?>
    </form>

    <?php if ($anggotaList === []): ?>
        <div class="empty-state">
            <?= icon('users') ?>
            <h3><?= $q !== '' ? 'Anggota tidak ditemukan' : 'Belum ada anggota' ?></h3>
            <p><?= $q !== '' ? 'Coba gunakan kata kunci lain.' : 'Tambahkan anggota pertama untuk mulai mengelola iuran.' ?></p>
            <?php if ($q === ''): ?>
                <a class="btn btn-primary" href="tambah_anggota.php"><?= icon('plus') ?> Tambah Anggota</a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th class="w-narrow">No</th>
                        <th>Kode</th>
                        <th>Nama</th>
                        <th>Kontak</th>
                        <th>Akun Login</th>
                        <th>Iuran</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($anggotaList as $index => $row): ?>
                        <tr>
                            <td><?= (int) $pagination['offset'] + $index + 1 ?></td>
                            <td><span class="code-pill"><?= e($row['kode_anggota']) ?></span></td>
                            <td><strong><?= e($row['nama']) ?></strong></td>
                            <td>
                                <div><?= e($row['telepon'] ?: '-') ?></div>
                                <div class="table-sub text-truncate"><?= e($row['alamat'] ?: '-') ?></div>
                            </td>
                            <td>
                                <?php if ($row['username'] !== null): ?>
                                    <span class="badge badge-info"><?= e($row['username']) ?></span>
                                <?php else: ?>
                                    <span class="badge badge-muted">Belum ada</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?= rupiah($row['total_iuran']) ?></strong>
                                <div class="table-sub"><?= (int) $row['jumlah_iuran'] ?> transaksi</div>
                            </td>
                            <td class="text-right">
                                <div class="table-actions">
                                    <a class="btn btn-icon btn-ghost" href="edit_anggota.php?id=<?= (int) $row['id'] ?>"
                                       title="Edit anggota" aria-label="Edit anggota">
                                        <?= icon('edit') ?>
                                    </a>
                                    <a class="btn btn-icon btn-ghost" href="riwayat_iuran.php?anggota_id=<?= (int) $row['id'] ?>"
                                       title="Lihat riwayat iuran" aria-label="Lihat riwayat iuran">
                                        <?= icon('history') ?>
                                    </a>
                                    <?php if ((int) $row['jumlah_iuran'] > 0): ?>
                                        <button type="button" class="btn btn-icon btn-ghost" disabled
                                                title="Tidak dapat dihapus: anggota memiliki riwayat iuran"
                                                aria-label="Tidak dapat dihapus">
                                            <?= icon('trash') ?>
                                        </button>
                                    <?php else: ?>
                                        <form method="post" action="hapus_anggota.php" class="inline-form"
                                              data-confirm="Hapus anggota <?= e($row['nama']) ?>? Data yang dihapus tidak dapat dikembalikan.">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                            <button type="submit" class="btn btn-icon btn-danger-soft"
                                                    title="Hapus anggota" aria-label="Hapus anggota">
                                                <?= icon('trash') ?>
                                            </button>
                                        </form>
                                    <?php endif; ?>
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
                dari <?= number_format($total, 0, ',', '.') ?> anggota
            </span>
            <?= render_pagination($pagination, $q !== '' ? ['q' => $q] : []) ?>
        </div>
    <?php endif; ?>
</section>

<?php render_app_end(); ?>
