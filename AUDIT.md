# Audit Proyek Koperasi

Tanggal audit: 14 November 2026
Lingkup: seluruh file PHP, CSS, JavaScript, koneksi database, autentikasi, otorisasi, relasi data, keamanan, dan tampilan.

> Dokumen ini mencatat hasil audit internal, perbaikan yang dilakukan, dan hasil pengujian akhir. Database **live** (`db_koperasi`) tidak diubah selama audit yang mengubah data; seluruh pengujian tulis dijalankan pada database salinan.

## Ringkasan Struktur Database (live)

Database: `db_koperasi` — baseline **31 anggota / 26 kas / 7 user**, total kas **Rp 1.260.000**.

| Tabel | Kolom | Keterangan |
|---|---|---|
| `anggota` | `id`, `kode_anggota`, `nama`, `alamat`, `telepon` | 31 baris data |
| `kas` | `id`, `anggota_id`, `bulan`, `tahun`, `jumlah`, `tanggal` | 26 baris, index `idx_kas_anggota` & `idx_kas_periode` |
| `users` | `id`, `nama`, `username`, `password`, `role`, `anggota_id` | 7 baris, password bcrypt, unique `username`, index `idx_users_anggota` |

Catatan data lama (dipertahankan, tidak dihapus):

- Pembayaran ganda pada Januari 2026 untuk anggota ID 1–10 (masing-masing 2 transaksi). Karena aturan baru, total Januari 2026 sengaja dilaporkan sebagai 20 transaksi / 10 anggota / Rp 1.000.000.
- Kode anggota ganda: `AG001`–`AG010` muncul dua kali (9 pasangan duplikat).
- Terdapat satu akun lama `anggota` yang belum terhubung ke data anggota.

## Daftar Masalah Awal dan Status

| # | Masalah | Tingkat | Status |
|---|---|---|---|
| 1 | Dua halaman login dengan logika berbeda | Tinggi | Diperbaiki |
| 2 | `login.php` memakai kolom `level`, padahal `role` | Tinggi | Diperbaiki |
| 3 | Password plaintext dibandingkan langsung | Kritis | Diperbaiki |
| 4 | Query memakai interpolasi input (SQL injection) | Kritis | Diperbaiki |
| 5 | Tidak ada pemeriksaan session pada halaman internal | Kritis | Diperbaiki |
| 6 | Tidak ada pembatasan hak akses admin vs anggota | Kritis | Diperbaiki |
| 7 | `hapus_iuran.php` memakai kolom `id_iuran` yang tidak ada | Tinggi | Diperbaiki |
| 8 | Link cetak menuju file yang tidak ada | Sedang | Diperbaiki |
| 9 | `update anggota.php` menduplikasi `edit_anggota.php` | Sedang | Diperbaiki |
| 10 | `edit_anggota.php` tidak lengkap (tanpa validasi ID) | Sedang | Diperbaiki |
| 11 | CRUD anggota tanpa validasi kode unik dan tanpa CSRF | Tinggi | Diperbaiki |
| 12 | Hapus memakai GET tanpa konfirmasi server | Tinggi | Diperbaiki |
| 13 | Kas dapat disimpan berulang untuk periode yang sama | Tinggi | Diperbaiki |
| 14 | Riwayat kas anggota menampilkan pembayaran seluruh anggota | Kritis | Diperbaiki |
| 15 | Dashboard anggota mencari user berdasarkan nama (ambigu) | Tinggi | Diperbaiki |
| 16 | Belum ada relasi eksplisit `users` → `anggota` | Tinggi | Diperbaiki (kolom `anggota_id`) |
| 17 | Laporan tanpa filter, total, dan status yang akurat | Sedang | Diperbaiki |
| 18 | Halaman cetak tanpa kop, total, tanda tangan, filter | Sedang | Diperbaiki |
| 19 | CSS duplikat dan tidak responsif | Sedang | Diperbaiki |
| 20 | `<button>` bersarang di dalam `<a>` (HTML tidak valid) | Rendah | Diperbaiki |
| 21 | Tidak ada umpan balik sukses/gagal | Rendah | Diperbaiki |
| 22 | Tidak ada proteksi CSRF | Tinggi | Diperbaiki |
| 23 | Tidak ada validasi input | Tinggi | Diperbaiki |
| 24 | Error database ditampilkan mentah | Sedang | Diperbaiki |
| 25 | Logout masih berupa tautan GET | Sedang | Diperbaiki (POST + CSRF) |
| 26 | Tidak ada pengelolaan/edit iuran | Sedang | Diperbaiki |
| 27 | Anggota tanpa akun tidak bisa diberi akun | Sedang | Diperbaiki |
| 28 | Belum ada pencarian/paginasi anggota & iuran | Rendah | Diperbaiki |
| 29 | Rekap iuran per anggota per bulan belum tersedia | Sedang | Diperbaiki |
| 30 | Dashboard admin tanpa transaksi terbaru/aksi cepat | Rendah | Diperbaiki |
| 31 | Validasi overpay `edit_iuran.php` tidak dijalankan saat hanya nominal berubah | Tinggi | Diperbaiki |
| 32 | Status iuran dihitung per baris, bukan per total periode | Sedang | Diperbaiki |
| 33 | CSRF memakai status nonstandar `419` (dipetakan Apache ke HTTP 500) | Sedang | Diperbaiki (HTTP 403) |
| 34 | Kredensial demo lemah (`123`) tampil di halaman login publik | Tinggi | Diperbaiki |
| 35 | Kredensial database hardcode tanpa dukungan konfigurasi eksternal | Sedang | Diperbaiki |
| 36 | Belum ada header keamanan (CSP, Permissions-Policy, Cache-Control) | Sedang | Diperbaiki |
| 37 | `.htaccess` belum memblokir `backups/`, `database/`, dan file config | Sedang | Diperbaiki |
| 38 | Dump database asli dan data pribadi berisiko ikut terpublikasi | Tinggi | Diperbaiki (`.gitignore` + demo tersanitasi) |

