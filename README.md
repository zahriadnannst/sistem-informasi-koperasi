# Sistem Informasi Koperasi

Aplikasi berbasis web untuk membantu pengelolaan administrasi dan keuangan koperasi secara digital. Sistem ini menyediakan fitur pengelolaan anggota, transaksi kas, pembayaran iuran, verifikasi pembayaran, serta laporan koperasi.

Dibangun menggunakan PHP Native dan MySQL serta dijalankan menggunakan XAMPP sebagai server lokal.

## ✨ Fitur Aplikasi

### 1. Admin
- Dashboard ringkasan informasi koperasi.
- Mengelola data anggota.
- Mengelola akun pengguna.
- Mengelola transaksi kas.
- Melihat dan mengelola pembayaran iuran.
- Memverifikasi pengajuan pembayaran anggota.
- Mengatur informasi rekening bank dan QRIS.
- Melihat riwayat pembayaran.
- Melihat dan mencetak laporan koperasi.
- Mengelola data sesuai hak akses admin.

### 2. Anggota
- Login ke halaman anggota.
- Melihat informasi profil anggota.
- Melihat tagihan dan status pembayaran iuran.
- Melihat total pembayaran dan sisa kewajiban.
- Mengajukan pembayaran melalui transfer bank atau QRIS.
- Mengunggah bukti pembayaran.
- Melihat riwayat pengajuan pembayaran.
- Melihat status pembayaran yang telah diverifikasi admin.

### 3. Keamanan
- Autentikasi pengguna.
- Password hashing.
- Pembatasan akses berdasarkan role.
- Prepared statements untuk query database.
- Validasi input.
- Perlindungan CSRF.
- Validasi file bukti pembayaran.
- Perlindungan file dan direktori penting.

## 🛠️ Teknologi

- PHP Native
- MySQL / MariaDB
- HTML5
- CSS3
- JavaScript
- XAMPP
- Git dan GitHub

## 📋 Persyaratan Instalasi

Sebelum menjalankan aplikasi, siapkan:

1. Windows 10/11.
2. XAMPP dengan Apache dan MySQL.
3. PHP yang kompatibel dengan aplikasi.
4. Browser seperti Google Chrome.
5. Visual Studio Code (opsional).

## 🚀 Cara Instalasi di Localhost

Ikuti langkah berikut untuk menjalankan aplikasi di komputer sendiri.

### Langkah 1. Download atau Clone Repository

Buka terminal atau CMD, kemudian jalankan:

```bash
git clone https://github.com/USERNAME/REPOSITORY.git
```

Ganti `USERNAME` dan `REPOSITORY` sesuai repository GitHub.

Atau download repository melalui tombol **Code → Download ZIP**, lalu ekstrak folder proyek.

### Langkah 2. Pindahkan Folder ke htdocs

Pindahkan folder proyek ke:

```text
C:\xampp\htdocs\koperasi
```

Pastikan file utama aplikasi berada di dalam folder tersebut, bukan di dalam folder bertingkat yang tidak diperlukan.

### Langkah 3. Jalankan XAMPP

1. Buka XAMPP Control Panel.
2. Klik Start pada Apache.
3. Klik Start pada MySQL.
4. Pastikan keduanya berjalan dengan status aktif.

### Langkah 4. Buat Database

1. Buka browser.
2. Akses phpMyAdmin:

```text
http://localhost/phpmyadmin
```

3. Klik menu **New**.
4. Buat database dengan nama:

```text
db_koperasi
```

5. Klik Create.

### Langkah 5. Import Database

Jika repository menyediakan file database:

1. Klik database `db_koperasi`.
2. Pilih tab **Import**.
3. Klik Choose File.
4. Pilih file SQL yang disediakan, misalnya `schema.sql`.
5. Klik Import atau Go.
6. Tunggu sampai proses selesai.

Jika tersedia file database demo, gunakan database demo sesuai petunjuk yang disediakan.

