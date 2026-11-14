<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_admin();

$errors = [];
$form = [
    'kode' => '',
    'nama' => '',
    'alamat' => '',
    'telepon' => '',
    'username' => '',
];

if (is_post()) {
    verify_csrf();

    $form['kode'] = strtoupper(post('kode'));
    $form['nama'] = post('nama');
    $form['alamat'] = post('alamat');
    $form['telepon'] = post('telepon');
    $form['username'] = strtolower(post('username'));

    $password = $_POST['password'] ?? '';
    $password = is_string($password) ? $password : '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';
    $passwordConfirm = is_string($passwordConfirm) ? $passwordConfirm : '';

    if ($form['kode'] === '') {
        $errors[] = 'Kode anggota wajib diisi.';
    } elseif (!preg_match('/^[A-Z0-9._-]{2,20}$/', $form['kode'])) {
        $errors[] = 'Kode anggota hanya boleh berisi huruf, angka, titik, garis bawah, atau strip (2–20 karakter).';
    } elseif ((int) db_value($conn, 'SELECT COUNT(*) FROM anggota WHERE kode_anggota = ?', [$form['kode']]) > 0) {
        $errors[] = 'Kode anggota sudah digunakan. Gunakan kode lain.';
    }

    if ($form['nama'] === '') {
        $errors[] = 'Nama anggota wajib diisi.';
    } elseif (mb_strlen($form['nama']) > 100) {
        $errors[] = 'Nama anggota maksimal 100 karakter.';
    }

    if ($form['alamat'] === '') {
        $errors[] = 'Alamat wajib diisi.';
    } elseif (mb_strlen($form['alamat']) > 500) {
        $errors[] = 'Alamat maksimal 500 karakter.';
    }

    if ($form['telepon'] === '') {
        $errors[] = 'Nomor telepon wajib diisi.';
    } elseif (!preg_match('/^[0-9+\-\s().]{6,20}$/', $form['telepon'])) {
        $errors[] = 'Nomor telepon tidak valid (6–20 karakter angka/simbol telepon).';
    }

    if ($form['username'] !== '') {
        if (!preg_match('/^[a-z0-9._-]{3,50}$/', $form['username'])) {
            $errors[] = 'Username minimal 3 karakter dan hanya boleh berisi huruf kecil, angka, titik, garis bawah, atau strip.';
        } elseif ((int) db_value($conn, 'SELECT COUNT(*) FROM users WHERE username = ?', [$form['username']]) > 0) {
            $errors[] = 'Username sudah digunakan. Pilih username lain.';
        }

        if (strlen($password) < 6) {
            $errors[] = 'Password akun minimal 6 karakter.';
        } elseif ($password !== $passwordConfirm) {
            $errors[] = 'Konfirmasi password tidak cocok.';
        }
    }

    if ($errors === []) {
        $conn->begin_transaction();

        try {
            $stmt = db_run(
                $conn,
                'INSERT INTO anggota (kode_anggota, nama, alamat, telepon) VALUES (?, ?, ?, ?)',
                [$form['kode'], $form['nama'], $form['alamat'], $form['telepon']]
            );
            $anggotaId = (int) $conn->insert_id;
            $stmt->close();

            if ($form['username'] !== '') {
                $stmt = db_run(
                    $conn,
                    'INSERT INTO users (nama, username, password, role, anggota_id)
                     VALUES (?, ?, ?, \'anggota\', ?)',
                    [
                        $form['nama'],
                        $form['username'],
                        password_hash($password, PASSWORD_DEFAULT),
                        $anggotaId,
                    ]
                );
                $stmt->close();
            }

            $conn->commit();

            $pesan = 'Data anggota berhasil ditambahkan.';
            if ($form['username'] !== '') {
                $pesan .= ' Akun login "' . $form['username'] . '" juga telah dibuat.';
            }
            flash('success', $pesan);
            redirect('anggota.php');
        } catch (Throwable $exception) {
            $conn->rollback();
            error_log('[KOPERASI] Gagal menambah anggota: ' . $exception->getMessage());
            $errors[] = 'Data gagal disimpan. Silakan coba lagi.';
        }
    }
}

render_app_start(
    'Tambah Anggota',
    'anggota.php',
    'Tambah Anggota',
    'Masukkan data anggota baru dan akun login bila diperlukan.'
);
?>

<div class="breadcrumb">
    <a href="anggota.php"><?= icon('back') ?> Data Anggota</a>
    <span>/</span>
    <span>Tambah Anggota</span>
</div>

<?= render_errors($errors) ?>

<form method="post" class="panel">
    <?= csrf_field() ?>

    <div class="panel-head">
        <div>
            <h2>Data Anggota</h2>
            <p>Kolom bertanda <span class="required">*</span> wajib diisi.</p>
        </div>
    </div>

    <div class="form-grid">
        <div class="field">
            <label for="kode">Kode Anggota <span class="required">*</span></label>
            <input id="kode" type="text" name="kode" value="<?= e($form['kode']) ?>"
                   placeholder="Contoh: AG021" maxlength="20" required>
            <small class="field-hint">Gunakan kode unik untuk setiap anggota.</small>
        </div>

        <div class="field">
            <label for="nama">Nama Lengkap <span class="required">*</span></label>
            <input id="nama" type="text" name="nama" value="<?= e($form['nama']) ?>"
                   placeholder="Masukkan nama anggota" maxlength="100" required>
        </div>

        <div class="field">
            <label for="telepon">Nomor Telepon <span class="required">*</span></label>
            <input id="telepon" type="text" name="telepon" value="<?= e($form['telepon']) ?>"
                   placeholder="08xxxxxxxxxx" maxlength="20" required>
        </div>

        <div class="field">
            <label for="alamat">Alamat <span class="required">*</span></label>
            <textarea id="alamat" name="alamat" rows="3" maxlength="500"
                      placeholder="Masukkan alamat lengkap" required><?= e($form['alamat']) ?></textarea>
        </div>
    </div>

    <div class="panel-divider"></div>

    <div class="panel-head">
        <div>
            <h2>Akun Login <span class="badge badge-muted">Opsional</span></h2>
            <p>Buat akun agar anggota dapat melihat riwayat iurannya sendiri.</p>
        </div>
    </div>

    <div class="form-grid">
        <div class="field">
            <label for="username">Username</label>
            <input id="username" type="text" name="username" value="<?= e($form['username']) ?>"
                   placeholder="Contoh: budi" maxlength="50" autocomplete="off">
            <small class="field-hint">Kosongkan jika belum ingin membuat akun login.</small>
        </div>

        <div class="field">
            <label for="password">Password</label>
            <input id="password" type="password" name="password" placeholder="Minimal 6 karakter"
                   minlength="6" autocomplete="new-password">
        </div>

        <div class="field">
            <label for="password_confirm">Konfirmasi Password</label>
            <input id="password_confirm" type="password" name="password_confirm"
                   placeholder="Ulangi password" minlength="6" autocomplete="new-password">
        </div>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary"><?= icon('save') ?> Simpan Anggota</button>
        <a class="btn btn-ghost" href="anggota.php"><?= icon('back') ?> Batal</a>
    </div>
</form>

<?php render_app_end(); ?>
