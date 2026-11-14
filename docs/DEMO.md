# Akun dan Data Demo

File `database/demo_koperasi.sql` berisi database contoh untuk evaluasi. Seluruh data di dalamnya **fiktif** dan tidak berasal dari database produksi.

## Impor

```powershell
C:\xampp\mysql\bin\mysql.exe -u root < C:\xampp\htdocs\koperasi\database\demo_koperasi.sql
```

Perintah tersebut membuat database `koperasi_demo`.

Arahkan aplikasi ke database demo melalui `config.local.php`:

```php
return [
    'host' => '127.0.0.1',
    'port' => 3306,
    'user' => 'root',
    'pass' => '',
    'name' => 'koperasi_demo',
];
```

## Akun

| Peran | Username | Password | Data anggota |
|---|---|---|---|
| Admin | `admin` | `Admin#Koperasi2026` | — |
| Anggota | `nadia` | `Nadia#Koperasi2026` | Nadia Putri (AG001) |
| Anggota | `rizky` | `Rizky#Koperasi2026` | Rizky Pratama (AG002) |

Password disimpan sebagai hash bcrypt di database. Password di atas hanya untuk demo.

## Isi Data

- 10 anggota fiktif: `AG001`–`AG010`.
- 73 transaksi iuran dari Desember 2025 sampai Oktober 2026.
- Total kas demo: **Rp 3.500.000**.
- Contoh status:
  - **Lunas:** sebagian besar anggota pada Januari 2026.
  - **Sebagian:** Rizky Pratama (Februari), Bagas Setiawan (Maret dan Mei), Fajar Nugroho (Oktober).
  - **Dua transaksi dalam satu periode:** Intan Maharani pada Maret 2026 (`Rp 20.000` + `Rp 30.000`) untuk menguji status agregat.
  - **Belum bayar:** beberapa anggota pada bulan tertentu.
- Dua akun anggota terhubung ke data anggota untuk menguji isolasi data.

Data ini dirancang agar filter, laporan, paginasi, dan status periode dapat diuji tanpa menyentuh data nyata.

## Mengganti Akun Demo

Sebelum memakai aplikasi untuk data nyata:

1. Jangan gunakan database `koperasi_demo`.
2. Buat database produksi dari `database/schema.sql`.
3. Buat akun admin dengan password kuat dan hash bcrypt.
4. Hapus atau jangan impor akun demo.
5. Ganti seluruh kredensial yang pernah dibagikan.

## Reset Database Demo

Untuk mengembalikan data demo ke kondisi awal, impor ulang file demo. File tersebut menghapus dan membuat ulang tabel **di database `koperasi_demo` saja**:

```powershell
C:\xampp\mysql\bin\mysql.exe -u root < C:\xampp\htdocs\koperasi\database\demo_koperasi.sql
```

> Jangan menjalankan file demo pada database produksi. Periksa nama database pada file sebelum impor.
