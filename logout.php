<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_login();

if (!is_post()) {
    flash('warning', 'Aksi keluar harus dilakukan melalui tombol Keluar yang tersedia.');
    redirect(dashboard_url());
}

verify_csrf();

logout_user();

// Mulai session baru hanya untuk membawa pesan flash ke halaman login.
session_start();
flash('info', 'Anda telah keluar dari sistem.');

redirect('index.php');