## Perbaikan Kunci

1. **Autentikasi** — login tunggal di `index.php`, `password_hash()`/`password_verify()`, regenerasi session dan rotasi token CSRF setelah login, pembatasan percobaan login berbasis session.
2. **Otorisasi** — `require_login()`, `require_admin()`, `require_member()` pada setiap halaman; isolasi data anggota berdasarkan `users.anggota_id`.
3. **CSRF** — token pada semua form POST; token tidak valid/missing ditolak dengan **HTTP 403** (bukan `419`).
4. **Query** — seluruh query memakai prepared statement; output di-escape `e()`.
5. **Iuran** — dukungan pembayaran sebagian; pengecekan dan penyimpanan dalam **satu transaksi database** dengan `SELECT ... FOR UPDATE` pada baris anggota untuk mencegah dua input bersamaan melampaui batas `Rp 50.000`/periode.
6. **Edit iuran** — selalu memeriksa total periode tujuan (kecuali transaksi yang sedang diedit, `id <> ?`), termasuk saat hanya nominal yang diubah; pengecekan ulang di dalam transaksi + row lock.
7. **Status periode agregat** — status (`Lunas`/`Sebagian`/`Belum bayar`) dihitung dari total `anggota_id + bulan + tahun` dan diterapkan konsisten di riwayat iuran, laporan, riwayat anggota, dan dashboard anggota.
8. **Konfigurasi** — `koneksi.php` mendukung environment variable dan `config.local.php`; `config.example.php` disediakan. Kredensial tidak lagi ditulis di source.
9. **Header keamanan** — CSP, `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`, dan `Cache-Control: no-store`.
10. **Session** — `use_strict_mode`, `use_only_cookies`, cookie `HttpOnly` + `SameSite=Lax`, `Secure` otomatis saat HTTPS.
11. **Perlindungan file** — `.htaccess` memblokir `.sql`, `.md`, log, config, file tersembunyi, folder `backups/`, `database/`, dan `includes/`.
12. **Migrasi** — `database/migrate.php` idempoten: kolom `users.anggota_id`, index, unique index kondisional, foreign key dengan pemeriksaan data yatim, dan konversi password plaintext.
13. **Publikasi aman** — `.gitignore` mengecualikan dump asli, `backups/`, dan `config.local.php`; disediakan `database/schema.sql` (kosong) dan `database/demo_koperasi.sql` (fiktif).

## Evaluasi Constraint Database

| Kandidat | Keputusan | Alasan |
|---|---|---|
| `UNIQUE(users.username)` | Dipakai | Login harus unik |
| `UNIQUE(anggota.kode_anggota)` | Skema baru: dipakai; live lama: dilewati | Data lama punya kode duplikat. Aplikasi tetap memvalidasi kode unik |
| `UNIQUE(kas.anggota_id, bulan, tahun)` | **Tidak dipakai** | Pembayaran sebagian menghasilkan beberapa baris per periode |
| `CHECK(kas.jumlah > 0)` | Skema baru | Mencegah nominal nol/negatif di DB |
| `CHECK(kas.tahun BETWEEN 2000 AND 2100)` | Skema baru | Membatasi tahun tidak wajar |
| `FOREIGN KEY(kas.anggota_id)` | Skema baru / migrasi bila tanpa orphan | Integritas relasi keuangan |
| `FOREIGN KEY(users.anggota_id)` | Skema baru / migrasi bila tanpa orphan | Integritas akun anggota |
| `INDEX(kas.anggota_id, tahun, bulan)` | Migrasi & skema baru | Mempercepat total iuran per periode |

