<?php

/*
 * Fitur yang disembunyikan tanpa menghapus datanya (Ega, 2026-11-26).
 *
 * Absensi kantor pindah ke tablet pengenalan wajah dan aplikasi HP menjadi
 * layanan mandiri karyawan, jadi absen lewat HP dan Kabar dimatikan. Tabel,
 * foto, dan postingan lama tetap tersimpan; set env ke true untuk membuka lagi.
 */
return [
    // Absen masuk/pulang dari aplikasi HP (API clock/consent, foto selfie,
    // menu web Absensi > Absen Aplikasi HP).
    'mobile_clock' => (bool) env('FEATURE_MOBILE_CLOCK', false),

    // Kabar: feed, postingan, komentar karyawan (API) + menu web Moderasi Kabar.
    // Pengumuman HR tidak termasuk dan tetap aktif.
    'kabar' => (bool) env('FEATURE_KABAR', false),
];
