<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

if (is_logged_in()) {
    redirect(dashboard_url());
}

$errors = [];
$username = '';

if (is_post()) {
    verify_csrf();

    $username = post('username');
    $password = $_POST['password'] ?? '';
    $password = is_string($password) ? $password : '';

    $now = time();
    $lockedUntil = (int) ($_SESSION['login_locked_until'] ?? 0);

    if ($lockedUntil > $now) {
        $sisa = $lockedUntil - $now;
        $errors[] = 'Terlalu banyak percobaan login. Silakan coba lagi dalam ' . $sisa . ' detik.';
    } elseif ($username === '' || $password === '') {
        $errors[] = 'Username dan password wajib diisi.';
    } else {
        $user = db_one(
            $conn,
            'SELECT id, nama, username, password, role, anggota_id
             FROM users
             WHERE username = ?
             LIMIT 1',
            [$username]
        );

        if ($user !== null && password_verify($password, (string) $user['password'])) {
            if (password_needs_rehash((string) $user['password'], PASSWORD_DEFAULT)) {
                db_run(
                    $conn,
                    'UPDATE users SET password = ? WHERE id = ?',
                    [password_hash($password, PASSWORD_DEFAULT), (int) $user['id']]
                );
            }

            login_user($user);
            flash('success', 'Selamat datang, ' . $user['nama'] . '.');
            redirect(dashboard_url());
        }

        $attempts = (int) ($_SESSION['login_attempts'] ?? 0) + 1;
        $_SESSION['login_attempts'] = $attempts;

        if ($attempts >= 5) {
            $_SESSION['login_attempts'] = 0;
            $_SESSION['login_locked_until'] = $now + 30;
            $errors[] = 'Terlalu banyak percobaan login. Silakan coba lagi dalam 30 detik.';
        } else {
            $errors[] = 'Username atau password salah.';
        }
    }
}

render_head('Masuk', 'auth-page');
?>
<div class="auth-shell">
    <div class="auth-card">
        <section class="auth-brand">
            <div class="brand brand-light">
                <span class="brand-logo"><?= icon('bank') ?></span>
                <span class="brand-text">
                    <strong><?= e(APP_NAME) ?></strong>
                    <small><?= e(APP_TAGLINE) ?></small>
                </span>
            </div>

            <div class="auth-copy">
                <h2>Kelola iuran koperasi dengan rapi dan transparan.</h2>
                <p>Pantau pembayaran anggota, rekap bulanan, dan laporan kas dalam satu sistem terpadu.</p>
                <ul class="auth-points">
                    <li><?= icon('check') ?>Data anggota dan iuran tersimpan di database</li>
                    <li><?= icon('check') ?>Riwayat pembayaran dapat ditelusuri</li>
                    <li><?= icon('check') ?>Laporan siap dicetak</li>
                </ul>
            </div>

            <p class="auth-credit"><?= e(APP_TAGLINE) ?> &middot; <?= date('Y') ?></p>
        </section>

        <section class="auth-form-panel">
            <div class="auth-form-head">
                <span class="eyebrow">Selamat datang</span>
                <h1>Masuk ke akun</h1>
                <p>Gunakan akun admin atau anggota yang terdaftar.</p>
            </div>

            <?= render_flashes() ?>
            <?= render_errors($errors) ?>

            <form method="post" class="auth-form">
                <?= csrf_field() ?>

                <div class="field">
                    <label for="username">Username</label>
                    <input
                        id="username"
                        type="text"
                        name="username"
                        value="<?= e($username) ?>"
                        placeholder="Masukkan username"
                        autocomplete="username"
                        maxlength="50"
                        required
                        autofocus>
                </div>

                <div class="field">
                    <label for="password">Password</label>
                    <input
                        id="password"
                        type="password"
                        name="password"
                        placeholder="Masukkan password"
                        autocomplete="current-password"
                        required>
                </div>

                <button type="submit" class="btn btn-primary btn-block">
                    <?= icon('logout') ?>
                    Masuk
                </button>
            </form>

            <details class="demo-box">
                <summary><?= icon('info') ?> Keamanan akun</summary>
                <p>
                    Gunakan password yang kuat dan unik. Akun demo hanya tersedia pada
                    database contoh untuk keperluan evaluasi, bukan untuk produksi.
                </p>
            </details>

            <p class="auth-footer">&copy; <?= date('Y') ?> <?= e(APP_NAME) ?>. Semua data tersimpan di database koperasi.</p>
        </section>
    </div>
</div>
<script src="<?= e(asset('app.js')) ?>"></script>
</body>
</html>
