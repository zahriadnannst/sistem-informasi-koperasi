<?php

declare(strict_types=1);

/* -------------------------------------------------------------------------
 * Konstanta aplikasi
 * ---------------------------------------------------------------------- */

const APP_NAME = 'Koperasi';
const APP_TAGLINE = 'Sistem Informasi Koperasi';
const IURAN_WAJIB = 50000.0;

/* -------------------------------------------------------------------------
 * Utilitas umum
 * ---------------------------------------------------------------------- */

function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Direktori dasar aplikasi relatif terhadap document root, mis. "/koperasi/".
 * Dipakai agar URL aset tetap benar walau halaman dibuka dari URL berbeda.
 */
function base_url(): string
{
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $dir = rtrim(dirname($script), '/');

    return $dir === '' ? '/' : $dir . '/';
}

/**
 * URL aset (absolut terhadap document root) + penanda versi cache-busting
 * berbasis waktu ubah file, sehingga CSS/JS selalu ditemukan dan selalu
 * dimuat ulang browser setelah file diubah.
 */
function asset(string $path): string
{
    $relative = 'assets/' . ltrim($path, '/');
    $full = dirname(__DIR__) . '/' . $relative;
    $version = is_file($full) ? (string) filemtime($full) : '0';

    return base_url() . $relative . '?v=' . $version;
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function post(string $key, string $default = ''): string
{
    $value = $_POST[$key] ?? $default;

    return is_string($value) ? trim($value) : $default;
}

function get(string $key, string $default = ''): string
{
    $value = $_GET[$key] ?? $default;

    return is_string($value) ? trim($value) : $default;
}

function post_int(string $key): int
{
    return (int) post($key);
}

function get_int(string $key): int
{
    return (int) get($key);
}

/**
 * Halaman error minimal (dipakai untuk CSRF kedaluwarsa / 404 sederhana).
 */
function abort_request(int $status, string $message): never
{
    http_response_code($status);
    $safeMessage = e($message);

    exit(
        '<!doctype html><html lang="id"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>Permintaan Ditolak</title></head>'
        . '<body style="font-family:Arial,sans-serif;background:#f1f5f9;margin:0;padding:40px">'
        . '<div style="max-width:520px;margin:auto;background:#fff;border:1px solid #e2e8f0;'
        . 'border-radius:14px;padding:28px;box-shadow:0 10px 30px rgba(15,23,42,.08)">'
        . '<h1 style="margin:0 0 12px;font-size:22px;color:#0f172a">Permintaan ditolak</h1>'
        . '<p style="color:#475569;line-height:1.6">' . $safeMessage . '</p>'
        . '<p><a href="index.php" style="color:#0f766e;font-weight:600">Kembali ke halaman utama</a></p>'
        . '</div></body></html>'
    );
}

/* -------------------------------------------------------------------------
 * Format tampilan
 * ---------------------------------------------------------------------- */

function rupiah(mixed $number): string
{
    return 'Rp ' . number_format((float) $number, 0, ',', '.');
}

function tanggal_indo(?string $datetime, bool $denganWaktu = false): string
{
    if ($datetime === null || $datetime === '') {
        return '-';
    }

    $timestamp = strtotime($datetime);
    if ($timestamp === false) {
        return '-';
    }

    return date($denganWaktu ? 'd/m/Y H:i' : 'd/m/Y', $timestamp);
}

function tanggal_panjang(?string $datetime = null): string
{
    $bulan = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
    ];

    $timestamp = $datetime === null ? time() : (strtotime($datetime) ?: time());

    return date('j', $timestamp) . ' ' . $bulan[(int) date('n', $timestamp)] . ' ' . date('Y', $timestamp);
}

function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $first = mb_substr($parts[0] ?? '', 0, 1);
    $second = isset($parts[1]) ? mb_substr($parts[1], 0, 1) : '';

    return mb_strtoupper($first . $second);
}

