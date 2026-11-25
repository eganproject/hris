<?php

namespace App\Http\Controllers\Api\Mobile\Hr;

use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\ScheduleAcknowledgement;
use App\Models\Shift;
use App\Services\ScheduleGenerator;
use App\Support\ApprovalNotifier;
use App\Support\Mobile\Present;
use App\Support\Mobile\ScheduleDays;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * "Atur jadwal tim" untuk atasan: grid mingguan anggota tim langsung, ubah satu sel,
 * salin minggu lalu, dan terbitkan. Perubahan ditulis sebagai override harian lewat
 * ScheduleGenerator::override() — jalur yang sama dengan grid Jadwal Kerja di web,
 * jadi absensi hari itu ikut dihitung ulang dan generator tidak menimpanya.
 *
 * Hari yang sudah lewat tidak bisa diubah dari HP: jadwal masa lalu menentukan
 * telat/alfa yang sudah terhitung, perubahan seperti itu tetap lewat HR di web.
 */
class TeamScheduleController extends HrController
{
    public function __construct(
        private readonly ScheduleDays $days,
        private readonly ScheduleGenerator $generator,
    ) {}

    /** ?week_start=YYYY-MM-DD (dibulatkan ke Senin; bawaan minggu depan). */
    public function show(Request $request): JsonResponse
    {
        $leader = $this->leader();
        $weekStart = $this->weekStart($request->input('week_start'));

        return $this->ok($this->grid($leader, $weekStart));
    }

    public function shifts(): JsonResponse
    {
        $this->leader();

        return $this->ok(
            Shift::query()->where('is_active', true)->orderBy('start_time')->get()
                ->map(fn (Shift $shift) => [
                    'id' => $shift->id,
                    'code' => $shift->code,
                    'name' => $shift->name,
                    'start_time' => substr((string) $shift->start_time, 0, 5),
                    'end_time' => substr((string) $shift->end_time, 0, 5),
                ])->values(),
        );
    }

    /** Ubah satu sel: shift tertentu, WFH (dengan shift), atau libur. */
    public function update(Request $request): JsonResponse
    {
        $leader = $this->leader();

        $data = $request->validate([
            'employee_id' => ['required', 'integer'],
            // Satu hari (date) atau beberapa hari sekaligus (dates[]), mis. Sen–Jum.
            'date' => ['required_without:dates', 'date', 'after_or_equal:today'],
            'dates' => ['required_without:date', 'array', 'min:1', 'max:31'],
            'dates.*' => ['date', 'after_or_equal:today'],
            'is_day_off' => ['boolean'],
            'is_wfh' => ['boolean'],
            'shift_id' => [Rule::requiredIf(fn () => ! $request->boolean('is_day_off')), 'nullable', 'integer', Rule::exists('shifts', 'id')->where('is_active', true)],
        ], [
            'date.after_or_equal' => 'Jadwal hari yang sudah lewat hanya bisa diubah HR dari web.',
            'dates.*.after_or_equal' => 'Jadwal hari yang sudah lewat hanya bisa diubah HR dari web.',
            'shift_id.required' => 'Pilih shift, atau tandai sebagai libur.',
        ]);

        $member = $this->member($leader, (int) $data['employee_id']);
        $isDayOff = (bool) ($data['is_day_off'] ?? false);

        $dates = collect($data['dates'] ?? [$data['date']])->map(fn ($d) => Carbon::parse($d)->startOfDay())->unique(fn ($d) => $d->toDateString());

        DB::transaction(function () use ($dates, $member, $isDayOff, $data): void {
            foreach ($dates as $date) {
                $this->generator->override(
                    $member,
                    $date,
                    $isDayOff ? null : (int) $data['shift_id'],
                    $isDayOff,
                    'Diatur atasan lewat aplikasi HP',
                    (bool) ($data['is_wfh'] ?? false),
                );
            }
        });

        $message = $dates->count() > 1 ? 'Jadwal '.$member->full_name.' diatur untuk '.$dates->count().' hari.' : null;

        return $this->ok($this->grid($leader, $dates->first()->copy()->startOfWeek()), 200, $message ? ['message' => $message] : []);
    }

