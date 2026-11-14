<?php

declare(strict_types=1);

/**
 * Layout aplikasi (sidebar + topbar) dan halaman cetak.
 */

function render_head(string $title, string $bodyClass = ''): void
{
    ?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?= e(APP_TAGLINE) ?>">
    <title><?= e($title) ?> &middot; <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= e(asset('style.css')) ?>">
</head>
<body<?= $bodyClass !== '' ? ' class="' . e($bodyClass) . '"' : '' ?>>
    <?php
}

function render_app_start(string $title, string $active, string $heading = '', string $subtitle = ''): void
{
    global $conn;

    $user = current_user();
    if ($user === null) {
        redirect('index.php');
    }

    $isAdmin = $user['role'] === 'admin';
    $heading = $heading !== '' ? $heading : $title;

    $menu = $isAdmin
        ? [
            ['file' => 'dashboard_admin.php', 'label' => 'Dashboard', 'icon' => 'home'],
            ['file' => 'anggota.php', 'label' => 'Data Anggota', 'icon' => 'users'],
            ['file' => 'kas.php', 'label' => 'Input Iuran', 'icon' => 'plus'],
            ['file' => 'riwayat_iuran.php', 'label' => 'Riwayat Iuran', 'icon' => 'history'],
            ['file' => 'iuran_anggota.php', 'label' => 'Rekap Iuran', 'icon' => 'chart'],
            ['file' => 'verifikasi_pembayaran.php', 'label' => 'Verifikasi Pembayaran', 'icon' => 'verify', 'badge' => $conn instanceof mysqli ? jumlah_pending_verifikasi($conn) : 0],
            ['file' => 'pengaturan_pembayaran.php', 'label' => 'Pengaturan Pembayaran', 'icon' => 'bank'],
            ['file' => 'laporan.php', 'label' => 'Laporan', 'icon' => 'report'],
        ]
        : [
            ['file' => 'dashboard_anggota.php', 'label' => 'Dashboard', 'icon' => 'home'],
            ['file' => 'pembayaran_iuran.php', 'label' => 'Pembayaran Iuran', 'icon' => 'wallet'],
            ['file' => 'riwayat_kas.php', 'label' => 'Riwayat Iuran', 'icon' => 'history'],
        ];

    render_head($title);
    ?>
<div class="app">
    <aside class="sidebar" id="sidebar" aria-label="Menu utama">
        <a class="brand" href="<?= e(dashboard_url()) ?>">
            <span class="brand-logo"><?= icon('bank') ?></span>
            <span class="brand-text">
                <strong><?= e(APP_NAME) ?></strong>
                <small><?= e(APP_TAGLINE) ?></small>
            </span>
        </a>

        <nav class="nav">
            <span class="nav-label"><?= $isAdmin ? 'Menu Admin' : 'Menu Anggota' ?></span>
            <?php foreach ($menu as $item): ?>
                <a class="nav-link<?= $active === $item['file'] ? ' active' : '' ?>"
                   href="<?= e($item['file']) ?>">
                    <?= icon($item['icon']) ?>
                    <span><?= e($item['label']) ?></span>
                    <?php if (($item['badge'] ?? 0) > 0): ?>
                        <span class="nav-badge"><?= (int) $item['badge'] ?></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="sidebar-note">
            <?= icon('info') ?>
            <p>Iuran wajib <strong><?= rupiah(IURAN_WAJIB) ?></strong> per anggota setiap bulan.</p>
        </div>

        <div class="sidebar-footer">
            <div class="user-chip">
                <span class="avatar"><?= e(initials($user['nama'])) ?></span>
                <span class="user-chip-text">
                    <strong><?= e($user['nama']) ?></strong>
                    <small><?= $isAdmin ? 'Administrator' : 'Anggota' ?></small>
                </span>
            </div>
            <form method="post" action="logout.php" class="logout-form">
                <?= csrf_field() ?>
                <button type="submit" class="logout-link">
                    <?= icon('logout') ?>
                    <span>Keluar</span>
                </button>
            </form>
        </div>
    </aside>

    <button type="button" class="sidebar-overlay" id="sidebarOverlay" aria-label="Tutup menu"></button>

    <div class="main">
        <header class="topbar">
            <button type="button" class="menu-toggle" id="menuToggle" aria-label="Buka menu" aria-controls="sidebar" aria-expanded="false">
                <?= icon('menu') ?>
            </button>

            <div class="page-heading">
                <h1><?= e($heading) ?></h1>
                <?php if ($subtitle !== ''): ?>
                    <p><?= e($subtitle) ?></p>
                <?php endif; ?>
            </div>

            <div class="topbar-meta">
                <span class="topbar-date"><?= icon('calendar') ?><?= e(tanggal_panjang()) ?></span>
                <span class="role-badge<?= $isAdmin ? ' is-admin' : '' ?>">
                    <?= $isAdmin ? 'Admin' : 'Anggota' ?>
                </span>
            </div>
        </header>

        <main class="content">
            <?= render_flashes() ?>
    <?php
}

function render_app_end(): void
{
    ?>
        </main>
    </div>
</div>
<script src="<?= e(asset('app.js')) ?>"></script>
</body>
</html>
    <?php
}

/**
 * Halaman error 404 sederhana dengan layout aplikasi.
 */
function render_not_found(string $message = 'Data yang Anda cari tidak ditemukan.'): never
{
    require_login();
    http_response_code(404);
    render_app_start('Tidak Ditemukan', '', 'Data Tidak Ditemukan', $message);
    echo '<div class="panel"><div class="empty-state">' . icon('search')
        . '<h3>Data tidak ditemukan</h3><p>' . e($message) . '</p>'
        . '<a class="btn btn-primary" href="' . e(dashboard_url()) . '">' . icon('home') . 'Kembali ke Dashboard</a>'
        . '</div></div>';
    render_app_end();
    exit;
}
