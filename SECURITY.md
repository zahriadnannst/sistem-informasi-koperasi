# Kebijakan Keamanan

## Versi yang Didukung

Perbaikan keamanan difokuskan pada versi terbaru di branch utama. Versi lama tidak dijamin menerima patch.

## Melaporkan Kerentanan

**Jangan membuka issue publik untuk kerentanan keamanan.**

Setelah repositori dipublikasikan di GitHub, gunakan fitur **Security Advisories** pada tab *Security* repositori untuk melaporkan secara privat. Sertakan:

- deskripsi kerentanan dan dampaknya;
- langkah reproduksi;
- versi PHP/MySQL dan browser yang digunakan;
- bukti konsep secukupnya, tanpa data pribadi atau kredensial asli.

Anda akan menerima konfirmasi penerimaan laporan. Mohon beri waktu yang wajar sebelum melakukan pengungkapan publik.

## Lingkup

Contoh area yang relevan:

- autentikasi dan manajemen session;
- otorisasi admin/anggota dan isolasi data;
- CSRF, XSS, dan SQL Injection;
- validasi input serta batas pembayaran;
- kebocoran file konfigurasi, dump database, atau informasi sensitif;
- kesalahan perhitungan yang dapat merugikan anggota.

## Kontrol Keamanan yang Diterapkan

| Area | Kontrol |
|---|---|
| Password | `password_hash()` / `password_verify()` dengan bcrypt, rehash otomatis |
| Database | Prepared statement untuk seluruh query |
| Output | Escaping `htmlspecialchars()` melalui `e()` |
| CSRF | Token per session, rotasi setelah login, HTTP 403 bila tidak valid |
| Session | Regenerasi setelah login, `HttpOnly`, `SameSite=Lax`, `Secure` saat HTTPS, strict mode |
| Otorisasi | `require_admin()` dan `require_member()` |
| Isolasi data | Data anggota difilter berdasarkan `users.anggota_id` milik session |
| Logout | POST + CSRF, session dan cookie dihapus |
| File sensitif | `.sql`, `.md`, log, backup, config, dan `includes/` diblokir `.htaccess` |
| Header | CSP, `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy` |
| Error | Detail hanya ditampilkan saat `KOPERASI_DEBUG=1` |
| Integritas iuran | Validasi total periode + transaksi database + row lock |

## Checklist Deployment

- [ ] Ganti semua akun dan password demo.
- [ ] Gunakan user database khusus dengan hak minimum, bukan `root`.
- [ ] Aktifkan HTTPS.
- [ ] Pastikan `KOPERASI_DEBUG` tidak aktif.
- [ ] Hapus atau lindungi folder `backups/`.
- [ ] Jangan menaruh dump database asli di folder web.
- [ ] Batasi akses panel database (phpMyAdmin) dari publik.
- [ ] Buat backup terjadwal dan uji restore.
- [ ] Perbarui PHP, Apache, dan MariaDB/MySQL.
- [ ] Tinjau log error secara berkala.

## Yang Bukan Kerentanan

- Kredensial demo yang memang dipublikasikan untuk database contoh, selama tidak dipakai di produksi.
- Ketiadaan HTTPS pada lingkungan pengembangan lokal.
- Konfigurasi default XAMPP (`root` tanpa password) yang tidak dipakai di produksi.
- Laporan tentang data contoh fiktif.
