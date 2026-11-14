-- ============================================================================
-- Skema database Koperasi untuk instalasi baru (tanpa data contoh)
-- ----------------------------------------------------------------------------
-- Buat database terlebih dahulu, misalnya:
--   CREATE DATABASE db_koperasi CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
--   mysql -u root -p db_koperasi < database/schema.sql
--
-- File ini hanya membuat tabel yang belum ada dan tidak menghapus data.
-- Untuk data contoh siap pakai, gunakan database/demo_koperasi.sql.
-- ============================================================================

CREATE TABLE IF NOT EXISTS `anggota` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `kode_anggota` VARCHAR(20) NOT NULL,
    `nama` VARCHAR(100) NOT NULL,
    `alamat` TEXT DEFAULT NULL,
    `telepon` VARCHAR(20) DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_anggota_kode` (`kode_anggota`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `kas` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `anggota_id` INT(11) NOT NULL,
    `bulan` VARCHAR(20) NOT NULL,
    `tahun` INT(11) NOT NULL,
    `jumlah` DECIMAL(12,2) NOT NULL,
    `tanggal` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_kas_anggota` (`anggota_id`),
    KEY `idx_kas_periode` (`tahun`, `bulan`),
    KEY `idx_kas_anggota_periode` (`anggota_id`, `tahun`, `bulan`),
    CONSTRAINT `fk_kas_anggota` FOREIGN KEY (`anggota_id`)
        REFERENCES `anggota` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `chk_kas_jumlah` CHECK (`jumlah` > 0),
    CONSTRAINT `chk_kas_tahun` CHECK (`tahun` BETWEEN 2000 AND 2100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `users` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `nama` VARCHAR(100) NOT NULL,
    `username` VARCHAR(50) NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `role` ENUM('admin', 'anggota') NOT NULL DEFAULT 'anggota',
    `anggota_id` INT(11) DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_users_username` (`username`),
    KEY `idx_users_anggota` (`anggota_id`),
    CONSTRAINT `fk_users_anggota` FOREIGN KEY (`anggota_id`)
        REFERENCES `anggota` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Pengaturan rekening & QRIS koperasi (baris tunggal id = 1).
CREATE TABLE IF NOT EXISTS `pengaturan_pembayaran` (
    `id` TINYINT(1) NOT NULL DEFAULT 1,
    `nama_bank` VARCHAR(100) DEFAULT NULL,
    `nomor_rekening` VARCHAR(60) DEFAULT NULL,
    `nama_pemilik` VARCHAR(100) DEFAULT NULL,
    `qris_nama` VARCHAR(100) DEFAULT NULL,
    `qris_gambar` VARCHAR(255) DEFAULT NULL,
    `qris_gambar_asli` VARCHAR(255) DEFAULT NULL,
    `catatan` TEXT DEFAULT NULL,
    `updated_by` INT(11) DEFAULT NULL,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Pengajuan pembayaran iuran online. Hanya status approved yang masuk ke kas.
CREATE TABLE IF NOT EXISTS `pembayaran` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `anggota_id` INT(11) NOT NULL,
    `bulan` VARCHAR(20) NOT NULL,
    `tahun` INT(11) NOT NULL,
    `jumlah` DECIMAL(12,2) NOT NULL,
    `metode` ENUM('transfer', 'qris') NOT NULL DEFAULT 'transfer',
    `bukti` VARCHAR(255) NOT NULL,
    `bukti_asli` VARCHAR(255) DEFAULT NULL,
    `bukti_mime` VARCHAR(60) DEFAULT NULL,
    `catatan` VARCHAR(255) DEFAULT NULL,
    `status` ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    `alasan_tolak` VARCHAR(255) DEFAULT NULL,
    `kas_id` INT(11) DEFAULT NULL,
    `verified_by` INT(11) DEFAULT NULL,
    `verified_at` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_pembayaran_kas` (`kas_id`),
    KEY `idx_pembayaran_anggota` (`anggota_id`),
    KEY `idx_pembayaran_status` (`status`),
    KEY `idx_pembayaran_periode` (`tahun`, `bulan`),
    KEY `idx_pembayaran_anggota_periode` (`anggota_id`, `tahun`, `bulan`),
    CONSTRAINT `fk_pembayaran_anggota` FOREIGN KEY (`anggota_id`)
        REFERENCES `anggota` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_pembayaran_kas` FOREIGN KEY (`kas_id`)
        REFERENCES `kas` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `chk_pembayaran_jumlah` CHECK (`jumlah` > 0),
    CONSTRAINT `chk_pembayaran_tahun` CHECK (`tahun` BETWEEN 2000 AND 2100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
