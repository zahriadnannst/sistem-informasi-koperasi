<?php

declare(strict_types=1);

/**
 * Helper domain pembayaran iuran online.
 *
 * Alur: anggota mengajukan pembayaran (status pending) -> admin menyetujui
 * (approved, dicatat ke tabel kas) atau menolak (rejected, tidak masuk kas).
 */

const PEMBAYARAN_METODE = ['transfer', 'qris'];
const PEMBAYARAN_STATUS = ['pending', 'approved', 'rejected'];

/**
 * Ambil pengaturan rekening/QRIS koperasi (baris tunggal id = 1).
 * Mengembalikan nilai default bila belum pernah diatur.
 */
function pengaturan_pembayaran(mysqli $conn): array
{
    $default = [
        'id' => 1,
        'nama_bank' => '',
        'nomor_rekening' => '',
        'nama_pemilik' => '',
        'qris_nama' => '',
        'qris_gambar' => '',
        'qris_gambar_asli' => '',
        'catatan' => '',
        'updated_at' => null,
    ];

    try {
        $row = db_one($conn, 'SELECT * FROM pengaturan_pembayaran WHERE id = 1 LIMIT 1');
    } catch (Throwable $exception) {
        return $default;
    }

    if ($row === null) {
        return $default;
    }

    return array_merge($default, array_map(
        static fn ($value) => $value ?? '',
        $row
    ));
}

function metode_pembayaran_label(string $metode): string
{
    return match ($metode) {
        'transfer' => 'Transfer Bank',
        'qris' => 'QRIS',
        default => ucfirst($metode),
    };
}

function metode_pembayaran_valid(string $metode): bool
{
    return in_array($metode, PEMBAYARAN_METODE, true);
}

/**
 * Badge status pengajuan pembayaran.
 */
function badge_status_pembayaran(string $status): string
{
    return match ($status) {
        'pending' => '<span class="badge badge-warning">' . icon('clock') . ' Menunggu verifikasi</span>',
        'approved' => '<span class="badge badge-success">' . icon('check') . ' Disetujui</span>',
        'rejected' => '<span class="badge badge-danger">' . icon('x') . ' Ditolak</span>',
        default => '<span class="badge badge-muted">-</span>',
    };
}

/**
 * Total pengajuan yang masih menunggu verifikasi pada satu periode.
 */
function total_pembayaran_pending(mysqli $conn, int $anggotaId, string $bulan, int $tahun): float
{
    try {
        return (float) db_value(
            $conn,
            'SELECT COALESCE(SUM(jumlah), 0)
             FROM pembayaran
             WHERE anggota_id = ? AND bulan = ? AND tahun = ? AND status = \'pending\'',
            [$anggotaId, $bulan, $tahun]
        );
    } catch (Throwable $exception) {
        return 0.0;
    }
}

/**
 * Ringkasan tagihan satu anggota pada satu periode.
 *
 * @return array{wajib:float,dibayar:float,pending:float,sisa:float}
 */
function ringkasan_tagihan(mysqli $conn, int $anggotaId, string $bulan, int $tahun): array
{
    $dibayar = total_iuran_periode($conn, $anggotaId, $bulan, $tahun);
    $pending = total_pembayaran_pending($conn, $anggotaId, $bulan, $tahun);
    $sisa = max(0.0, IURAN_WAJIB - $dibayar - $pending);

    return [
        'wajib' => (float) IURAN_WAJIB,
        'dibayar' => $dibayar,
        'pending' => $pending,
        'sisa' => $sisa,
    ];
}

/**
 * Jumlah pengajuan menunggu verifikasi (untuk lencana menu admin).
 * Aman walau tabel belum dimigrasi.
 */
function jumlah_pending_verifikasi(mysqli $conn): int
{
    try {
        return (int) db_value(
            $conn,
            'SELECT COUNT(*) FROM pembayaran WHERE status = \'pending\''
        );
    } catch (Throwable $exception) {
        return 0;
    }
}

/**
 * Ambil satu pengajuan milik anggota tertentu (mencegah akses lintas anggota).
 */
function pembayaran_milik_anggota(mysqli $conn, int $id, int $anggotaId): ?array
{
    return db_one(
        $conn,
        'SELECT * FROM pembayaran WHERE id = ? AND anggota_id = ? LIMIT 1',
        [$id, $anggotaId]
    );
}