<?php

/*
|--------------------------------------------------------------------------
| Helper sekali-pakai: buat symlink storage di hosting
|--------------------------------------------------------------------------
| Dipakai bila `php artisan storage:link` tidak bisa dijalankan di server
| (mis. di shared hosting seperti Hostinger).
|
| Cara pakai:
|   1. Pastikan file ini ada di folder public/ pada server.
|   2. Akses lewat browser: https://domain-kamu.com/link-storage.php
|   3. Setelah muncul pesan BERHASIL, HAPUS file ini demi keamanan.
*/

header('Content-Type: text/plain; charset=utf-8');

$link   = __DIR__ . '/storage';
$target = realpath(__DIR__ . '/../storage/app/public');

if ($target === false) {
    exit("GAGAL: folder target storage/app/public tidak ditemukan di server.\n");
}

// Bersihkan symlink lama yang rusak atau salah arah (mis. path Windows).
if (is_link($link)) {
    @unlink($link);
    echo "Symlink lama dihapus.\n";
} elseif (is_dir($link)) {
    exit("public/storage sudah berupa folder biasa (bukan symlink).\n"
        . "Hapus/pindahkan folder itu dulu bila ingin dijadikan symlink.\n");
} elseif (file_exists($link)) {
    exit("public/storage sudah ada sebagai file. Hapus dulu secara manual.\n");
}

if (@symlink($target, $link)) {
    echo "BERHASIL: public/storage -> {$target}\n";
    echo "Sekarang HAPUS file link-storage.php ini demi keamanan.\n";
} else {
    echo "GAGAL membuat symlink.\n";
    echo "Fungsi symlink() kemungkinan dinonaktifkan oleh hosting.\n";
    echo "Solusi: minta support Hostinger mengaktifkan symlink(), atau gunakan\n";
    echo "pendekatan tanpa symlink (arahkan disk 'public' langsung ke public/storage).\n";
}
