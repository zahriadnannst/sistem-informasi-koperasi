<?php

declare(strict_types=1);

/* -------------------------------------------------------------------------
 * Autentikasi & otorisasi
 * ---------------------------------------------------------------------- */

function current_user(): ?array
{
    if (empty($_SESSION['user_id']) || empty($_SESSION['role'])) {
        return null;
    }

    $role = (string) $_SESSION['role'];
    if (!in_array($role, ['admin', 'anggota'], true)) {
        return null;
    }

    return [
        'id' => (int) $_SESSION['user_id'],
        'nama' => (string) ($_SESSION['nama'] ?? ''),
        'username' => (string) ($_SESSION['username'] ?? ''),
        'role' => $role,
        'anggota_id' => isset($_SESSION['anggota_id']) && $_SESSION['anggota_id'] !== null
            ? (int) $_SESSION['anggota_id']
            : null,
    ];
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function is_admin(): bool
{
    return (current_user()['role'] ?? '') === 'admin';
}

function login_user(array $user): void
{
    session_regenerate_id(true);

    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['nama'] = (string) ($user['nama'] ?? '');
    $_SESSION['username'] = (string) ($user['username'] ?? '');
    $_SESSION['role'] = (string) ($user['role'] ?? 'anggota');
    $_SESSION['anggota_id'] = isset($user['anggota_id']) && $user['anggota_id'] !== null
        ? (int) $user['anggota_id']
        : null;
    $_SESSION['login_at'] = time();

    // Rotasi token CSRF setelah login berhasil.
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    unset($_SESSION['login_attempts'], $_SESSION['login_locked_until']);
}

function logout_user(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            [
                'expires' => time() - 42000,
                'path' => $params['path'],
                'domain' => $params['domain'],
                'secure' => (bool) $params['secure'],
                'httponly' => (bool) $params['httponly'],
                'samesite' => $params['samesite'] ?? 'Lax',
            ]
        );
    }

    session_destroy();
}

function dashboard_url(): string
{
    return is_admin() ? 'dashboard_admin.php' : 'dashboard_anggota.php';
}

function require_login(): void
{
    if (!is_logged_in()) {
        flash('warning', 'Silakan login terlebih dahulu untuk mengakses halaman tersebut.');
        redirect('index.php');
    }
}

function require_admin(): void
{
    require_login();

    if (!is_admin()) {
        flash('danger', 'Halaman tersebut hanya dapat diakses oleh admin.');
        redirect('dashboard_anggota.php');
    }
}

/**
 * Halaman khusus anggota. Admin yang membuka akan diarahkan ke dashboard admin.
 */
function require_member(): void
{
    require_login();

    if (is_admin()) {
        flash('info', 'Halaman tersebut khusus untuk anggota.');
        redirect('dashboard_admin.php');
    }
}
