<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_admin();

/**
 * File lama yang masih dipakai oleh tautan lama.
 * Dialihkan ke halaman edit yang baru.
 */

$id = get_int('id');

if ($id > 0) {
    redirect('edit_anggota.php?id=' . $id);
}

redirect('anggota.php');
