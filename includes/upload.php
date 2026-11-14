<?php

declare(strict_types=1);

/**
 * Penanganan unggahan berkas gambar (bukti transfer dan QRIS) dengan aman.
 *
 * Aturan:
 * - Hanya JPG/JPEG/PNG/WebP.
 * - Tipe MIME diperiksa dari isi berkas (finfo) dan dicocokkan dengan
 *   getimagesize(), bukan hanya dari ekstensi.
 * - Ukuran maksimal 2 MB.
 * - Nama berkas acak; ekstensi ditentukan dari tipe yang terdeteksi sehingga
 *   berkas PHP/skrip tidak pernah tersimpan sebagai berkas yang dapat dieksekusi.
 * - Berkas disimpan di direktori storage/ yang diblokir dari akses langsung.
 */

const UPLOAD_MAKS_BYTE = 2097152; // 2 MB

/** Tipe MIME gambar yang diizinkan beserta ekstensi aman. */
function upload_mime_diizinkan(): array
{
    return [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
}

/**
 * Direktori penyimpanan (di luar akses langsung browser). Dibuat bila belum ada.
 */
function storage_dir(string $subdir = ''): string
{
    $base = dirname(__DIR__) . '/storage';
    $subdir = trim(str_replace('\\', '/', $subdir), '/');
    $dir = $subdir === '' ? $base : $base . '/' . $subdir;

    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    return $dir;
}

/**
 * Pastikan direktori penyimpanan dan proteksi .htaccess tersedia.
 * Aman dipanggil berkali-kali (idempoten).
 */
function pastikan_storage(): void
{
    storage_dir();
    storage_dir('bukti');
    storage_dir('qris');

    $htaccess = storage_dir() . '/.htaccess';
    if (!is_file($htaccess)) {
        @file_put_contents($htaccess, storage_htaccess_isi());
    }
}

function storage_htaccess_isi(): string
{
    return <<<'HTACCESS'
# Direktori ini menyimpan berkas bukti pembayaran.
# Tidak boleh diakses langsung maupun dieksekusi. Gunakan handler PHP (bukti.php / qris.php).

Options -Indexes -ExecCGI

<IfModule mod_authz_core.c>
    Require all denied
</IfModule>
<IfModule !mod_authz_core.c>
    Order deny,allow
    Deny from all
</IfModule>

RemoveHandler .php .phtml .phar .cgi .pl .py .asp .aspx .sh .exe .html .htm .js
RemoveType .php .phtml .phar

<FilesMatch "\.(php|phtml|phar|cgi|pl|py|asp|aspx|sh|exe|html?|js)$">
    <IfModule mod_authz_core.c>
        Require all denied
    </IfModule>
    <IfModule !mod_authz_core.c>
        Order deny,allow
        Deny from all
    </IfModule>
</FilesMatch>
HTACCESS;
}

/**
 * Bersihkan nama berkas asli untuk ditampilkan (bukan untuk disimpan di disk).
 */
function nama_asli_aman(string $nama): string
{
    $nama = str_replace('\\', '/', $nama);
    $nama = basename($nama);
    $nama = preg_replace('/[^\p{L}\p{N} ._()\-]+/u', '_', $nama) ?? '';
    $nama = trim($nama);

    if ($nama === '') {
        return 'bukti';
    }

    return mb_substr($nama, 0, 120);
}

/**
 * Validasi & simpan berkas unggahan gambar.
 *
 * @param array<string,mixed> $file   Elemen dari $_FILES.
 * @param string              $subdir Subdirektori di dalam storage/.
 * @return array{ok:bool,nama:?string,asli:?string,mime:?string,pesan:?string}
 */
function simpan_upload_gambar(array $file, string $subdir = 'bukti', int $maxBytes = UPLOAD_MAKS_BYTE): array
{
    $gagal = static function (string $pesan): array {
        return ['ok' => false, 'nama' => null, 'asli' => null, 'mime' => null, 'pesan' => $pesan];
    };

    if (!isset($file['error']) || is_array($file['error'])) {
        return $gagal('Berkas bukti tidak valid.');
    }

    $error = (int) $file['error'];

    if ($error === UPLOAD_ERR_NO_FILE) {
        return $gagal('Bukti transfer wajib diunggah.');
    }
    if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
        return $gagal('Ukuran berkas melebihi batas maksimal 2 MB.');
    }
    if ($error !== UPLOAD_ERR_OK) {
        return $gagal('Gagal mengunggah berkas. Silakan coba lagi.');
    }

    $tmp = is_string($file['tmp_name'] ?? null) ? $file['tmp_name'] : '';
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        return $gagal('Berkas bukti tidak valid.');
    }

    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0) {
        return $gagal('Berkas bukti kosong atau tidak valid.');
    }
    if ($size > $maxBytes) {
        return $gagal('Ukuran berkas melebihi batas maksimal 2 MB.');
    }

    $mime = '';
    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($tmp);
    }

    $diizinkan = upload_mime_diizinkan();
    if (!isset($diizinkan[$mime])) {
        return $gagal('Format berkas harus JPG, JPEG, PNG, atau WebP.');
    }

    // Pastikan isi berkas benar-benar gambar dan cocok dengan MIME terdeteksi.
    $info = @getimagesize($tmp);
    if ($info === false) {
        return $gagal('Berkas yang diunggah bukan gambar yang valid.');
    }

    $petaTipe = [
        'image/jpeg' => IMAGETYPE_JPEG,
        'image/png' => IMAGETYPE_PNG,
        'image/webp' => IMAGETYPE_WEBP,
    ];

    if (($petaTipe[$mime] ?? 0) !== (int) ($info[2] ?? 0)) {
        return $gagal('Isi berkas tidak cocok dengan format gambarnya.');
    }

    $ext = $diizinkan[$mime];
    $nama = bin2hex(random_bytes(16)) . '.' . $ext;
    $tujuan = storage_dir($subdir) . '/' . $nama;

    if (!move_uploaded_file($tmp, $tujuan)) {
        return $gagal('Gagal menyimpan berkas bukti.');
    }

    @chmod($tujuan, 0644);

    return [
        'ok' => true,
        'nama' => $nama,
        'asli' => nama_asli_aman((string) ($file['name'] ?? 'bukti')),
        'mime' => $mime,
        'pesan' => null,
    ];
}

/**
 * Hapus berkas bukti/qris dari storage dengan nama yang divalidasi.
 */
function hapus_berkas_storage(?string $nama, string $subdir = 'bukti'): void
{
    if ($nama === null || $nama === '') {
        return;
    }

    if (!preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/', $nama)) {
        return;
    }

    $path = storage_dir($subdir) . '/' . $nama;
    if (is_file($path)) {
        @unlink($path);
    }
}

/**
 * Path absolut berkas bukti/qris bila valid dan ada.
 */
function path_berkas_storage(string $nama, string $subdir = 'bukti'): ?string
{
    if (!preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/', $nama)) {
        return null;
    }

    $path = storage_dir($subdir) . '/' . $nama;

    return is_file($path) ? $path : null;
}
