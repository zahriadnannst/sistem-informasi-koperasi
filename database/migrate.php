<?php

declare(strict_types=1);

/**
 * Migrasi database untuk instalasi lama (tanpa menghapus data).
 *
 * Jalankan dari terminal:
 *   php database/migrate.php
 *
 * Yang dilakukan:
 * 1. Menambah kolom users.anggota_id (relasi akun ke data anggota).
 * 2. Menambah index untuk pencarian iuran dan rekap per periode.
 * 3. Menambah unique index username bila tidak ada duplikat.
 * 4. Menambah unique index kode anggota bila tidak ada duplikat lama.
 * 5. Menambah foreign key bila tidak ada data yatim (orphan).
 * 6. Menghubungkan akun anggota ke data anggota berdasarkan nama.
 * 7. Mengubah password plaintext lama menjadi hash bcrypt.
 *
 * Skrip ini idempoten dan tidak menghapus data. Untuk database produksi,
 * buat backup terlebih dahulu dan jalankan di luar jam sibuk.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Skrip migrasi hanya dapat dijalankan melalui command line.');
}

require_once __DIR__ . '/../koneksi.php';

function tabelPunyaKolom(mysqli $conn, string $table, string $column): bool
{
    $stmt = $conn->prepare(
        'SELECT COUNT(*) AS jumlah
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$table, $column]);
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return (int) ($row['jumlah'] ?? 0) > 0;
}

function tabelPunyaIndex(mysqli $conn, string $table, string $index): bool
{
    $stmt = $conn->prepare(
        'SELECT COUNT(*) AS jumlah
         FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?'
    );
    $stmt->execute([$table, $index]);
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return (int) ($row['jumlah'] ?? 0) > 0;
}

function tabelPunyaForeignKey(mysqli $conn, string $table, string $constraint): bool
{
    $stmt = $conn->prepare(
        'SELECT COUNT(*) AS jumlah
         FROM information_schema.TABLE_CONSTRAINTS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
           AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = \'FOREIGN KEY\''
    );
    $stmt->execute([$table, $constraint]);
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return (int) ($row['jumlah'] ?? 0) > 0;
}

function hitungDuplikat(mysqli $conn, string $sql): int
{
    $result = $conn->query($sql);
    if ($result === false) {
        return 0;
    }

    $row = $result->fetch_assoc();

    return (int) ($row['jumlah'] ?? 0);
}

echo "Migrasi database Koperasi\n";
echo "=========================\n";

try {
    if (!tabelPunyaKolom($conn, 'users', 'anggota_id')) {
        $conn->query(
            'ALTER TABLE users
             ADD COLUMN anggota_id INT(11) NULL DEFAULT NULL AFTER role,
             ADD INDEX idx_users_anggota (anggota_id)'
        );
        echo "[OK] Kolom users.anggota_id ditambahkan.\n";
    } else {
        if (!tabelPunyaIndex($conn, 'users', 'idx_users_anggota')) {
            $conn->query('ALTER TABLE users ADD INDEX idx_users_anggota (anggota_id)');
            echo "[OK] Index users.anggota_id ditambahkan.\n";
        } else {
            echo "[SKIP] Kolom dan index users.anggota_id sudah ada.\n";
        }
    }

    if (!tabelPunyaIndex($conn, 'kas', 'idx_kas_anggota')) {
        $conn->query('ALTER TABLE kas ADD INDEX idx_kas_anggota (anggota_id)');
        echo "[OK] Index kas.anggota_id ditambahkan.\n";
    } else {
        echo "[SKIP] Index kas.anggota_id sudah ada.\n";
    }

    if (!tabelPunyaIndex($conn, 'kas', 'idx_kas_periode')) {
        $conn->query('ALTER TABLE kas ADD INDEX idx_kas_periode (tahun, bulan)');
        echo "[OK] Index periode iuran ditambahkan.\n";
    } else {
        echo "[SKIP] Index periode iuran sudah ada.\n";
    }

    // Index gabungan mempercepat pengecekan total per anggota + periode.
    if (!tabelPunyaIndex($conn, 'kas', 'idx_kas_anggota_periode')) {
        $conn->query(
            'ALTER TABLE kas ADD INDEX idx_kas_anggota_periode (anggota_id, tahun, bulan)'
        );
        echo "[OK] Index gabungan kas anggota + periode ditambahkan.\n";
    } else {
        echo "[SKIP] Index gabungan kas anggota + periode sudah ada.\n";
    }

    if (!tabelPunyaIndex($conn, 'users', 'uq_users_username')) {
        $duplikat = hitungDuplikat(
            $conn,
            'SELECT COUNT(*) AS jumlah FROM (
                SELECT username FROM users
                WHERE username IS NOT NULL AND username <> \'\'
                GROUP BY username HAVING COUNT(*) > 1
            ) AS d'
        );

        if ($duplikat === 0) {
            $conn->query('ALTER TABLE users ADD UNIQUE KEY uq_users_username (username)');
            echo "[OK] Unique index username ditambahkan.\n";
        } else {
            echo "[WARN] Ada {$duplikat} username duplikat. Unique index tidak ditambahkan.\n";
        }
    } else {
        echo "[SKIP] Unique index username sudah ada.\n";
    }

    if (!tabelPunyaIndex($conn, 'anggota', 'uq_anggota_kode')) {
        $duplikatKode = hitungDuplikat(
            $conn,
            'SELECT COUNT(*) AS jumlah FROM (
                SELECT kode_anggota FROM anggota
                WHERE kode_anggota IS NOT NULL AND kode_anggota <> \'\'
                GROUP BY kode_anggota HAVING COUNT(*) > 1
            ) AS d'
        );

        if ($duplikatKode === 0) {
            $conn->query('ALTER TABLE anggota ADD UNIQUE KEY uq_anggota_kode (kode_anggota)');
            echo "[OK] Unique index kode anggota ditambahkan.\n";
        } else {
            echo "[WARN] Ada {$duplikatKode} kode anggota duplikat (data lama). "
                . "Unique index kode anggota tidak ditambahkan.\n";
        }
    } else {
        echo "[SKIP] Unique index kode anggota sudah ada.\n";
    }

    $orphanKas = hitungDuplikat(
        $conn,
        'SELECT COUNT(*) AS jumlah FROM kas k
         LEFT JOIN anggota a ON a.id = k.anggota_id
         WHERE k.anggota_id IS NOT NULL AND a.id IS NULL'
    );
    if (!tabelPunyaForeignKey($conn, 'kas', 'fk_kas_anggota')) {
        if ($orphanKas === 0) {
            $conn->query(
                'ALTER TABLE kas
                 ADD CONSTRAINT fk_kas_anggota FOREIGN KEY (anggota_id)
                 REFERENCES anggota (id) ON DELETE RESTRICT ON UPDATE CASCADE'
            );
            echo "[OK] Foreign key kas.anggota_id ditambahkan.\n";
        } else {
            echo "[WARN] Ada {$orphanKas} transaksi tanpa anggota. Foreign key kas dilewati.\n";
        }
    } else {
        echo "[SKIP] Foreign key kas.anggota_id sudah ada.\n";
    }

    $orphanUsers = hitungDuplikat(
        $conn,
        'SELECT COUNT(*) AS jumlah FROM users u
         LEFT JOIN anggota a ON a.id = u.anggota_id
         WHERE u.anggota_id IS NOT NULL AND a.id IS NULL'
    );
    if (!tabelPunyaForeignKey($conn, 'users', 'fk_users_anggota')) {
        if ($orphanUsers === 0) {
            $conn->query(
                'ALTER TABLE users
                 ADD CONSTRAINT fk_users_anggota FOREIGN KEY (anggota_id)
                 REFERENCES anggota (id) ON DELETE SET NULL ON UPDATE CASCADE'
            );
            echo "[OK] Foreign key users.anggota_id ditambahkan.\n";
        } else {
            echo "[WARN] Ada {$orphanUsers} akun tanpa anggota. Foreign key users dilewati.\n";
        }
    } else {
        echo "[SKIP] Foreign key users.anggota_id sudah ada.\n";
    }

    $conn->query(
        'UPDATE users u
         SET u.anggota_id = (
             SELECT MIN(a.id) FROM anggota a WHERE a.nama = u.nama
         )
         WHERE u.role = \'anggota\' AND u.anggota_id IS NULL'
    );
    echo "[OK] Akun anggota dihubungkan ke data anggota (berdasarkan nama).\n";

    $result = $conn->query('SELECT id, password FROM users ORDER BY id');
    $stmt = $conn->prepare('UPDATE users SET password = ? WHERE id = ?');
    $diubah = 0;

    while ($user = $result->fetch_assoc()) {
        $info = password_get_info((string) $user['password']);

        if (($info['algoName'] ?? 'unknown') === 'unknown') {
            $hash = password_hash((string) $user['password'], PASSWORD_DEFAULT);
            $stmt->execute([$hash, (int) $user['id']]);
            $diubah++;
        }
    }

    $stmt->close();
    echo "[OK] {$diubah} password plaintext diubah menjadi hash.\n";

    echo "=========================\n";
    echo "Migrasi selesai tanpa menghapus data.\n";
} catch (Throwable $exception) {
    fwrite(STDERR, "[GAGAL] " . $exception->getMessage() . "\n");
    exit(1);
}
