<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Holiday;
use App\Services\DefaultOfficeSchedule;
use App\Support\LeaveCalendar;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Read-only self-service roster: lets any logged-in employee check their own work
 * schedule for a month — shift, hari libur, dan cara kerjanya hari itu (kantor /
 * WFH / dinas luar) — plus daftar singkat hari kerja berikutnya.
 * Available to every authenticated user with a linked employee record — no special
 * permission required (unlike the shift-swap page).
 */
class MyRosterController extends Controller
{
    /**
     * Jendela pencarian "hari kerja berikutnya" untuk karyawan jam kantor. 21 hari
     * memuat 7 hari kerja pada pola mingguan mana pun, termasuk rotasi 2-on/2-off.
     */
    private const UPCOMING_HORIZON_DAYS = 21;

    public function __construct(private readonly DefaultOfficeSchedule $officeSchedule) {}

    public function index(Request $request): View
    {
        $employee = auth()->user()->employee;

        abort_unless($employee, 403, 'Akun Anda belum tertaut ke data karyawan.');

        $month = $this->resolveMonth($request->input('month'));
        $from = $month->copy()->startOfMonth();
        $to = $month->copy()->endOfMonth();

        $days = collect(CarbonPeriod::create($from, $to)->toArray());

        // Karyawan "jam kantor" tidak punya baris roster sama sekali. Lengkapi harinya
        // dari pola yang berlaku baginya — sumber yang sama dengan grid HR dan
        // AttendanceResolver — supaya bulannya tidak tampil "belum dijadwalkan"
        // padahal absensinya diproses normal. Baris nyata tetap menang.
        $schedules = $this->officeSchedule
            ->fill(
                $employee,
                $employee->schedules()
                    ->whereBetween('work_date', [$from->toDateString(), $to->toDateString()])
                    ->with('shift')
                    ->get(),
                $days,
            )
            ->keyBy(fn ($row) => $row->work_date->toDateString());

        // National holidays (and this employee's branch holidays) overlay the grid.
        $holidays = Holiday::query()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->where(fn ($query) => $query->where('is_national', true)->orWhere('branch_id', $employee->branch_id))
            ->get()
            ->keyBy(fn (Holiday $holiday) => $holiday->date->toDateString());

        // Pengajuan yang disetujui menimpa jadwal: cuti membuat harinya kosong, WFH
        // dan dinas luar tetap hari kerja tapi berubah tempatnya. Yang masih menunggu
        // keputusan tidak mengubah jadwal, hanya ditandai — lihat LeaveCalendar.
        $calendar = LeaveCalendar::for($employee, $from, $to);

        $upcoming = $this->upcomingWorkDays($employee);

        $workDays = $schedules->filter(fn ($row) => ! $row->is_day_off && $row->shift_id !== null)->count();

        return view('attendance.my-roster.index', [
            'employee' => $employee,
            'month' => $month,
            'days' => $days,
            'schedules' => $schedules,
            'holidays' => $holidays,
            'calendar' => $calendar,
            'upcoming' => $upcoming,
            'workDays' => $workDays,
            'offDays' => $schedules->count() - $workDays,
            'prevMonth' => $month->copy()->subMonth()->format('Y-m'),
            'nextMonth' => $month->copy()->addMonth()->format('Y-m'),
            'today' => now()->startOfDay(),
        ]);
    }

    /**
     * Hari kerja terdekat (maks 7). Karyawan berjadwal cukup dibaca dari roster;
     * karyawan "jam kantor" tidak punya baris apa pun, jadi jendelanya disintesis
     * dari pola dalam horizon terbatas — cukup lebar untuk memuat 7 hari kerja pada
     * pola mana pun, dan tetap satu perhitungan kecil alih-alih query tak berbatas.
     */
    private function upcomingWorkDays(Employee $employee): Collection
    {
        $from = now()->startOfDay();
        $isWorkDay = fn ($row) => ! $row->is_day_off && $row->shift_id !== null;

        if (! $this->officeSchedule->isConfiguredFor($employee)) {
            return $employee->schedules()
                ->whereDate('work_date', '>=', $from->toDateString())
                ->where('is_day_off', false)
                ->whereNotNull('shift_id')
                ->with('shift')
                ->orderBy('work_date')
                ->limit(7)
                ->get();
        }

        $to = $from->copy()->addDays(self::UPCOMING_HORIZON_DAYS);

        $rows = $employee->schedules()
            ->whereBetween('work_date', [$from->toDateString(), $to->toDateString()])
            ->with('shift')
            ->get();

        return $this->officeSchedule
            ->fill($employee, $rows, CarbonPeriod::create($from, $to))
            ->filter($isWorkDay)
            ->sortBy(fn ($row) => $row->work_date->toDateString())
            ->take(7)
            ->values();
    }

    private function resolveMonth(?string $value): Carbon
    {
        try {
            return $value ? Carbon::createFromFormat('Y-m', $value)->startOfMonth() : now()->startOfMonth();
        } catch (\Throwable) {
            return now()->startOfMonth();
        }
    }
}