function bulan_list(): array
{
    static $bulan = [
        'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
    ];

    return $bulan;
}

function bulan_valid(string $bulan): bool
{
    return in_array($bulan, bulan_list(), true);
}

function bulan_ini(): string
{
    return bulan_list()[(int) date('n') - 1];
}

function bulan_singkat(string $bulan): string
{
    static $map = [
        'Januari' => 'Jan', 'Februari' => 'Feb', 'Maret' => 'Mar',
        'April' => 'Apr', 'Mei' => 'Mei', 'Juni' => 'Jun',
        'Juli' => 'Jul', 'Agustus' => 'Ags', 'September' => 'Sep',
        'Oktober' => 'Okt', 'November' => 'Nov', 'Desember' => 'Des',
    ];

    return $map[$bulan] ?? mb_substr($bulan, 0, 3);
}

/**
 * Badge status berdasarkan total pembayaran satu periode.
 */
function status_badge(float $total): string
{
    if ($total <= 0) {
        return '<span class="badge badge-danger">Belum bayar</span>';
    }

    if ($total < IURAN_WAJIB) {
        return '<span class="badge badge-warning">Sebagian</span>';
    }

    return '<span class="badge badge-success">Lunas</span>';
}

/**
 * Badge status berdasarkan nominal satu transaksi.
 */
function status_transaksi(float $jumlah): string
{
    if ($jumlah >= IURAN_WAJIB) {
        return '<span class="badge badge-success">Lunas</span>';
    }

    if ($jumlah > 0) {
        return '<span class="badge badge-warning">Sebagian</span>';
    }

    return '<span class="badge badge-danger">Belum bayar</span>';
}

/* -------------------------------------------------------------------------
 * Flash message
 * ---------------------------------------------------------------------- */

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function take_flashes(): array
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);

    return is_array($flashes) ? $flashes : [];
}

function render_flashes(): string
{
    $flashes = take_flashes();
    if ($flashes === []) {
        return '';
    }

    $allowed = ['success', 'danger', 'warning', 'info'];
    $icons = [
        'success' => 'check',
        'danger' => 'alert',
        'warning' => 'alert',
        'info' => 'info',
    ];

    $html = '';
    foreach ($flashes as $item) {
        $type = in_array($item['type'] ?? '', $allowed, true) ? $item['type'] : 'info';
        $html .= '<div class="alert alert-' . $type . '" role="alert">'
            . icon($icons[$type])
            . '<span>' . e($item['message'] ?? '') . '</span>'
            . '</div>';
    }

    return $html;
}

function render_errors(array $errors): string
{
    if ($errors === []) {
        return '';
    }

    $html = '<div class="alert alert-danger" role="alert">' . icon('alert') . '<div><ul>';
    foreach ($errors as $error) {
        $html .= '<li>' . e($error) . '</li>';
    }
    $html .= '</ul></div></div>';

    return $html;
}

/* -------------------------------------------------------------------------
 * CSRF
 * ---------------------------------------------------------------------- */

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';

    if (!is_string($token) || $token === '' || empty($_SESSION['csrf_token'])
        || !hash_equals((string) $_SESSION['csrf_token'], $token)) {
        // Gunakan status yang dikenali Apache/XAMPP. Status nonstandar seperti
        // 419 dapat dipetakan Apache menjadi HTTP 500.
        abort_request(403, 'Sesi formulir sudah kedaluwarsa. Silakan muat ulang halaman lalu coba lagi.');
    }
}

/* -------------------------------------------------------------------------
 * Akses database (prepared statement)
 * ---------------------------------------------------------------------- */

function db_stmt(mysqli $conn, string $sql, array $params = []): mysqli_stmt
{
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        throw new RuntimeException('Query gagal disiapkan: ' . $conn->error);
    }

    $stmt->execute($params === [] ? null : $params);

    return $stmt;
}

