# Panduan Instalasi XAMPP

Panduan ini menjelaskan cara menjalankan aplikasi Koperasi di Windows dengan XAMPP. Langkah serupa berlaku untuk Linux/macOS dengan menyesuaikan path.

## 1. Prasyarat

- XAMPP dengan **PHP 8.2+** dan **MariaDB/MySQL**.
- Ekstensi PHP: `mysqli`, `mbstring`, `openssl`.
- Port Apache (80) dan MySQL (3306) tidak dipakai aplikasi lain.

Periksa versi PHP:

```powershell
C:\xampp\php\php.exe -v
```

## 2. Menempatkan Proyek

Letakkan folder proyek di:

```
C:\xampp\htdocs\koperasi
```

Struktur akhir harus memiliki `index.php` langsung di dalam folder tersebut.

## 3. Menjalankan Layanan

Buka **XAMPP Control Panel**, lalu klik **Start** pada:

1. Apache
2. MySQL

Pastikan tidak ada pesan error pada log Apache/MySQL.

## 4. Membuat Database

### Opsi A — Database Demo (disarankan untuk evaluasi)

Impor data contoh yang seluruhnya fiktif:

```powershell
C:\xampp\mysql\bin\mysql.exe -u root < C:\xampp\htdocs\koperasi\database\demo_koperasi.sql
```

Database `koperasi_demo` akan dibuat otomatis.

### Opsi B — Database Kosong untuk Produksi

```powershell
C:\xampp\mysql\bin\mysql.exe -u root -e "CREATE DATABASE db_koperasi CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;"
C:\xampp\mysql\bin\mysql.exe -u root db_koperasi < C:\xampp\htdocs\koperasi\database\schema.sql
```

Buat akun admin pertama. Ganti `GANTI_PASSWORD_KUAT` dengan password pilihan Anda:

```powershell
$hash = C:\xampp\php\php.exe -r "echo password_hash('GANTI_PASSWORD_KUAT', PASSWORD_DEFAULT);"
C:\xampp\mysql\bin\mysql.exe -u root db_koperasi -e "INSERT INTO users (nama, username, password, role) VALUES ('Administrator', 'admin', '$hash', 'admin');"
```

Jangan menyimpan password mentah di dokumentasi, tiket, atau repositori.

## 5. Konfigurasi Aplikasi

Salin contoh konfigurasi:

```powershell
Copy-Item C:\xampp\htdocs\koperasi\config.example.php C:\xampp\htdocs\koperasi\config.local.php
```

Edit `config.local.php`, misalnya untuk database demo:

```php
return [
    'host' => '127.0.0.1',
    'port' => 3306,
    'user' => 'root',
    'pass' => '',
    'name' => 'koperasi_demo',
];
```

`config.local.php` sudah masuk `.gitignore`. Jangan meng-commit file tersebut.

Alternatif: gunakan environment variable `KOPERASI_DB_HOST`, `KOPERASI_DB_PORT`, `KOPERASI_DB_USER`, `KOPERASI_DB_PASS`, dan `KOPERASI_DB_NAME`.

## 6. Membuka Aplikasi

Buka browser:

```
http://localhost/koperasi/
```

Login dengan akun demo (lihat [DEMO.md](DEMO.md)) atau akun admin yang baru dibuat.

## 7. Memeriksa Instalasi

- Halaman login tampil tanpa error.
- Login admin berhasil dan dashboard menampilkan statistik.
- Menu **Data Anggota**, **Input Iuran**, **Riwayat Iuran**, dan **Laporan** dapat dibuka.
- Halaman cetak terbuka di tab baru dan dapat disimpan sebagai PDF.
- Logout mengembalikan ke halaman login.

Jika muncul pesan **Koneksi database gagal**, periksa:

1. MySQL sudah berjalan.
2. Nama database pada `config.local.php` benar.
3. User/password database benar.
4. Database sudah diimpor.

Untuk detail error saat pengembangan, jalankan Apache dengan environment `KOPERASI_DEBUG=1`. Jangan aktifkan mode ini di produksi.

## 8. Migrasi Instalasi Lama

Jika Anda memiliki database lama tanpa kolom `users.anggota_id`, index, atau hash password:

```powershell
C:\xampp\mysql\bin\mysqldump.exe -u root --single-transaction --databases db_koperasi --result-file=C:\backup\db_koperasi.sql
C:\xampp\php\php.exe C:\xampp\htdocs\koperasi\database\migrate.php
```

Migrasi tidak menghapus data. Constraint yang tidak memungkinkan karena data lama (misalnya kode anggota duplikat) akan dilewati dengan peringatan.

## 9. Hardening Produksi

- Ganti seluruh akun dan password demo.
- Gunakan user MySQL khusus aplikasi, bukan `root`.
- Aktifkan HTTPS dan pastikan cookie session memakai flag `Secure` (otomatis bila HTTPS terdeteksi).
- Nonaktifkan `KOPERASI_DEBUG`.
- Batasi akses folder `backups/` dan file SQL (sudah diblokir `.htaccess`).
- Buat backup terjadwal dan uji proses restore.
- Jangan menaruh salinan database asli di folder web publik.
