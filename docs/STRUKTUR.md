# Struktur Proyek

## Prinsip

- **PHP Native** tanpa framework, Composer, atau build step.
- Satu file per halaman utama.
- Logika bersama di folder `includes/`.
- Aset statis di `assets/`.
- Database dan utilitas CLI di `database/`.

## Alur Request

1. Halaman memanggil `includes/bootstrap.php`.
2. Bootstrap menyiapkan timezone, error reporting, session, header keamanan, koneksi database, dan helper.
3. Halaman memanggil `require_login()`, `require_admin()`, atau `require_member()`.
4. Query memakai prepared statement melalui `db_all()`, `db_one()`, `db_value()`, atau `db_run()`.
5. POST diverifikasi dengan `verify_csrf()` sebelum mengubah data.
6. Output dinamis di-escape dengan `e()` atau helper format.
7. Layout dirender dengan `render_app_start()` dan `render_app_end()`.

## File Utama

| File | Fungsi |
|---|---|
| `index.php` | Login terpadu |
| `logout.php` | Logout POST + CSRF |
| `dashboard_admin.php` | Dashboard admin |
| `dashboard_anggota.php` | Dashboard anggota |
| `anggota.php` | Daftar, cari, paginasi anggota |
| `tambah_anggota.php` | Tambah anggota + akun opsional |
| `edit_anggota.php` | Edit anggota + reset password |
| `hapus_anggota.php` | Hapus anggota (dengan proteksi riwayat) |
| `kas.php` | Input iuran |
| `edit_iuran.php` | Edit transaksi iuran |
| `hapus_iuran.php` | Hapus transaksi iuran |
| `riwayat_iuran.php` | Riwayat seluruh transaksi (admin) |
| `riwayat_kas.php` | Riwayat pribadi (anggota) |
| `iuran_anggota.php` | Rekap matriks 12 bulan |
| `laporan.php` | Laporan rekap + detail |
| `cetak.php` | Halaman cetak/PDF |
| `login.php` | Redirect kompatibilitas |
| `update anggota.php` | Redirect kompatibilitas |

## Folder `includes/`

| File | Fungsi |
|---|---|
| `bootstrap.php` | Session, header keamanan, koneksi, error handler |
| `functions.php` | Escape, format, CSRF, paginasi, helper database, ikon |
| `auth.php` | Session user, login/logout, guard peran |
| `layout.php` | Sidebar, topbar, layout, halaman 404 |
| `report.php` | Filter dan query laporan |
| `.htaccess` | Blokir akses langsung ke folder includes |

## Folder `database/`

| File | Fungsi |
|---|---|
| `schema.sql` | Skema kosong untuk instalasi baru |
| `demo_koperasi.sql` | Database contoh fiktif |
| `migrate.php` | Migrasi instalasi lama (CLI, idempoten) |

## Folder `assets/`

| File | Fungsi |
|---|---|
| `style.css` | Tampilan aplikasi dan breakpoint responsif |
| `cetak.css` | Gaya cetak A4 |
| `app.js` | Sidebar mobile, konfirmasi hapus, tombol cetak |

## Folder `docs/`

Dokumentasi instalasi, database, demo, fitur, dan struktur. Folder ini ikut diblokir dari akses web oleh `.htaccess`.

## Konvensi Kode

- `declare(strict_types=1);` di semua file PHP.
- Nama fungsi dan variabel menggunakan bahasa Indonesia agar konsisten dengan domain.
- Query selalu memakai placeholder `?`.
- Jangan menampilkan pesan exception mentah kecuali `KOPERASI_DEBUG=1`.
- Setiap operasi yang mengubah data harus POST + CSRF.
- Jangan menambahkan dependensi eksternal tanpa diskusi.

## Menambah Halaman Baru

1. Buat file PHP di root.
2. Panggil `includes/bootstrap.php`.
3. Tambahkan guard yang sesuai.
4. Gunakan helper database dan `e()`.
5. Tambahkan menu di `includes/layout.php` bila perlu.
6. Uji akses anonim, admin, dan anggota.
