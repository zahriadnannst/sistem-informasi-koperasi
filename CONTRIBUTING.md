# Panduan Kontribusi

Terima kasih telah tertarik berkontribusi pada proyek Koperasi. Kontribusi dapat berupa perbaikan bug, peningkatan dokumentasi, pengujian, atau fitur baru yang relevan.

## Sebelum Memulai

- Baca `README.md`, `docs/STRUKTUR.md`, dan `SECURITY.md`.
- Untuk perubahan besar, buka issue terlebih dahulu agar arah implementasi disepakati.
- Jangan menyertakan data pribadi, password, hash password asli, dump database produksi, atau kredensial pada issue, commit, maupun pull request.

## Alur Kerja

1. Fork repositori dan buat branch baru:

   ```bash
   git checkout -b fix/nama-perbaikan
   ```

2. Lakukan perubahan secara terfokus. Hindari refactor besar yang tidak terkait.
3. Jalankan lint dan pengujian.
4. Commit dengan pesan yang jelas, misalnya:

   ```
   fix: validasi total periode saat edit iuran
   ```

5. Buka pull request dan jelaskan:
   - masalah yang diperbaiki;
   - pendekatan yang dipakai;
   - langkah pengujian;
   - dampak terhadap database atau kompatibilitas.

## Standar Kode

- Gunakan PHP 8.2+ dan `declare(strict_types=1);`.
- Pertahankan gaya penulisan dan penamaan yang sudah ada.
- Semua query database wajib memakai prepared statement.
- Semua output dinamis wajib di-escape dengan `e()` atau helper terkait.
- Semua operasi POST wajib memakai token CSRF.
- Jangan menambah framework, Composer, atau dependensi JavaScript eksternal tanpa diskusi.
- Pertahankan desain dan struktur navigasi yang sudah ada.

## Pengujian Wajib

Sebelum mengirim pull request, minimal lakukan:

```powershell
Get-ChildItem -Recurse -Filter *.php | ForEach-Object { C:\xampp\php\php.exe -l $_.FullName }
```

Lalu uji manual pada **database salinan**:

- login admin dan anggota;
- akses halaman yang diubah sebagai anonim, admin, dan anggota;
- operasi POST dengan token CSRF valid dan tidak valid;
- input tidak valid dan percobaan injection/XSS;
- perhitungan total iuran dan laporan setelah perubahan.

Jangan menguji operasi yang mengubah data pada database produksi.

## Database

- Perubahan skema harus disertai alasan dan dampak kompatibilitas.
- Jangan menghapus atau mengganti nama kolom tanpa alasan kuat.
- Sertakan migrasi yang idempoten di `database/migrate.php` bila relevan.
- Jangan mengubah `database/schema.sql` dan `database/demo_koperasi.sql` secara bertentangan.
- Data demo harus tetap fiktif dan bebas kredensial asli.

## Dokumentasi

Perbarui dokumentasi terkait bila perubahan memengaruhi:

- instalasi atau konfigurasi;
- skema database;
- fitur atau hak akses;
- langkah pengujian;
- keamanan.

## Keamanan

Jangan melaporkan kerentanan melalui issue publik. Ikuti prosedur di `SECURITY.md`.

## Lisensi

Dengan mengirim kontribusi, Anda menyatakan bahwa kontribusi tersebut boleh dirilis di bawah lisensi MIT proyek ini.