## Pengujian Akhir

Pengujian dijalankan melalui HTTP terhadap server PHP lokal (PHP 8.2.12, MariaDB 10.4.32) dan diverifikasi langsung ke database **salinan** (`koperasi_audit`, `koperasi_demo`, `koperasi_schema_test`). Database live tidak digunakan untuk operasi yang mengubah data.

### Lint

- `php -l` pada seluruh file PHP: **lulus**.

### Suite HTTP database salinan (`koperasi_audit`) — 39/39 lulus

| Kelompok | Cakupan | Hasil |
|---|---|---|
| Autentikasi | Login admin/anggota, password salah ditolak, guard anonim, redirect lintas peran | Lulus |
| Isolasi | Anggota hanya melihat data sendiri; diblokir dari halaman admin & edit iuran | Lulus |
| CSRF | POST tanpa token → 403; token salah → 403; tidak menulis data | Lulus |
| CRUD anggota | Tambah dengan/tanpa akun, akun terhubung, hash bcrypt, edit, duplikat kode ditolak | Lulus |
| Iuran | Sebagian, top-up sampai lunas, overpay ditolak, duplikat periode ditolak | Lulus |
| Status agregat | Dua baris sebagian ditampilkan **Lunas**; detail laporan ikut agregat | Lulus |
| Edit iuran | Nominal saja menolak overpay; nominal dalam sisa diterima; pindah periode kosong diterima | Lulus |
| Laporan & filter | Filter tahun/anggota/bulan, total anggota, cetak, paginasi | Lulus |
| Ketahanan | Parameter URL tidak valid, SQL injection, XSS pada pencarian | Lulus |
| Hapus | Hapus transaksi; anggota dengan riwayat ditolak; anggota tanpa riwayat dihapus + akun ikut terhapus | Lulus |
| Logout | POST + CSRF, session dibersihkan | Lulus |

### Suite HTTP database demo (`koperasi_demo`) — 9/9 lulus

Login admin/anggota demo, total Rp 3.500.000, 10 anggota, laporan, cetak, isolasi anggota, dan penolakan password salah.

### Verifikasi cetak/PDF

Halaman `cetak.php` dirender dengan Edge headless dan disimpan sebagai PDF:

- Header `%PDF-1.4`, ukuran ± 103 KB.
- 2 halaman, `MediaBox` A4 (`595 × 842` pt).
- Memuat kop, judul laporan, filter, total, dan tanda tangan.

### Pemeriksaan Apache (XAMPP)

| Pemeriksaan | Hasil |
|---|---|
| Halaman beranda via Apache | 200 |
| Header keamanan (CSP, X-Frame-Options, nosniff, Referrer-Policy, Permissions-Policy, Cache-Control) | Ada |
| `db_koperasi.sql` / `README.md` / `AUDIT.md` | 403 |
| `includes/functions.php` | 403 |
| `backups/` / `database/migrate.php` | 403 |
| `config.example.php` | 403 |
| POST tanpa / dengan token CSRF salah | 403 (tanpa HTTP 500) |

### Migrasi & skema

- `database/migrate.php` dijalankan dua kali pada `koperasi_audit`: idempoten.
- Index gabungan `idx_kas_anggota_periode` dan dua foreign key ditambahkan pada salinan.
- Unique index kode anggota **dilewati** karena 9 kode duplikat lama.
- `database/schema.sql` diimpor ke database kosong: 3 tabel + 9 constraint.
- `database/demo_koperasi.sql` diimpor: 10 anggota, 73 kas, 3 user, total Rp 3.500.000; ketiga password demo terverifikasi `password_verify()`.

## Catatan & Batasan

- **Database live belum dimigrasikan.** Constraint/index baru hanya diuji pada salinan. Penerapan ke `db_koperasi` memerlukan **backup dan persetujuan pemilik data**.
- Data duplikat lama (kode anggota & pembayaran Januari 2026) **tidak dihapus**; aplikasi hanya mencegah duplikat/overpay baru.
- Status nonstandar `419` tidak dipakai karena Apache XAMPP memetakannya menjadi HTTP 500; digunakan `403`.
- Folder `backups/` berisi dump data nyata dan **tidak boleh** dipublikasikan; sudah diabaikan `.gitignore` dan diblokir `.htaccess`.
- `db_koperasi.sql` di root adalah dump data nyata milik pemilik proyek dan diabaikan Git; jangan menaruhnya di repositori publik.
- Kredensial demo hanya untuk `koperasi_demo`; wajib diganti untuk penggunaan nyata.
- Hasil pengujian di atas mencerminkan database salinan pada saat audit; database salinan telah berubah karena pengujian tulis dan bukan baseline.
