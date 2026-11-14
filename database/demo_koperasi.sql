-- ============================================================================
-- Database contoh (demo) Koperasi
-- ----------------------------------------------------------------------------
-- Seluruh nama, alamat, nomor telepon, dan akun di bawah ini adalah data fiktif.
-- Tidak ada data pelanggan asli, password asli, atau hash dari database produksi.
--
-- Cara impor:
--   mysql -u root -p < database/demo_koperasi.sql
--
-- Database yang dibuat: koperasi_demo
-- Akun demo (hanya untuk evaluasi, jangan dipakai di produksi):
--   admin / Admin#Koperasi2026
--   nadia / Nadia#Koperasi2026
--   rizky / Rizky#Koperasi2026
-- ============================================================================

CREATE DATABASE IF NOT EXISTS `koperasi_demo`
    DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

USE `koperasi_demo`;

DROP TABLE IF EXISTS `pembayaran`;
DROP TABLE IF EXISTS `pengaturan_pembayaran`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `kas`;
DROP TABLE IF EXISTS `anggota`;

CREATE TABLE `anggota` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `kode_anggota` VARCHAR(20) NOT NULL,
    `nama` VARCHAR(100) NOT NULL,
    `alamat` TEXT DEFAULT NULL,
    `telepon` VARCHAR(20) DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_anggota_kode` (`kode_anggota`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `kas` (
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

CREATE TABLE `users` (
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

CREATE TABLE `pengaturan_pembayaran` (
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

CREATE TABLE `pembayaran` (
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

INSERT INTO `anggota` (`id`, `kode_anggota`, `nama`, `alamat`, `telepon`) VALUES
(1, 'AG001', 'Nadia Putri', 'Jl. Melati No. 1, Kota Demo', '081200000001'),
(2, 'AG002', 'Rizky Pratama', 'Jl. Kenanga No. 2, Kota Demo', '081200000002'),
(3, 'AG003', 'Salsabila Rahma', 'Jl. Anggrek No. 3, Kota Demo', '081200000003'),
(4, 'AG004', 'Fajar Nugroho', 'Jl. Mawar No. 4, Kota Demo', '081200000004'),
(5, 'AG005', 'Intan Maharani', 'Jl. Dahlia No. 5, Kota Demo', '081200000005'),
(6, 'AG006', 'Bagas Setiawan', 'Jl. Cempaka No. 6, Kota Demo', '081200000006'),
(7, 'AG007', 'Citra Dewi', 'Jl. Flamboyan No. 7, Kota Demo', '081200000007'),
(8, 'AG008', 'Dimas Anggara', 'Jl. Kamboja No. 8, Kota Demo', '081200000008'),
(9, 'AG009', 'Elsa Permata', 'Jl. Seroja No. 9, Kota Demo', '081200000009'),
(10, 'AG010', 'Gilang Ramadhan', 'Jl. Teratai No. 10, Kota Demo', '081200000010');

-- Password bcrypt untuk akun demo. Ganti sebelum penggunaan nyata.
INSERT INTO `users` (`id`, `nama`, `username`, `password`, `role`, `anggota_id`) VALUES
(1, 'Administrator Demo', 'admin', '$2y$10$.cCCMHVZ8r8/YwLY6rJSguRHR3OGs8tCGR6xr7ZKEsKeX70ie6bZC', 'admin', NULL),
(2, 'Nadia Putri', 'nadia', '$2y$10$2nyVN5ZA3Eh9lFqd.v8ete8N14Q4hc1TAuJNGH1TkpZzyl9qXdmUS', 'anggota', 1),
(3, 'Rizky Pratama', 'rizky', '$2y$10$YMUmS2YvcoxYr8M6tqW2lOL.4d/XsLM4B0fAQvz9BguqESLhkTU7W', 'anggota', 2);

-- Desember 2025
INSERT INTO `kas` (`id`, `anggota_id`, `bulan`, `tahun`, `jumlah`, `tanggal`) VALUES
(1, 1, 'Desember', 2025, 50000.00, '2025-12-05 09:00:00'),
(2, 2, 'Desember', 2025, 50000.00, '2025-12-05 09:05:00'),
(3, 3, 'Desember', 2025, 50000.00, '2025-12-05 09:10:00'),
(4, 4, 'Desember', 2025, 50000.00, '2025-12-05 09:15:00'),
(5, 5, 'Desember', 2025, 50000.00, '2025-12-05 09:20:00');

-- Januari 2026: anggota 1-8 lunas
INSERT INTO `kas` (`id`, `anggota_id`, `bulan`, `tahun`, `jumlah`, `tanggal`) VALUES
(6, 1, 'Januari', 2026, 50000.00, '2026-01-05 09:00:00'),
(7, 2, 'Januari', 2026, 50000.00, '2026-01-05 09:05:00'),
(8, 3, 'Januari', 2026, 50000.00, '2026-01-05 09:10:00'),
(9, 4, 'Januari', 2026, 50000.00, '2026-01-05 09:15:00'),
(10, 5, 'Januari', 2026, 50000.00, '2026-01-05 09:20:00'),
(11, 6, 'Januari', 2026, 50000.00, '2026-01-06 09:00:00'),
(12, 7, 'Januari', 2026, 50000.00, '2026-01-06 09:05:00'),
(13, 8, 'Januari', 2026, 50000.00, '2026-01-06 09:10:00');

-- Februari 2026: anggota 7 membayar sebagian
INSERT INTO `kas` (`id`, `anggota_id`, `bulan`, `tahun`, `jumlah`, `tanggal`) VALUES
(14, 1, 'Februari', 2026, 50000.00, '2026-02-05 09:00:00'),
(15, 2, 'Februari', 2026, 50000.00, '2026-02-05 09:05:00'),
(16, 3, 'Februari', 2026, 50000.00, '2026-02-05 09:10:00'),
(17, 4, 'Februari', 2026, 50000.00, '2026-02-05 09:15:00'),
(18, 5, 'Februari', 2026, 50000.00, '2026-02-05 09:20:00'),
(19, 6, 'Februari', 2026, 50000.00, '2026-02-06 09:00:00'),
(20, 7, 'Februari', 2026, 25000.00, '2026-02-06 09:05:00');

-- Maret 2026: anggota 5 membayar dua kali (20.000 + 30.000), anggota 6 sebagian
INSERT INTO `kas` (`id`, `anggota_id`, `bulan`, `tahun`, `jumlah`, `tanggal`) VALUES
(21, 1, 'Maret', 2026, 50000.00, '2026-03-05 09:00:00'),
(22, 2, 'Maret', 2026, 50000.00, '2026-03-05 09:05:00'),
(23, 3, 'Maret', 2026, 50000.00, '2026-03-05 09:10:00'),
(24, 4, 'Maret', 2026, 50000.00, '2026-03-05 09:15:00'),
(25, 5, 'Maret', 2026, 20000.00, '2026-03-10 09:00:00'),
(26, 5, 'Maret', 2026, 30000.00, '2026-03-20 09:00:00'),
(27, 6, 'Maret', 2026, 25000.00, '2026-03-10 09:10:00');

-- April 2026
INSERT INTO `kas` (`id`, `anggota_id`, `bulan`, `tahun`, `jumlah`, `tanggal`) VALUES
(28, 1, 'April', 2026, 50000.00, '2026-04-05 09:00:00'),
(29, 2, 'April', 2026, 50000.00, '2026-04-05 09:05:00'),
(30, 3, 'April', 2026, 50000.00, '2026-04-05 09:10:00'),
(31, 4, 'April', 2026, 50000.00, '2026-04-05 09:15:00'),
(32, 5, 'April', 2026, 50000.00, '2026-04-05 09:20:00'),
(33, 6, 'April', 2026, 50000.00, '2026-04-06 09:00:00'),
(34, 7, 'April', 2026, 50000.00, '2026-04-06 09:05:00');

-- Mei 2026: anggota 6 membayar sebagian
INSERT INTO `kas` (`id`, `anggota_id`, `bulan`, `tahun`, `jumlah`, `tanggal`) VALUES
(35, 1, 'Mei', 2026, 50000.00, '2026-05-05 09:00:00'),
(36, 2, 'Mei', 2026, 50000.00, '2026-05-05 09:05:00'),
(37, 3, 'Mei', 2026, 50000.00, '2026-05-05 09:10:00'),
(38, 4, 'Mei', 2026, 50000.00, '2026-05-05 09:15:00'),
(39, 5, 'Mei', 2026, 50000.00, '2026-05-05 09:20:00'),
(40, 6, 'Mei', 2026, 40000.00, '2026-05-06 09:00:00');

-- Juni 2026
INSERT INTO `kas` (`id`, `anggota_id`, `bulan`, `tahun`, `jumlah`, `tanggal`) VALUES
(41, 1, 'Juni', 2026, 50000.00, '2026-06-05 09:00:00'),
(42, 2, 'Juni', 2026, 50000.00, '2026-06-05 09:05:00'),
(43, 3, 'Juni', 2026, 50000.00, '2026-06-05 09:10:00'),
(44, 4, 'Juni', 2026, 50000.00, '2026-06-05 09:15:00'),
(45, 5, 'Juni', 2026, 50000.00, '2026-06-05 09:20:00'),
(46, 6, 'Juni', 2026, 50000.00, '2026-06-06 09:00:00'),
(47, 7, 'Juni', 2026, 50000.00, '2026-06-06 09:05:00'),
(48, 8, 'Juni', 2026, 50000.00, '2026-06-06 09:10:00'),
(49, 9, 'Juni', 2026, 50000.00, '2026-06-06 09:15:00');

-- Juli 2026
INSERT INTO `kas` (`id`, `anggota_id`, `bulan`, `tahun`, `jumlah`, `tanggal`) VALUES
(50, 1, 'Juli', 2026, 50000.00, '2026-07-05 09:00:00'),
(51, 2, 'Juli', 2026, 50000.00, '2026-07-05 09:05:00'),
(52, 3, 'Juli', 2026, 50000.00, '2026-07-05 09:10:00'),
(53, 4, 'Juli', 2026, 50000.00, '2026-07-05 09:15:00');

-- Agustus 2026
INSERT INTO `kas` (`id`, `anggota_id`, `bulan`, `tahun`, `jumlah`, `tanggal`) VALUES
(54, 1, 'Agustus', 2026, 50000.00, '2026-08-05 09:00:00'),
(55, 2, 'Agustus', 2026, 50000.00, '2026-08-05 09:05:00'),
(56, 3, 'Agustus', 2026, 50000.00, '2026-08-05 09:10:00'),
(57, 4, 'Agustus', 2026, 50000.00, '2026-08-05 09:15:00'),
(58, 5, 'Agustus', 2026, 50000.00, '2026-08-05 09:20:00'),
(59, 6, 'Agustus', 2026, 50000.00, '2026-08-06 09:00:00');

-- September 2026
INSERT INTO `kas` (`id`, `anggota_id`, `bulan`, `tahun`, `jumlah`, `tanggal`) VALUES
(60, 1, 'September', 2026, 50000.00, '2026-09-05 09:00:00'),
(61, 2, 'September', 2026, 50000.00, '2026-09-05 09:05:00'),
(62, 3, 'September', 2026, 50000.00, '2026-09-05 09:10:00'),
(63, 4, 'September', 2026, 50000.00, '2026-09-05 09:15:00'),
(64, 5, 'September', 2026, 50000.00, '2026-09-05 09:20:00'),
(65, 6, 'September', 2026, 50000.00, '2026-09-06 09:00:00'),
(66, 7, 'September', 2026, 50000.00, '2026-09-06 09:05:00'),
(67, 8, 'September', 2026, 50000.00, '2026-09-06 09:10:00'),
(68, 9, 'September', 2026, 50000.00, '2026-09-06 09:15:00'),
(69, 10, 'September', 2026, 50000.00, '2026-09-06 09:20:00');

-- Oktober 2026: anggota 4 membayar sebagian
INSERT INTO `kas` (`id`, `anggota_id`, `bulan`, `tahun`, `jumlah`, `tanggal`) VALUES
(70, 1, 'Oktober', 2026, 50000.00, '2026-10-05 09:00:00'),
(71, 2, 'Oktober', 2026, 50000.00, '2026-10-05 09:05:00'),
(72, 3, 'Oktober', 2026, 50000.00, '2026-10-05 09:10:00'),
(73, 4, 'Oktober', 2026, 10000.00, '2026-10-06 09:00:00');

-- Total demo: Rp 3.500.000 dari 73 transaksi.

-- Pengaturan rekening & QRIS koperasi (contoh). Silakan ubah melalui
-- menu Pengaturan Pembayaran setelah login sebagai admin.
INSERT INTO `pengaturan_pembayaran`
    (`id`, `nama_bank`, `nomor_rekening`, `nama_pemilik`, `qris_nama`, `catatan`) VALUES
(1, 'Bank Demo Nusantara', '1234567890', 'Koperasi Sejahtera Demo', 'Koperasi Sejahtera Demo',
 'Mohon unggah bukti transfer setelah melakukan pembayaran iuran.');

-- Tabel pembayaran sengaja dibiarkan kosong pada data contoh.
-- Anggota dapat mengajukan pembayaran melalui menu "Pembayaran Iuran".
