<?php

namespace App\Console\Commands;

use App\Models\Attendance;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Hapus foto selfie absen mandiri yang sudah lewat masa simpan. Baris absensinya
 * tetap utuh (jam, status, koordinat) — hanya file gambarnya yang dibuang, karena
 * itulah yang menghabiskan storage.
 */
class PruneAttendanceSelfies extends Command
{
    protected $signature = 'attendance:prune-selfies {--months=6 : Umur simpan foto dalam bulan}';

    protected $description = 'Hapus foto selfie absen mandiri yang lebih tua dari masa simpan.';

    public function handle(): int
    {
        $months = max(1, (int) $this->option('months'));
        $cutoff = now()->subMonths($months)->toDateString();
        $disk = Storage::disk('public');
        $deleted = 0;

        Attendance::query()
            ->where('work_date', '<', $cutoff)
            ->where(fn ($query) => $query
                ->whereNotNull('clock_in_photo_path')
                ->orWhereNotNull('clock_out_photo_path'))
            ->chunkById(200, function ($rows) use ($disk, &$deleted) {
                foreach ($rows as $attendance) {
                    foreach (['clock_in_photo_path', 'clock_out_photo_path'] as $column) {
                        if ($attendance->{$column}) {
                            $disk->delete($attendance->{$column});
                            $deleted++;
                        }
                    }

                    $attendance->forceFill([
                        'clock_in_photo_path' => null,
                        'clock_out_photo_path' => null,
                    ])->save();
                }
            });

        $this->info("{$deleted} foto selfie sebelum {$cutoff} dihapus.");

        return self::SUCCESS;
    }
}