function db_all(mysqli $conn, string $sql, array $params = []): array
{
    $stmt = db_stmt($conn, $sql, $params);
    $result = $stmt->get_result();
    $rows = $result instanceof mysqli_result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();

    return $rows;
}

function db_one(mysqli $conn, string $sql, array $params = []): ?array
{
    $rows = db_all($conn, $sql, $params);

    return $rows[0] ?? null;
}

function db_value(mysqli $conn, string $sql, array $params = []): mixed
{
    $row = db_one($conn, $sql, $params);
    if ($row === null) {
        return null;
    }

    return reset($row);
}

function db_run(mysqli $conn, string $sql, array $params = []): mysqli_stmt
{
    return db_stmt($conn, $sql, $params);
}

/**
 * Total pembayaran iuran seorang anggota pada satu periode.
 *
 * @param int $excludeId Abaikan transaksi tertentu (dipakai saat mengedit).
 */
function total_iuran_periode(
    mysqli $conn,
    int $anggotaId,
    string $bulan,
    int $tahun,
    int $excludeId = 0
): float {
    $sql = 'SELECT COALESCE(SUM(jumlah), 0)
            FROM kas
            WHERE anggota_id = ? AND bulan = ? AND tahun = ?';
    $params = [$anggotaId, $bulan, $tahun];

    if ($excludeId > 0) {
        $sql .= ' AND id <> ?';
        $params[] = $excludeId;
    }

    return (float) db_value($conn, $sql, $params);
}

/* -------------------------------------------------------------------------
 * Data referensi
 * ---------------------------------------------------------------------- */

function anggota_options(mysqli $conn): array
{
    return db_all($conn, 'SELECT id, kode_anggota, nama FROM anggota ORDER BY nama ASC');
}

function tahun_tersedia(mysqli $conn): array
{
    $rows = db_all($conn, 'SELECT DISTINCT tahun FROM kas WHERE tahun IS NOT NULL ORDER BY tahun DESC');
    $tahun = array_map(static fn (array $row): int => (int) $row['tahun'], $rows);

    $sekarang = (int) date('Y');
    if (!in_array($sekarang, $tahun, true)) {
        $tahun[] = $sekarang;
    }

    rsort($tahun);

    return $tahun;
}

/* -------------------------------------------------------------------------
 * Paginasi
 * ---------------------------------------------------------------------- */

function paginate(int $total, int $perPage, int $page): array
{
    $perPage = max(1, $perPage);
    $pages = max(1, (int) ceil($total / $perPage));
    $page = max(1, min($page, $pages));

    return [
        'total' => $total,
        'per_page' => $perPage,
        'pages' => $pages,
        'page' => $page,
        'offset' => ($page - 1) * $perPage,
    ];
}

function render_pagination(array $pagination, array $params = [], string $pageParam = 'hal'): string
{
    if (($pagination['pages'] ?? 1) <= 1) {
        return '';
    }

    $script = basename((string) ($_SERVER['SCRIPT_NAME'] ?? 'index.php'));
    $buildUrl = static function (int $page) use ($script, $params, $pageParam): string {
        $query = $params;
        $query[$pageParam] = $page;

        return $script . '?' . http_build_query($query);
    };

    $current = (int) $pagination['page'];
    $pages = (int) $pagination['pages'];
    $html = '<nav class="pagination" aria-label="Navigasi halaman">';

    if ($current > 1) {
        $html .= '<a class="page-link" href="' . e($buildUrl($current - 1)) . '" aria-label="Sebelumnya">&laquo;</a>';
    } else {
        $html .= '<span class="page-link disabled">&laquo;</span>';
    }

    $start = max(1, $current - 2);
    $end = min($pages, $current + 2);

    if ($start > 1) {
        $html .= '<a class="page-link" href="' . e($buildUrl(1)) . '">1</a>';
        if ($start > 2) {
            $html .= '<span class="page-dots">…</span>';
        }
    }

    for ($i = $start; $i <= $end; $i++) {
        if ($i === $current) {
            $html .= '<span class="page-link active" aria-current="page">' . $i . '</span>';
        } else {
            $html .= '<a class="page-link" href="' . e($buildUrl($i)) . '">' . $i . '</a>';
        }
    }

    if ($end < $pages) {
        if ($end < $pages - 1) {
            $html .= '<span class="page-dots">…</span>';
        }
        $html .= '<a class="page-link" href="' . e($buildUrl($pages)) . '">' . $pages . '</a>';
    }

    if ($current < $pages) {
        $html .= '<a class="page-link" href="' . e($buildUrl($current + 1)) . '" aria-label="Berikutnya">&raquo;</a>';
    } else {
        $html .= '<span class="page-link disabled">&raquo;</span>';
    }

    return $html . '</nav>';
}

