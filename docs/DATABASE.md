# Database

Aplikasi menggunakan MySQL/MariaDB dengan tiga tabel utama: `anggota`, `kas`, dan `users`.

## Skema

### Tabel `anggota`

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | INT, PK, AUTO_INCREMENT | Identitas anggota |
| `kode_anggota` | VARCHAR(20) | Kode unik anggota, mis. `AG001` |
| `nama` | VARCHAR(100) | Nama lengkap |
| `alamat` | TEXT | Alamat |
| `telepon` | VARCHAR(20) | Nomor telepon |

### Tabel `kas`

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | INT, PK, AUTO_INCREMENT | Identitas transaksi |
| `anggota_id` | INT | Relasi ke `anggota.id` |
| `bulan` | VARCHAR(20) | Nama bulan dalam bahasa Indonesia |
| `tahun` | INT | Tahun periode |
| `jumlah` | DECIMAL(12,2) | Nominal pembayaran |
| `tanggal` | TIMESTAMP | Waktu pencatatan, default `CURRENT_TIMESTAMP` |

Satu periode (`anggota_id`, `bulan`, `tahun`) **boleh memiliki beberapa baris** karena aplikasi mendukung pembayaran sebagian. Karena itu, kombinasi ketiganya tidak dapat dibuat `UNIQUE`. Batas total `Rp 50.000` ditegakkan di lapisan aplikasi dengan transaksi dan penguncian baris.

### Tabel `users`

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | INT, PK, AUTO_INCREMENT | Identitas akun |
| `nama` | VARCHAR(100) | Nama tampilan |
| `username` | VARCHAR(50) | Username unik |
| `password` | VARCHAR(255) | Hash bcrypt dari `password_hash()` |
| `role` | ENUM/VARCHAR | `admin` atau `anggota` |
| `anggota_id` | INT, NULL | Relasi ke `anggota.id` untuk role anggota |

Akun admin tidak terhubung ke anggota (`anggota_id = NULL`). Akun anggota sebaiknya selalu terhubung; bila tidak, dashboard menampilkan peringatan agar admin menghubungkannya.

## Relasi

```
anggota (1) ──── (N) kas
anggota (1) ──── (0..N) users
```

- `kas.anggota_id` → `anggota.id`, `ON DELETE RESTRICT`.
- `users.anggota_id` → `anggota.id`, `ON DELETE SET NULL`.

Aplikasi juga mencegah penghapusan anggota yang masih memiliki transaksi iuran. Dengan demikian, data keuangan tidak kehilangan hubungan dengan pemiliknya.

## Index

| Index | Tabel | Kolom | Tujuan |
|---|---|---|---|
| `PRIMARY` | semua tabel | `id` | Lookup utama |
| `uq_users_username` | `users` | `username` | Mencegah username ganda |
| `uq_anggota_kode` | `anggota` | `kode_anggota` | Mencegah kode ganda (skema baru) |
| `idx_users_anggota` | `users` | `anggota_id` | Relasi akun–anggota |
| `idx_kas_anggota` | `kas` | `anggota_id` | Filter riwayat per anggota |
| `idx_kas_periode` | `kas` | `tahun`, `bulan` | Filter laporan per periode |
| `idx_kas_anggota_periode` | `kas` | `anggota_id`, `tahun`, `bulan` | Total iuran per anggota + periode |

## Evaluasi Constraint

| Kandidat | Keputusan | Alasan |
|---|---|---|
| `UNIQUE(users.username)` | Dipakai | Username harus unik untuk login. |
| `UNIQUE(anggota.kode_anggota)` | Dipakai pada instalasi baru; dilewati bila ada data lama duplikat | Data legacy dapat memiliki kode ganda. Aplikasi tetap memvalidasi kode unik saat tambah/edit. |
| `UNIQUE(kas.anggota_id, bulan, tahun)` | **Tidak dipakai** | Pembayaran sebagian menghasilkan beberapa transaksi per periode. |
| `CHECK(kas.jumlah > 0)` | Dipakai pada skema baru | Mencegah nominal nol/negatif di lapisan database. |
| `CHECK(kas.tahun BETWEEN 2000 AND 2100)` | Dipakai pada skema baru | Membatasi tahun yang tidak wajar. |
| `FOREIGN KEY(kas.anggota_id)` | Dipakai pada skema baru; migrasi memeriksa orphan | Menjaga integritas relasi. |
| `FOREIGN KEY(users.anggota_id)` | Dipakai pada skema baru; migrasi memeriksa orphan | Menjaga integritas akun anggota. |

> Untuk database live yang sudah berisi data lama, constraint di atas hanya boleh diterapkan setelah backup dan persetujuan pemilik data. Jalankan `database/migrate.php` pada salinan terlebih dahulu.

## Skema Baru vs Migrasi

- **Instalasi baru:** gunakan `database/schema.sql` (kosong) atau `database/demo_koperasi.sql` (data fiktif).
- **Instalasi lama:** gunakan `database/migrate.php`. Skrip ini:
  - menambahkan kolom dan index yang belum ada;
  - menambahkan unique index bila tidak ada duplikat;
  - memeriksa data yatim sebelum menambahkan foreign key;
  - menghubungkan akun anggota berdasarkan nama;
  - mengubah password plaintext lama menjadi hash bcrypt.

## Backup dan Restore

Backup sebelum perubahan risiko tinggi:

```powershell
New-Item -ItemType Directory -Force C:\backup | Out-Null
C:\xampp\mysql\bin\mysqldump.exe -u root --single-transaction --routines --databases db_koperasi --result-file=C:\backup\db_koperasi.sql
```

Restore ke database tertentu (hati-hati: tindakan ini menimpa data):

```powershell
C:\xampp\mysql\bin\mysql.exe -u root -e "DROP DATABASE IF EXISTS db_koperasi_restore; CREATE DATABASE db_koperasi_restore CHARACTER SET utf8mb4;"
C:\xampp\mysql\bin\mysql.exe -u root db_koperasi_restore < C:\backup\db_koperasi.sql
```

Jangan menyimpan file backup di folder web publik. Folder `backups/` sudah diblokir `.htaccess` dan diabaikan `.gitignore`.

## Catatan Data Legacy

Beberapa instalasi lama dapat memiliki:

- kode anggota duplikat;
- lebih dari satu transaksi pada periode yang sama melebihi batas;
- akun anggota yang belum terhubung ke data anggota;
- password plaintext.

Aplikasi tidak menghapus data lama secara otomatis. Data tersebut dapat dibersihkan secara manual oleh admin melalui menu Riwayat Iuran, atau dibiarkan sebagai arsip. Pencegahan duplikat dan overpayment berlaku untuk transaksi baru dan hasil edit.
