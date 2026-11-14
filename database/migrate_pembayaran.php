<?php

declare(strict_types=1);

/**
 * Migrasi FITUR PEMBAYARAN IURAN ONLINE (aman & idempoten).
 *
 * Jalankan dari terminal:
 *   php database/migrate_pembayaran.php
 *
 * Yang dilakukan:
 * 1. Membuat tabel pengaturan_pembayaran (rekening & QRIS koperasi).
 * 2. Membuat tabel pembayaran (pengajuan pembayaran iuran online).
 * 3. Menambah index & foreign key bila belum ada.
 * 4. Mengisi baris pengaturan default bila belum ada.
 * 5. Menyiapkan direktori storage/ (bukti & QRIS) yang terlindungi.
 *
 * Skrip ini TIDAK menghapus data, tidak menyentuh tabel yang sudah ada selain
 * menambah index/foreign key yang belum ada, dan dapat dijalankan berulang.
 * Jalankan hanya setelah membuat backup database.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Skrip migrasi hanya dapat dijalankan melalui command line.');
}

require_once __DIR__ . '/../koneksi.php';

// Jangan pernah menjalankan migrasi fitur pembayaran pada database utama.
if (strtolower((string) ($dbName ?? '')) === 'db_koperasi') {
    fwrite(STDERR, "[DITOLAK] Migrasi pembayaran diblokir pada database utama 'db_koperasi'. Arahkan konfigurasi ke database pengujian terpisah.\n");
    exit(1);
}
require_once __DIR__ . '/../includes/upload.php';

function mp_tabelAda(mysqli $conn, string $table): bool
{
    $stmt = $conn->prepare(
        'SELECT COUNT(*) AS jumlah FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
    );
    $stmt->execute([$table]);
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return (int) ($row['jumlah'] ?? 0) > 0;
}

function mp_indexAda(mysqli $conn, string $table, string $index): bool
{
    $stmt = $conn->prepare(
        'SELECT COUNT(*) AS jumlah FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?'
    );
    $stmt->execute([$table, $index]);
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return (int) ($row['jumlah'] ?? 0) > 0;
}

function mp_fkAda(mysqli $conn, string $table, string $constraint): bool
{
    $stmt = $conn->prepare(
        'SELECT COUNT(*) AS jumlah FROM information_schema.TABLE_CONSTRAINTS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
           AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = \'FOREIGN KEY\''
    );
    $stmt->execute([$table, $constraint]);
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return (int) ($row['jumlah'] ?? 0) > 0;
}

function mp_hitung(mysqli $conn, string $sql): int
{
    $result = $conn->query($sql);
    if ($result === false) {
        return 0;
    }
    $row = $result->fetch_assoc();

    return (int) ($row['jumlah'] ?? 0);
}

echo "Migrasi fitur pembayaran iuran online\n";
echo "=====================================\n";

try {
    if (!mp_tabelAda($conn, 'anggota') || !mp_tabelAda($conn, 'kas')) {
        echo "[GAGAL] Tabel dasar (anggota/kas) tidak ditemukan. Impor database/schema.sql terlebih dahulu.\n";
        exit(1);
    }

    /* ---------------------------------------------------------------------
     * 1. Tabel pengaturan_pembayaran
     * ------------------------------------------------------------------ */
    if (!mp_tabelAda($conn, 'pengaturan_pembayaran')) {
        $conn->query(
            'CREATE TABLE pengaturan_pembayaran (
                id TINYINT(1) NOT NULL DEFAULT 1,
                nama_bank VARCHAR(100) DEFAULT NULL,
                nomor_rekening VARCHAR(60) DEFAULT NULL,
                nama_pemilik VARCHAR(100) DEFAULT NULL,
                qris_nama VARCHAR(100) DEFAULT NULL,
                qris_gambar VARCHAR(255) DEFAULT NULL,
                qris_gambar_asli VARCHAR(255) DEFAULT NULL,
                catatan TEXT DEFAULT NULL,
                updated_by INT(11) DEFAULT NULL,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci'
        );
        echo "[OK] Tabel pengaturan_pembayaran dibuat.\n";
    } else {
        echo "[SKIP] Tabel pengaturan_pembayaran sudah ada.\n";
    }

    /* ---------------------------------------------------------------------
     * 2. Tabel pembayaran
     * ------------------------------------------------------------------ */
    if (!mp_tabelAda($conn, 'pembayaran')) {
        $conn->query(
            'CREATE TABLE pembayaran (
                id INT(11) NOT NULL AUTO_INCREMENT,
                anggota_id INT(11) NOT NULL,
                bulan VARCHAR(20) NOT NULL,
                tahun INT(11) NOT NULL,
                jumlah DECIMAL(12,2) NOT NULL,
                metode ENUM(\'transfer\', \'qris\') NOT NULL DEFAULT \'transfer\',
                bukti VARCHAR(255) NOT NULL,
                bukti_asli VARCHAR(255) DEFAULT NULL,
                bukti_mime VARCHAR(60) DEFAULT NULL,
                catatan VARCHAR(255) DEFAULT NULL,
                status ENUM(\'pending\', \'approved\', \'rejected\') NOT NULL DEFAULT \'pending\',
                alasan_tolak VARCHAR(255) DEFAULT NULL,
                kas_id INT(11) DEFAULT NULL,
                verified_by INT(11) DEFAULT NULL,
                verified_at DATETIME DEFAULT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_pembayaran_kas (kas_id),
                KEY idx_pembayaran_anggota (anggota_id),
                KEY idx_pembayaran_status (status),
                KEY idx_pembayaran_periode (tahun, bulan),
                KEY idx_pembayaran_anggota_periode (anggota_id, tahun, bulan),
                CONSTRAINT fk_pembayaran_anggota FOREIGN KEY (anggota_id)
                    REFERENCES anggota (id) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT fk_pembayaran_kas FOREIGN KEY (kas_id)
                    REFERENCES kas (id) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT chk_pembayaran_jumlah CHECK (jumlah > 0),
                CONSTRAINT chk_pembayaran_tahun CHECK (tahun BETWEEN 2000 AND 2100)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci'
        );
        echo "[OK] Tabel pembayaran dibuat.\n";
    } else {
        echo "[SKIP] Tabel pembayaran sudah ada.\n";
    }

    /* ---------------------------------------------------------------------
     * 3. Index tambahan (bila tabel sudah ada dari versi sebelumnya)
     * ------------------------------------------------------------------ */
    $indexTambahan = [
        ['pembayaran', 'uq_pembayaran_kas', 'ALTER TABLE pembayaran ADD UNIQUE KEY uq_pembayaran_kas (kas_id)'],
        ['pembayaran', 'idx_pembayaran_anggota', 'ALTER TABLE pembayaran ADD INDEX idx_pembayaran_anggota (anggota_id)'],
        ['pembayaran', 'idx_pembayaran_status', 'ALTER TABLE pembayaran ADD INDEX idx_pembayaran_status (status)'],
        ['pembayaran', 'idx_pembayaran_periode', 'ALTER TABLE pembayaran ADD INDEX idx_pembayaran_periode (tahun, bulan)'],
        ['pembayaran', 'idx_pembayaran_anggota_periode', 'ALTER TABLE pembayaran ADD INDEX idx_pembayaran_anggota_periode (anggota_id, tahun, bulan)'],
    ];

    foreach ($indexTambahan as [$tabel, $nama, $sql]) {
        if (!mp_indexAda($conn, $tabel, $nama)) {
            $conn->query($sql);
            echo "[OK] Index {$tabel}.{$nama} ditambahkan.\n";
        }
    }

    /* ---------------------------------------------------------------------
     * 4. Foreign key (hanya bila tidak ada data yatim)
     * ------------------------------------------------------------------ */
    if (!mp_fkAda($conn, 'pembayaran', 'fk_pembayaran_anggota')) {
        $yatim = mp_hitung(
            $conn,
            'SELECT COUNT(*) AS jumlah FROM pembayaran p
             LEFT JOIN anggota a ON a.id = p.anggota_id WHERE a.id IS NULL'
        );
        if ($yatim === 0) {
            $conn->query(
                'ALTER TABLE pembayaran
                 ADD CONSTRAINT fk_pembayaran_anggota FOREIGN KEY (anggota_id)
                 REFERENCES anggota (id) ON DELETE RESTRICT ON UPDATE CASCADE'
            );
            echo "[OK] Foreign key pembayaran.anggota_id ditambahkan.\n";
        } else {
            echo "[WARN] Ada {$yatim} pengajuan tanpa anggota. Foreign key anggota dilewati.\n";
        }
    }

    if (!mp_fkAda($conn, 'pembayaran', 'fk_pembayaran_kas')) {
        $yatim = mp_hitung(
            $conn,
            'SELECT COUNT(*) AS jumlah FROM pembayaran p
             LEFT JOIN kas k ON k.id = p.kas_id
             WHERE p.kas_id IS NOT NULL AND k.id IS NULL'
        );
        if ($yatim === 0) {
            $conn->query(
                'ALTER TABLE pembayaran
                 ADD CONSTRAINT fk_pembayaran_kas FOREIGN KEY (kas_id)
                 REFERENCES kas (id) ON DELETE SET NULL ON UPDATE CASCADE'
            );
            echo "[OK] Foreign key pembayaran.kas_id ditambahkan.\n";
        } else {
            echo "[WARN] Ada {$yatim} pengajuan dengan kas tidak valid. Foreign key kas dilewati.\n";
        }
    }

    /* ---------------------------------------------------------------------
     * 5. Baris pengaturan default
     * ------------------------------------------------------------------ */
    $conn->query('INSERT INTO pengaturan_pembayaran (id) VALUES (1) ON DUPLICATE KEY UPDATE id = id');
    echo "[OK] Baris pengaturan pembayaran dipastikan ada.\n";

    /* ---------------------------------------------------------------------
     * 6. Direktori penyimpanan terlindungi
     * ------------------------------------------------------------------ */
    pastikan_storage();
    echo "[OK] Direktori storage/ (bukti & QRIS) disiapkan.\n";

    echo "=====================================\n";
    echo "Migrasi selesai tanpa menghapus data.\n";
} catch (Throwable $exception) {
    fwrite(STDERR, "[GAGAL] " . $exception->getMessage() . "\n");
    exit(1);
}
