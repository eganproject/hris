<?php

namespace App\Services\Mobile;

use App\Models\Attendance;
use Illuminate\Support\Carbon;

/**
 * Sesi kerja yang masih terbuka: sudah absen masuk, belum absen pulang.
 * Jam masuknya bisa berasal dari aplikasi HP, mesin sidik jari, atau koreksi.
 */
final class OpenSession
{
    public function __construct(
        public readonly Attendance $attendance,
        public readonly Carbon $clockInAt,
        public readonly Carbon $workDate,
    ) {}
}