/* -------------------------------------------------------------------------
 * Ikon SVG (inline, tanpa dependensi eksternal)
 * ---------------------------------------------------------------------- */

function icon(string $name, string $class = ''): string
{
    static $icons = [
        'home' => '<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/><path d="M9 21v-6h6v6"/>',
        'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'plus' => '<circle cx="12" cy="12" r="9"/><path d="M12 8v8M8 12h8"/>',
        'history' => '<path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5"/><path d="M12 7v5l3 2"/>',
        'chart' => '<path d="M3 3v18h18"/><path d="M7 14v4M12 9v9M17 5v13"/>',
        'report' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M8 13h8M8 17h8M8 9h2"/>',
        'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/>',
        'menu' => '<path d="M3 6h18M3 12h18M3 18h18"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/>',
        'print' => '<path d="M6 9V2h12v7"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><path d="M6 14h12v8H6z"/>',
        'edit' => '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/>',
        'trash' => '<path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/>',
        'back' => '<path d="M19 12H5"/><path d="m12 19-7-7 7-7"/>',
        'check' => '<path d="m20 6-11 11-5-5"/>',
        'alert' => '<path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/><path d="M12 9v4M12 17h.01"/>',
        'info' => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/>',
        'wallet' => '<path d="M21 12V7H5a2 2 0 0 1 0-4h14v4"/><path d="M3 5v14a2 2 0 0 0 2 2h16v-5"/><path d="M18 12a2 2 0 0 0 0 4h4v-4Z"/>',
        'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
        'calendar' => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
        'bank' => '<path d="M3 10 12 3l9 7"/><path d="M5 10v8M9 10v8M15 10v8M19 10v8"/><path d="M3 21h18"/>',
        'save' => '<path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2Z"/><path d="M17 21v-8H7v8"/><path d="M7 3v5h8"/>',
        'arrow-right' => '<path d="M5 12h14"/><path d="m12 5 7 7-7 7"/>',
        'x' => '<path d="M18 6 6 18M6 6l12 12"/>',
        'upload' => '<path d="M12 16V4"/><path d="m7 9 5-5 5 5"/><path d="M5 20h14"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'shield' => '<path d="M12 3 4 6v6c0 5 3.4 7.7 8 9 4.6-1.3 8-4 8-9V6Z"/><path d="m9 12 2 2 4-4"/>',
        'verify' => '<path d="M12 3 4 6v6c0 5 3.4 7.7 8 9 4.6-1.3 8-4 8-9V6Z"/><path d="m9 12 2 2 4-4"/>',
        'qr' => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><path d="M14 14h3v3"/><path d="M20 14v.01M14 20h.01M17 20h3v-3"/>',
        'image' => '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-4.5-4.5L7 20"/>',
        'download' => '<path d="M12 3v12"/><path d="m7 10 5 5 5-5"/><path d="M5 21h14"/>',
    ];

    $path = $icons[$name] ?? $icons['info'];
    $extra = $class !== '' ? ' ' . e($class) : '';

    return '<svg class="icon' . $extra . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" '
        . 'stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
        . $path . '</svg>';
}
