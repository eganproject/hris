<?php

namespace App\Observers;

use App\Models\ActivityLog;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetStorageLocation;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Device;
use App\Models\Employee;
use App\Models\EmployeeContract;
use App\Models\Holiday;
use App\Models\JobPosition;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\ScheduleAssignment;
use App\Models\SchedulePattern;
use App\Models\Shift;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Mencatat penambahan, perubahan, dan penghapusan pada data induk ke jejak aktivitas.
 *
 * Daftar model yang diawasi sengaja pendek dan eksplisit. Yang TIDAK ada di sini juga
 * disengaja: employee_schedules, attendances, dan attendance_punches ditulis ribuan
 * baris sekaligus oleh generator dan mesin absensi — mencatatnya satu per satu akan
 * menenggelamkan halaman ini sampai tak terbaca, sekaligus melipatgandakan ukuran
 * basis data. Untuk hal semacam itu yang dicatat adalah TINDAKANNYA
 * ("Generate roster Agustus 2026 — 217 hari"), dipanggil langsung dari controller-nya.
 */
class ActivityObserver
{
    /**
     * Model yang diawasi => modul tempatnya muncul di halaman aktivitas.
     *
     * @var array<class-string<Model>, string>
     */
    public const WATCHED = [
        Asset::class => 'assets',
        AssetCategory::class => 'assets',
        AssetStorageLocation::class => 'assets',
        Employee::class => 'employees',
        EmployeeContract::class => 'contracts',
        Shift::class => 'shifts',
        SchedulePattern::class => 'schedule-patterns',
        ScheduleAssignment::class => 'schedules',
        LeaveRequest::class => 'leave',
        LeaveType::class => 'leave-types',
        Holiday::class => 'holidays',
        Branch::class => 'organization',
        Department::class => 'organization',
        JobPosition::class => 'organization',
        Device::class => 'devices',
        User::class => 'users',
    ];

    public function created(Model $model): void
    {
        $this->record($model, 'created', 'Menambah');
    }

    public function updated(Model $model): void
    {
        $changes = ActivityLogger::changesOf($model);

        // Penyimpanan yang tidak mengubah apa pun selain stempel waktu bukan
        // aktivitas; mencatatnya hanya menambah derau.
        if ($changes === []) {
            return;
        }

        $this->record($model, 'updated', 'Mengubah', ['changes' => $changes]);
    }

    public function deleted(Model $model): void
    {
        // Soft delete adalah pengarsipan, bukan penghapusan — dan bedanya penting bagi
        // orang yang sedang menelusuri ke mana perginya sebuah data. Jenis kejadiannya
        // tetap "deleted" agar penyaringnya sederhana; yang membedakan kalimatnya.
        $archived = in_array(SoftDeletes::class, class_uses_recursive($model), true) && $model->trashed();

        $this->record($model, 'deleted', $archived ? 'Mengarsipkan' : 'Menghapus');
    }

    public function restored(Model $model): void
    {
        $this->record($model, 'restored', 'Memulihkan');
    }

    public function forceDeleted(Model $model): void
    {
        $this->record($model, 'deleted', 'Menghapus permanen');
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private function record(Model $model, string $event, string $verb, array $properties = []): void
    {
        $module = self::WATCHED[$model::class] ?? null;

        if (! $module) {
            return;
        }

        $noun = ActivityLog::MODULES[$module] ?? $module;
        $label = ActivityLogger::labelFor($model);

        ActivityLogger::log(
            $module,
            $event,
            trim("{$verb} {$noun}: {$label}"),
            $model,
            $properties,
            $label,
        );
    }
}
