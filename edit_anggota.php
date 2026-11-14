<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_admin();

$id = get_int('id');
if ($id <= 0) {
    render_not_found('ID anggota tidak valid.');
}

$anggota = db_one(
    $conn,
    'SELECT id, kode_anggota, nama, alamat, telepon FROM anggota WHERE id = ? LIMIT 1',
    [$id]
);

if ($anggota === null) {
    render_not_found('Data anggota tidak ditemukan atau sudah dihapus.');
}

$linkedUser = db_one(
    $conn,
    'SELECT id, username FROM users WHERE anggota_id = ? ORDER BY id ASC LIMIT 1',
    [$id]
);

$errors = [];
$form = [
    'kode' => (string) $anggota['kode_anggota'],
    'nama' => (string) $anggota['nama'],
    'alamat' => (string) $anggota['alamat'],
    'telepon' => (string) $anggota['telepon'],
    'username' => $linkedUser !== null ? (string) $linkedUser['username'] : '',
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
    } else {
        $duplikat = (int) db_value(
            $conn,
            'SELECT COUNT(*) FROM anggota WHERE kode_anggota = ? AND id <> ?',
            [$form['kode'], $id]
        );
        if ($duplikat > 0) {
            $errors[] = 'Kode anggota sudah digunakan oleh anggota lain.';
        }
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

    if ($linkedUser !== null) {
        if ($form['username'] === '') {
            $errors[] = 'Username akun yang sudah terhubung tidak boleh dikosongkan.';
        } elseif (!preg_match('/^[a-z0-9._-]{3,50}$/', $form['username'])) {
            $errors[] = 'Username minimal 3 karakter dan hanya boleh berisi huruf kecil, angka, titik, garis bawah, atau strip.';
        } else {
            $duplikatUser = (int) db_value(
                $conn,
                'SELECT COUNT(*) FROM users WHERE username = ? AND id <> ?',
                [$form['username'], (int) $linkedUser['id']]
            );
            if ($duplikatUser > 0) {
                $errors[] = 'Username sudah digunakan oleh akun lain.';
            }
        }

        if ($password !== '' && strlen($password) < 6) {
            $errors[] = 'Password baru minimal 6 karakter.';
        } elseif ($password !== '' && $password !== $passwordConfirm) {
            $errors[] = 'Konfirmasi password baru tidak cocok.';
        }
    } elseif ($form['username'] !== '') {
        if (!preg_match('/^[a-z0-9._-]{3,50}$/', $form['username'])) {
            $errors[] = 'Username minimal 3 karakter dan hanya boleh berisi huruf kecil, angka, titik, garis bawah, atau strip.';
        } else {
            $duplikatUser = (int) db_value(
                $conn,
                'SELECT COUNT(*) FROM users WHERE username = ?',
                [$form['username']]
            );
            if ($duplikatUser > 0) {
                $errors[] = 'Username sudah digunakan oleh akun lain.';
            }
        }

        if (strlen($password) < 6) {
            $errors[] = 'Password akun baru minimal 6 karakter.';
        } elseif ($password !== $passwordConfirm) {
            $errors[] = 'Konfirmasi password tidak cocok.';
        }
    }

    if ($errors === []) {
        $conn->begin_transaction();

        try {
            $stmt = db_run(
                $conn,
                'UPDATE anggota SET kode_anggota = ?, nama = ?, alamat = ?, telepon = ? WHERE id = ?',
                [$form['kode'], $form['nama'], $form['alamat'], $form['telepon'], $id]
            );
            $stmt->close();

            if ($linkedUser !== null) {
                if ($password !== '') {
                    $stmt = db_run(
                        $conn,
                        'UPDATE users SET nama = ?, username = ?, password = ? WHERE id = ?',
                        [
                            $form['nama'],
                            $form['username'],
                            password_hash($password, PASSWORD_DEFAULT),
                            (int) $linkedUser['id'],
                        ]
                    );
                } else {
                    $stmt = db_run(
                        $conn,
                        'UPDATE users SET nama = ?, username = ? WHERE id = ?',
                        [$form['nama'], $form['username'], (int) $linkedUser['id']]
                    );
                }
                $stmt->close();
            } elseif ($form['username'] !== '') {
                $stmt = db_run(
                    $conn,
                    'INSERT INTO users (nama, username, password, role, anggota_id)
                     VALUES (?, ?, ?, \'anggota\', ?)',
                    [
                        $form['nama'],
                        $form['username'],
                        password_hash($password, PASSWORD_DEFAULT),
                        $id,
                    ]
                );
                $stmt->close();
            }

            $conn->commit();
            flash('success', 'Data anggota berhasil diperbarui.');
            redirect('anggota.php');
        } catch (Throwable $exception) {
            $conn->rollback();
            error_log('[KOPERASI] Gagal memperbarui anggota: ' . $exception->getMessage());
            $errors[] = 'Data gagal diperbarui. Silakan coba lagi.';
        }
    }
}

render_app_start(
    'Edit Anggota',
    'anggota.php',
    'Edit Anggota',
    'Perbarui data anggota dan akun login.'
);
?>

<div class="breadcrumb">
    <a href="anggota.php"><?= icon('back') ?> Data Anggota</a>
    <span>/</span>
    <span>Edit Anggota</span>
</div>

<?= render_errors($errors) ?>

<form method="post" action="edit_anggota.php?id=<?= $id ?>" class="panel">
    <?= csrf_field() ?>

    <div class="panel-head">
        <div>
            <h2>Data Anggota</h2>
            <p>Kolom bertanda <span class="required">*</span> wajib diisi.</p>
        </div>
        <span class="badge badge-info">ID <?= $id ?></span>
    </div>

    <div class="form-grid">
        <div class="field">
            <label for="kode">Kode Anggota <span class="required">*</span></label>
            <input id="kode" type="text" name="kode" value="<?= e($form['kode']) ?>"
                   maxlength="20" required>
        </div>

        <div class="field">
            <label for="nama">Nama Lengkap <span class="required">*</span></label>
            <input id="nama" type="text" name="nama" value="<?= e($form['nama']) ?>"
                   maxlength="100" required>
        </div>

        <div class="field">
            <label for="telepon">Nomor Telepon <span class="required">*</span></label>
            <input id="telepon" type="text" name="telepon" value="<?= e($form['telepon']) ?>"
                   maxlength="20" required>
        </div>

        <div class="field">
            <label for="alamat">Alamat <span class="required">*</span></label>
            <textarea id="alamat" name="alamat" rows="3" maxlength="500" required><?= e($form['alamat']) ?></textarea>
        </div>
    </div>

    <div class="panel-divider"></div>

    <div class="panel-head">
        <div>
            <h2>Akun Login</h2>
            <p>
                <?= $linkedUser !== null
                    ? 'Akun sudah terhubung. Kosongkan password jika tidak ingin menggantinya.'
                    : 'Belum ada akun. Isi username dan password untuk membuat akun anggota.' ?>
            </p>
        </div>
        <?php if ($linkedUser !== null): ?>
            <span class="badge badge-success">Akun aktif</span>
        <?php else: ?>
            <span class="badge badge-muted">Belum ada akun</span>
        <?php endif; ?>
    </div>

    <div class="form-grid">
        <div class="field">
            <label for="username">Username<?= $linkedUser === null ? '' : ' <span class="required">*</span>' ?></label>
            <input id="username" type="text" name="username" value="<?= e($form['username']) ?>"
                   maxlength="50" autocomplete="off" <?= $linkedUser !== null ? 'required' : '' ?>>
        </div>

        <div class="field">
            <label for="password"><?= $linkedUser !== null ? 'Password Baru (opsional)' : 'Password' ?></label>
            <input id="password" type="password" name="password"
                   placeholder="<?= $linkedUser !== null ? 'Kosongkan jika tidak diubah' : 'Minimal 6 karakter' ?>"
                   minlength="6" autocomplete="new-password">
        </div>

        <div class="field">
            <label for="password_confirm">Konfirmasi Password</label>
            <input id="password_confirm" type="password" name="password_confirm"
                   placeholder="Ulangi password" minlength="6" autocomplete="new-password">
        </div>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary"><?= icon('save') ?> Simpan Perubahan</button>
        <a class="btn btn-ghost" href="anggota.php"><?= icon('back') ?> Batal</a>
    </div>
</form>

<?php render_app_end(); ?>
