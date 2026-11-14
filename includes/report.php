<?php

declare(strict_types=1);

/**
 * Helper filter dan query laporan iuran.
 * Dipakai oleh riwayat_iuran.php, laporan.php, dan cetak.php.
 */

function laporan_filter(array $source): array
{
    $anggotaId = 0;
    if (isset($source['anggota_id']) && is_scalar($source['anggota_id'])) {
        $anggotaId = max(0, (int) $source['anggota_id']);
    }

    $bulan = '';
    if (isset($source['bulan']) && is_string($source['bulan']) && bulan_valid(trim($source['bulan']))) {
        $bulan = trim($source['bulan']);
    }

    $tahun = '';
    if (isset($source['tahun']) && is_scalar($source['tahun'])) {
        $tahunRaw = trim((string) $source['tahun']);
        if ($tahunRaw !== '' && ctype_digit($tahunRaw)) {
            $tahunInt = (int) $tahunRaw;
            if ($tahunInt >= 2000 && $tahunInt <= 2100) {
                $tahun = (string) $tahunInt;
            }
        }
    }

    $q = '';
    if (isset($source['q']) && is_string($source['q'])) {
        $q = trim($source['q']);
        if (mb_strlen($q) > 100) {
            $q = mb_substr($q, 0, 100);
        }
    }

    return [
        'anggota_id' => $anggotaId,
        'bulan' => $bulan,
        'tahun' => $tahun,
        'q' => $q,
    ];
}

/**
 * @return array{0:string,1:array<int,mixed>}
 */
function kas_filter_sql(array $filter): array
{
    $where = [];
    $params = [];

    if ($filter['anggota_id'] > 0) {
        $where[] = 'kas.anggota_id = ?';
        $params[] = $filter['anggota_id'];
    }

    if ($filter['bulan'] !== '') {
        $where[] = 'kas.bulan = ?';
        $params[] = $filter['bulan'];
    }

    if ($filter['tahun'] !== '') {
        $where[] = 'kas.tahun = ?';
        $params[] = (int) $filter['tahun'];
    }

    if ($filter['q'] !== '') {
        $where[] = '(anggota.nama LIKE ? OR anggota.kode_anggota LIKE ?)';
        $keyword = '%' . $filter['q'] . '%';
        $params[] = $keyword;
        $params[] = $keyword;
    }

    return [
        $where === [] ? '' : ' WHERE ' . implode(' AND ', $where),
        $params,
    ];
}

function laporan_query_params(array $filter): array
{
    $params = [];

    if ($filter['anggota_id'] > 0) {
        $params['anggota_id'] = $filter['anggota_id'];
    }
    if ($filter['bulan'] !== '') {
        $params['bulan'] = $filter['bulan'];
    }
    if ($filter['tahun'] !== '') {
        $params['tahun'] = $filter['tahun'];
    }
    if ($filter['q'] !== '') {
        $params['q'] = $filter['q'];
    }

    return $params;
}

function periode_teks(array $filter): string
{
    if ($filter['bulan'] !== '' && $filter['tahun'] !== '') {
        return $filter['bulan'] . ' ' . $filter['tahun'];
    }

    if ($filter['bulan'] !== '') {
        return 'Bulan ' . $filter['bulan'];
    }

    if ($filter['tahun'] !== '') {
        return 'Tahun ' . $filter['tahun'];
    }

    return 'Semua periode';
}

function laporan_ringkasan(mysqli $conn, array $filter): array
{
    [$where, $params] = kas_filter_sql($filter);

    $row = db_one(
        $conn,
        'SELECT COUNT(*) AS jumlah_transaksi,
                COALESCE(SUM(kas.jumlah), 0) AS total,
                COUNT(DISTINCT kas.anggota_id) AS jumlah_anggota
         FROM kas
         LEFT JOIN anggota ON anggota.id = kas.anggota_id'
        . $where,
        $params
    );

    return [
        'jumlah_transaksi' => (int) ($row['jumlah_transaksi'] ?? 0),
        'total' => (float) ($row['total'] ?? 0),
        'jumlah_anggota' => (int) ($row['jumlah_anggota'] ?? 0),
    ];
}

function laporan_rekap(mysqli $conn, array $filter): array
{
    [$where, $params] = kas_filter_sql($filter);

    return db_all(
        $conn,
        'SELECT anggota.id,
                anggota.kode_anggota,
                anggota.nama,
                COUNT(kas.id) AS jumlah_transaksi,
                COALESCE(SUM(kas.jumlah), 0) AS total
         FROM anggota
         INNER JOIN kas ON kas.anggota_id = anggota.id'
        . $where . '
         GROUP BY anggota.id, anggota.kode_anggota, anggota.nama
         ORDER BY anggota.nama ASC',
        $params
    );
}

function laporan_detail(mysqli $conn, array $filter, int $limit = 0, int $offset = 0): array
{
    [$where, $params] = kas_filter_sql($filter);

    $sql = 'SELECT kas.id, kas.anggota_id, kas.bulan, kas.tahun, kas.jumlah, kas.tanggal,
                   anggota.nama, anggota.kode_anggota,
                   (SELECT COALESCE(SUM(k2.jumlah), 0)
                    FROM kas k2
                    WHERE k2.anggota_id = kas.anggota_id
                      AND k2.bulan = kas.bulan
                      AND k2.tahun = kas.tahun) AS total_periode
            FROM kas
            LEFT JOIN anggota ON anggota.id = kas.anggota_id'
        . $where . '
         ORDER BY kas.tahun DESC, kas.tanggal DESC, kas.id DESC';

    if ($limit > 0) {
        $sql .= ' LIMIT ' . (int) $limit . ' OFFSET ' . max(0, $offset);
    }

    return db_all($conn, $sql, $params);
}

function laporan_detail_total(mysqli $conn, array $filter): int
{
    [$where, $params] = kas_filter_sql($filter);

    return (int) db_value(
        $conn,
        'SELECT COUNT(*)
         FROM kas
         LEFT JOIN anggota ON anggota.id = kas.anggota_id' . $where,
        $params
    );
}
