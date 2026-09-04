<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Penolakan alur serah-terima aset yang kalimatnya memang untuk dibaca pengguna.
 *
 * Ada aturan yang tidak bisa dijamin oleh validasi formulir: apakah aset ini masih
 * tersedia, dan apakah belum ada orang lain yang memegangnya. Jawabannya baru pasti
 * di dalam transaksi, setelah barisnya dikunci — dua petugas yang menekan "Serahkan"
 * pada detik yang sama sama-sama lolos validasi, dan hanya satu yang boleh berhasil.
 *
 * Karena itu penolakannya berupa pengecualian, bukan validasi: ia terjadi setelah
 * formulir dinyatakan sah. Controller menangkapnya dan mengubahnya menjadi pesan di
 * layar.
 */
class AssetCustodyException extends RuntimeException {}
