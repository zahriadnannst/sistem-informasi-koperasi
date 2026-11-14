# Panduan Fitur

## Login dan Logout

- Login terpadu untuk admin dan anggota di `index.php`.
- Username dan password diverifikasi dengan `password_verify()`.
- Setelah login berhasil, session ID diregenerasi dan token CSRF dirotasi.
- Lima percobaan login gagal berturut-turut menyebabkan jeda 30 detik (berbasis session).
- Logout hanya dapat dilakukan melalui POST dengan token CSRF.
- `login.php` dipertahankan sebagai redirect kompatibilitas.

## Hak Akses

| Halaman | Admin | Anggota |
|---|---:|---:|
| Dashboard admin | Ya | Tidak |
| Data anggota / tambah / edit / hapus | Ya | Tidak |
| Input, edit, hapus iuran | Ya | Tidak |
| Riwayat seluruh anggota | Ya | Tidak |
| Rekap matriks dan laporan | Ya | Tidak |
| Dashboard anggota | Tidak | Ya |
| Riwayat iuran pribadi | Tidak | Ya |

Halaman admin memakai `require_admin()`; halaman anggota memakai `require_member()`. Percobaan akses lintas peran diarahkan ke dashboard yang sesuai dengan pesan.

## Dashboard Admin

Menampilkan:

- total anggota;
- total kas;
- iuran tahun berjalan;
- jumlah transaksi;
- status anggota yang lunas pada bulan berjalan;
- transaksi terbaru dengan aksi edit/hapus;
- tautan cepat ke tambah anggota dan laporan.

## Data Anggota

- Pencarian berdasarkan nama, kode, alamat, atau telepon.
- Paginasi 10 baris per halaman.
- Tambah anggota dengan validasi kode unik, nama, alamat, dan telepon.
- Pembuatan akun login anggota bersifat opsional.
- Edit anggota termasuk perubahan username dan reset password.
- Hapus anggota hanya bila belum memiliki riwayat iuran.
- Konfirmasi sebelum penghapusan.

## Input Iuran

- Pilih anggota, bulan, tahun, dan nominal.
- Nominal maksimal `Rp 50.000` per transaksi dan per periode.
- Pembayaran sebagian diperbolehkan.
- Sistem menolak:
  - pembayaran pada periode yang sudah lunas;
  - total pembayaran yang melebihi batas;
  - nominal nol, negatif, atau bukan angka.
- Pengecekan dan penyimpanan dilakukan dalam satu transaksi database dengan penguncian baris anggota untuk mencegah input bersamaan yang melampaui batas.

## Edit dan Hapus Iuran

- Edit dapat mengubah anggota, periode, dan nominal.
- **Setiap** perubahan nominal memeriksa total periode tujuan.
- Transaksi yang sedang diedit dikecualikan dari penjumlahan (`id <> ?`).
- Bila periode tujuan sudah lunas atau total akan melebihi batas, perubahan ditolak dengan pesan sisa pembayaran.
- Hapus transaksi memakai POST + CSRF dan konfirmasi.
- Setelah edit/hapus, total kas dan laporan langsung mengikuti data terbaru.

## Status Pembayaran

Status dihitung dari **total seluruh transaksi** pada kombinasi anggota + bulan + tahun:

| Total | Status |
|---|---|
| `0` | Belum bayar |
| `> 0` dan `< Rp 50.000` | Sebagian |
| `>= Rp 50.000` | Lunas |

Status agregat ditampilkan pada riwayat iuran admin, riwayat anggota, detail laporan, dan pembayaran terbaru di dashboard. Ini memastikan transaksi cicilan tidak salah ditampilkan sebagai "Sebagian" ketika total periode sebenarnya sudah lunas.

## Riwayat Iuran

### Admin

- Filter anggota, bulan, tahun, dan pencarian nama/kode.
- Paginasi 15 baris.
- Ringkasan jumlah transaksi, total iuran, dan jumlah anggota pembayar.
- Aksi edit dan hapus per transaksi.
- Tautan cetak mengikuti filter aktif.

### Anggota

- Hanya menampilkan transaksi milik anggota yang terhubung dengan akun.
- Filter tahun dan paginasi.
- Ringkasan total iuran, jumlah transaksi, dan status bulan berjalan.
- Tombol cetak halaman.

## Rekap Iuran

Matriks 12 bulan per anggota untuk tahun yang dipilih:

- warna lunas, sebagian, dan belum bayar;
- total per anggota dan total per bulan;
- pencarian anggota;
- tautan ke riwayat anggota;
- cetak rekap.

## Laporan dan Cetak

- Filter anggota, bulan, tahun, dan pencarian.
- Ringkasan total iuran, jumlah transaksi, dan jumlah anggota pembayar.
- Rekap per anggota dan detail transaksi.
- Total rekap dan detail dihitung dari filter yang sama.
- Halaman cetak memiliki kop, periode, filter, total, dan tanda tangan.
- Dapat disimpan sebagai PDF melalui dialog cetak browser (A4).

## Pesan dan Validasi

- Pesan sukses/gagal ditampilkan setelah redirect (flash message).
- Error validasi ditampilkan di atas form tanpa menghilangkan input.
- Konfirmasi penghapusan memakai dialog browser.
- Semua output dinamis di-escape untuk mencegah XSS.
