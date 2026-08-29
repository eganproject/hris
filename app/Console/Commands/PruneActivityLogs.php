<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Memangkas jejak aktivitas yang sudah lewat masa simpannya.
 *
 * Tabel ini bertambah pada hampir setiap tindakan pengguna dan tidak pernah menyusut
 * sendiri. Tanpa pemangkasan, halaman yang gunanya menelusuri kejadian justru akan
 * jadi tabel terbesar di basis data dalam hitungan bulan.
 *
 * Dihapus per potongan, bukan satu perintah DELETE besar: sekali pangkas bisa
 * menyentuh ratusan ribu baris, dan itu mengunci tabelnya terlalu lama.
 */
class PruneActivityLogs extends Command
{
    protected $signature = 'activity:prune {--days=180 : Umur maksimal catatan yang disimpan}';

    protected $description = 'Pangkas jejak aktivitas pengguna yang lebih tua dari masa simpan.';

    public function handle(): int
    {
        $days = max(7, (int) $this->option('days'));
        $cutoff = Carbon::now()->subDays($days);

        $deleted = 0;

        do {
            $batch = ActivityLog::query()
                ->where('created_at', '<', $cutoff)
                ->limit(1000)
                ->delete();

            $deleted += $batch;
        } while ($batch > 0);

        $this->info("Jejak aktivitas sebelum {$cutoff->toDateString()} dipangkas: {$deleted} baris.");

        return self::SUCCESS;
    }
}