    /** Salin jadwal minggu sebelumnya ke minggu ini (hanya hari yang belum lewat). */
    public function copyLastWeek(Request $request): JsonResponse
    {
        $leader = $this->leader();
        $weekStart = $this->weekStart($request->validate(['week_start' => ['required', 'date']])['week_start']);
        $today = now()->startOfDay();
        $copied = 0;

        DB::transaction(function () use ($leader, $weekStart, $today, &$copied): void {
            foreach ($this->members($leader) as $member) {
                $source = EmployeeSchedule::query()
                    ->where('employee_id', $member->id)
                    ->whereBetween('work_date', [$weekStart->copy()->subWeek()->toDateString(), $weekStart->copy()->subDay()->toDateString()])
                    ->get()
                    ->keyBy(fn (EmployeeSchedule $row) => $row->work_date->toDateString());

                for ($i = 0; $i < 7; $i++) {
                    $date = $weekStart->copy()->addDays($i);
                    $from = $source->get($date->copy()->subWeek()->toDateString());

                    if (! $from || $date->lessThan($today)) {
                        continue;
                    }

                    $this->generator->override($member, $date, $from->shift_id, (bool) $from->is_day_off, 'Disalin dari minggu lalu (aplikasi HP)', (bool) $from->is_wfh);
                    $copied++;
                }
            }
        });

        return $this->ok($this->grid($leader, $weekStart), 200, ['message' => "{$copied} hari disalin dari minggu lalu."]);
    }

    /** Terbitkan: anggota tim diberi tahu dan diminta mengonfirmasi. Terbit ulang = konfirmasi ulang. */
    public function publish(Request $request): JsonResponse
    {
        $leader = $this->leader();
        $weekStart = $this->weekStart($request->validate(['week_start' => ['required', 'date']])['week_start']);
        $members = $this->members($leader);

        foreach ($members as $member) {
            ScheduleAcknowledgement::query()->updateOrCreate(
                ['employee_id' => $member->id, 'week_start' => $weekStart->toDateString()],
                ['published_by' => $request->user()->id, 'published_at' => now(), 'confirmed_at' => null],
            );

            app(ApprovalNotifier::class)->schedulePublished($member->loadMissing('user'), $weekStart, $leader->full_name);
        }

        return $this->ok($this->grid($leader, $weekStart), 200, ['message' => 'Jadwal diterbitkan ke '.$members->count().' anggota tim.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function grid(Employee $leader, CarbonInterface $weekStart): array
    {
        $weekEnd = $weekStart->copy()->addDays(6);
        $members = $this->members($leader);

        $acks = ScheduleAcknowledgement::query()
            ->whereIn('employee_id', $members->pluck('id'))
            ->where('week_start', $weekStart->toDateString())
            ->get()
            ->keyBy('employee_id');

        $rows = $members->map(function (Employee $member) use ($weekStart, $weekEnd, $acks, $leader) {
            $days = $this->days->for($member, $weekStart, $weekEnd);
            $ack = $acks->get($member->id);

            return [
                'employee' => Present::person($member),
                'manager_name' => $member->manager_id === $leader->id ? null : $member->manager?->full_name,
                'confirmed' => (bool) $ack?->confirmed_at,
                'days' => $days->map(fn (array $day) => [
                    'date' => $day['date'],
                    'code' => $this->code($day),
                    'mode' => $day['mode'],
                    'shift_id' => $day['shift']['id'] ?? null,
                    'shift_name' => $day['shift']['name'] ?? null,
                    'label' => $day['label'],
                    'time' => $day['shift'] ? $day['shift']['start_time'].' – '.$day['shift']['end_time'] : null,
                    'editable' => Carbon::parse($day['date'])->greaterThanOrEqualTo(now()->startOfDay()) && $day['mode'] !== 'leave',
                ])->values(),
            ];
        })->values();

        $publishedAt = $acks->max('published_at');

        return [
            'week_start' => $weekStart->toDateString(),
            'week_end' => $weekEnd->toDateString(),
            'team_size' => $members->count(),
            'branch' => $leader->branch?->name,
            'unscheduled_days' => $rows->sum(fn ($r) => collect($r['days'])->where('mode', 'none')->count()),
            'published_at' => $publishedAt ? Carbon::parse($publishedAt)->toIso8601String() : null,
            'confirmed_count' => $rows->where('confirmed', true)->count(),
            'members' => $rows,
        ];
    }

    /** Kode singkat sel grid: kode shift, W (WFH), L (libur), C (cuti/izin), null = belum dijadwalkan. */
    private function code(array $day): ?string
    {
        return match ($day['mode']) {
            'wfh' => 'W',
            'off' => 'L',
            'leave' => 'C',
            'trip' => 'DL',
            'none' => null,
            default => $day['shift']['code'] ?? '?',
        };
    }

    /** @return Collection<int, Employee> */
    private function members(Employee $leader): Collection
    {
        return Employee::query()->active()
            ->whereIn('id', $this->teamIds($leader))
            ->with(['jobPosition', 'department', 'branch', 'manager:id,full_name'])
            ->orderBy('full_name')
            ->get();
    }

    private function member(Employee $leader, int $employeeId): Employee
    {
        $member = Employee::query()->active()->find($employeeId);

        abort_unless($member && in_array($member->id, $this->teamIds($leader), true), 403, 'Karyawan ini bukan anggota tim Anda.');

        return $member;
    }

    private function weekStart(?string $input): Carbon
    {
        return ($input ? Carbon::parse($input) : now()->addWeek())->startOfWeek();
    }
}
