<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Cuti dan izin selama ini dicatat pada satu status absensi yang sama ('leave'),
 * sehingga seluruh laporan hanya bisa menampilkan satu kolom "Cuti / Izin".
 * AttendanceStatus kini punya status tersendiri untuk izin ('permit'), dan
 * pemisahannya ditentukan pada jenis cutinya di menu Jenis Cuti.
 *
 * Migrasi ini memindahkan data yang sudah ada, dan sengaja hanya menyentuh jenis
 * cuti berkode "IZ" — satu-satunya jenis izin yang dibuat aplikasi ini sendiri lewat
 * AttendanceFoundationSeeder. Jenis lain yang dibuat HR tidak ditebak-tebak dari
 * namanya; HR memetakannya sendiri di menu Jenis Cuti.
 *
 * Riwayat pengajuannya sendiri TIDAK disentuh: leave_requests.leave_type_id tetap
 * utuh, jadi catatan "siapa mengambil cuti, siapa mengambil izin" tidak bergantung
 * pada migrasi ini sama sekali. Kolom attendances.status hanyalah nilai turunan yang
 * dihitung AttendanceResolver — bisa dihitung ulang kapan saja lewat tombol
 * "Proses Absensi" pada tanggal yang bersangkutan.
 */
return new class extends Migration
{
    public function up(): void
    {
        $types = DB::table('leave_types')
            ->where('code', 'IZ')
            ->where('attendance_status', 'leave')
            ->update(['attendance_status' => 'permit']);

        $rows = $this->moveAttendances('leave', 'permit');

        $this->report("Jenis cuti dipetakan ke Izin: {$types}. Baris absensi dipindahkan: {$rows}.");
        $this->reportOrphans();
    }

    public function down(): void
    {
        $rows = $this->moveAttendances('permit', 'leave');

        $types = DB::table('leave_types')
            ->where('code', 'IZ')
            ->where('attendance_status', 'permit')
            ->update(['attendance_status' => 'leave']);

        $this->report("Dikembalikan — jenis cuti: {$types}, baris absensi: {$rows}.");
    }

    /**
     * Absensi yang sudah tercatat ikut dipindahkan, kalau tidak laporan periode lalu
     * tetap menghitung izin sebagai cuti. Hanya baris yang memang terhubung ke
     * pengajuan berjenis izin yang disentuh — dicocokkan lewat subquery, bukan JOIN,
     * supaya jalan sama di SQLite maupun MySQL.
     */
    private function moveAttendances(string $from, string $to): int
    {
        $permitTypeIds = DB::table('leave_types')
            ->where('attendance_status', 'permit')
            ->pluck('id');

        if ($permitTypeIds->isEmpty()) {
            return 0;
        }

        return DB::table('attendances')
            ->where('status', $from)
            ->whereIn('leave_request_id', fn ($query) => $query
                ->select('id')
                ->from('leave_requests')
                ->whereIn('leave_type_id', $permitTypeIds))
            ->update(['status' => $to]);
    }

    /**
     * Baris cuti tanpa tautan pengajuan tidak bisa ditentukan cuti atau izin, jadi
     * ia ditinggalkan sebagai "Cuti" dan cukup dilaporkan. AttendanceResolver selalu
     * mengisi leave_request_id saat statusnya berasal dari pengajuan, jadi angkanya
     * normalnya nol; kalau tidak, tanggalnya tinggal diproses ulang dari halaman
     * Absensi Harian.
     */
    private function reportOrphans(): void
    {
        $orphans = DB::table('attendances')
            ->where('status', 'leave')
            ->whereNull('leave_request_id')
            ->count();

        if ($orphans > 0) {
            $this->report("Perhatian: {$orphans} baris berstatus Cuti tidak tertaut ke pengajuan mana pun, jadi tidak bisa dipastikan cuti atau izin. Proses ulang tanggalnya dari Absensi Harian bila perlu.");
        }
    }

    private function report(string $message): void
    {
        if (app()->runningInConsole() && ! app()->runningUnitTests()) {
            echo '  '.$message.PHP_EOL;
        }
    }
};