**Penting:** Jangan menggunakan atau mengunggah dump database asli yang berisi data pribadi anggota dan transaksi nyata. Gunakan struktur database dan data demo yang sudah disiapkan untuk publik.

### Langkah 6. Konfigurasi Koneksi Database

Buka file konfigurasi database di folder `config`.

Jika proyek menyediakan file contoh konfigurasi, salin file tersebut menjadi file konfigurasi lokal sesuai petunjuk proyek.

Sesuaikan koneksi database dengan konfigurasi XAMPP:

```php
$host = 'localhost';
$dbname = 'db_koperasi';
$username = 'root';
$password = '';
```

Pengaturan tersebut merupakan contoh konfigurasi standar XAMPP. Jika MySQL milikmu menggunakan password, sesuaikan nilainya.

Jangan mengunggah file konfigurasi lokal yang berisi password atau informasi rahasia ke GitHub.

### Langkah 7. Jalankan Aplikasi

Setelah Apache dan MySQL aktif serta database berhasil diimpor, buka browser dan akses:

**Halaman utama:**

```text
http://localhost/koperasi/
```

Jika halaman utama tidak langsung menampilkan login, buka halaman login sesuai nama file yang tersedia di proyek, misalnya:

```text
http://localhost/koperasi/login.php
```

**phpMyAdmin:**

```text
http://localhost/phpmyadmin
```

**Direktori proyek:**

```text
C:\xampp\htdocs\koperasi
```

### Langkah 8. Login Aplikasi

Gunakan akun demo yang telah disediakan oleh pengelola proyek.

Akun admin dan anggota harus dibuat atau disiapkan melalui proses seeding atau konfigurasi database yang tersedia.

Jangan menggunakan akun administrator pribadi untuk demonstrasi publik.

## 🗂️ Struktur Direktori

Gambaran struktur utama proyek:

```text
koperasi/
│
├── admin/
├── anggota/
├── assets/
│   ├── css/
│   ├── js/
│   └── img/
│
├── config/
├── database/
├── docs/
├── includes/
├── backups/
│
├── cetak.php
├── index.php
├── login.php
├── logout.php
├── schema.sql
│
├── README.md
├── LICENSE
├── CONTRIBUTING.md
├── SECURITY.md
└── .gitignore
```

## 🧪 Pengujian Aplikasi

Pengujian lokal telah mencakup:

- Login admin dan anggota.
- Hak akses berdasarkan role.
- Pengelolaan data anggota.
- Pengelolaan transaksi kas.
- Pengajuan dan verifikasi pembayaran.
- Validasi bukti pembayaran.
- Riwayat transaksi.
- Laporan dan cetak PDF.
- Validasi input dan keamanan akses.
- Pengujian tampilan responsif.

Fitur pembayaran telah diuji menggunakan database pengujian. Untuk menggunakan database utama, ikuti prosedur migrasi dan backup yang disediakan.

## 🔒 Keamanan dan Privasi

Sebelum menjalankan atau mengembangkan aplikasi:

- Jangan membagikan password database.
- Jangan mengunggah file konfigurasi lokal.
- Jangan mengunggah database asli yang berisi data anggota.
- Jangan mengunggah folder backup.
- Gunakan data demo untuk pengujian publik.
- Lakukan backup database sebelum menjalankan perubahan struktur database.

## 📄 Lisensi

Proyek ini menggunakan **MIT License**. Lihat file [LICENSE](LICENSE) untuk informasi lengkap mengenai ketentuan penggunaan.

## 👨‍💻 Pengembang

Proyek Sistem Informasi Koperasi dikembangkan sebagai bagian dari pembelajaran dan pengembangan aplikasi web untuk membantu digitalisasi pengelolaan koperasi.

---

**Sistem Informasi Koperasi**  
*Mendukung pengelolaan koperasi yang lebih mudah, terstruktur, dan digital.*