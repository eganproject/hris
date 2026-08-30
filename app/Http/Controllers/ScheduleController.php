<?php

namespace App\Http\Controllers;

use App\Enums\LeaveRequestStatus;
use App\Enums\ScheduleSource;
use App\Exports\ScheduleTemplateExport;
use App\Exports\UnscheduledEmployeesExport;
use App\Http\Requests\ScheduleAssignmentRequest;
use App\Http\Requests\ScheduleOverrideRequest;
use App\Imports\ScheduleMatrixImport;
use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\Holiday;
use App\Models\JobPosition;
use App\Models\LeaveRequest;
use App\Models\ScheduleAssignment;
use App\Models\SchedulePattern;
use App\Models\Shift;
use App\Models\User;
use App\Services\DefaultOfficeSchedule;
use App\Services\ScheduleGenerator;
use App\Support\ActivityLogger;
use App\Support\DataScope;
use App\Support\ImportErrorStore;
use App\Support\MonthInput;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ScheduleController extends Controller
{
    public function __construct(
        private readonly ScheduleGenerator $generator,
        private readonly DefaultOfficeSchedule $officeSchedule,
    ) {}

    /** Pilihan jumlah baris per halaman, dipakai grid roster dan daftar penugasan. */
    private const PER_PAGE_OPTIONS = [25, 50, 100];

    /**
     * Monthly roster grid: employees × days, showing the materialized schedule.
     *
     * Roster dan daftar penugasan tinggal di dua tab. Keduanya panjang, dan
     * menumpuknya memaksa pengguna menggulir melewati satu bulan penuh jadwal hanya
     * untuk sampai ke yang kedua. Tab dipilih di server, bukan di klien, supaya tab
     * yang tidak dibuka tidak ikut di-query sama sekali — dulu satu kali buka halaman
     * ini selalu memuat SELURUH karyawan dalam cakupan beserta jadwal sebulannya.
     */
    public function index(Request $request): View
    {
        $month = MonthInput::resolve($request->input('month'));
        $from = $month->copy()->startOfMonth();
        $to = $month->copy()->endOfMonth();
        $branchId = $request->integer('branch_id') ?: null;
        $scope = DataScope::forTeam($request->user());

        $tab = $request->input('tab') === 'assignments' ? 'assignments' : 'roster';
        $perPage = $this->resolvePerPage($request);
        $days = collect(CarbonPeriod::create($from, $to)->toArray());

        $employeeQuery = $this->filtered($scope->employees()->active(), $request);
        $assignmentQuery = $this->assignmentQuery($request, $scope, $from, $to);

        // Dipakai kedua tab: aksi di header (import, generate) dan badge jumlah pada
        // tab yang sedang tidak aktif.
        $shared = [
            'tab' => $tab,
            'days' => $days,
            'month' => $month,
            'prevMonth' => $month->copy()->subMonth()->format('Y-m'),
            'nextMonth' => $month->copy()->addMonth()->format('Y-m'),
            'branches' => $scope->branches(),
            'departments' => $scope->departments(),
            'jobPositions' => JobPosition::query()->where('is_active', true)->orderBy('name')->get(),
            'filters' => [
                'branch_id' => $branchId,
                'department_id' => $request->integer('department_id') ?: null,
                'job_position_id' => $request->integer('job_position_id') ?: null,
                'search' => $request->string('search')->toString(),
            ],
            'branchId' => $branchId,
            'hasNoScope' => $scope->isEmpty(),
            'hasNoTeam' => $scope->hasNoTeam(),
            'shifts' => Shift::query()->where('is_active', true)->orderBy('start_time')->get(),
            'patternCount' => SchedulePattern::query()->visibleTo($request->user())->where('is_active', true)->count(),
            'perPage' => $perPage,
            'perPageOptions' => self::PER_PAGE_OPTIONS,
        ];

        if ($tab === 'assignments') {
            $assignments = $assignmentQuery
                ->with(['employee', 'pattern'])
                // Newest first, matching the precedence the generator applies — the
                // assignment actually governing an overlap is the one listed on top.
                ->orderByDesc('id')
                ->paginate($perPage)
                ->withQueryString();

            return view('attendance.schedules.index', $shared + [
                'assignments' => $assignments,
                'governingAssignments' => $this->governingFor($assignments->items(), $from, $to, $days),
                'employeeCount' => $employeeQuery->count(),
                'assignmentCount' => $assignments->total(),
            ]);
        }

        $employees = $employeeQuery
            ->with(['schedules' => fn ($query) => $query
                ->whereBetween('work_date', [$from->toDateString(), $to->toDateString()])
                ->with('shift'),
            ])
            ->orderBy('full_name')
            ->paginate($perPage)
            ->withQueryString();

        // Karyawan "jam kantor" tidak punya baris roster; isi selnya dari pola jam
        // kantor yang berlaku baginya agar grid menampilkan jadwal mereka (bukan sel
        // kosong). Polanya di-cache per id di dalam service, jadi loop ini tetap satu
        // query per pola yang dipakai — bukan per karyawan.
        foreach ($employees as $employee) {
            if ($employee->follows_office_hours) {
                $employee->setRelation('schedules', $this->officeSchedule->fill($employee, $employee->schedules, $days));
            }
        }

        // National holidays overlay the grid so users see why a day may be off.
        $holidays = Holiday::query()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->where(fn ($query) => $query->where('is_national', true)->orWhere('branch_id', $branchId))
            ->get()
            ->keyBy(fn (Holiday $holiday) => $holiday->date->toDateString());

        return view('attendance.schedules.index', $shared + [
            'employees' => $employees,
            'holidays' => $holidays,
            // Cuti dibatasi ke karyawan yang benar-benar tampil, bukan seluruh cakupan.
            'leaves' => $this->approvedLeaveByDate(
                $from, $to, $branchId, null, $scope,
                $employees->getCollection()->pluck('id')->all(),
            ),
            'employeeCount' => $employees->total(),
            'assignmentCount' => $assignmentQuery->count(),
        ]);
    }

    /**
     * Penugasan pola yang terlihat oleh pengguna pada periode ini, sebelum diurutkan
     * atau dihalamankan. Dipisah agar tab roster bisa memakainya untuk menghitung
     * badge tanpa menyalin ulang aturan cakupan & kepemilikannya.
     */
    private function assignmentQuery(Request $request, DataScope $scope, Carbon $from, Carbon $to): Builder
    {
        return ScheduleAssignment::query()
            ->overlapping($from, $to)
            // Penyaring yang sama persis dengan tab Roster, diterapkan lewat
            // karyawannya. Formulir penyaringnya dipakai bersama kedua tab, jadi
            // dulu ketika hanya lokasi yang diterapkan di sini, memilih divisi atau
            // jabatan membuat kotaknya tetap terisi sementara tabelnya diam-diam
            // menampilkan seluruh penugasan.
            ->whereHas('employee', fn (Builder $query) => $this->filtered($query, $request))
            ->tap(fn ($query) => $scope->constrain($query))
            ->visibleToCreator($request->user());
    }

    /**
     * Penugasan mana yang benar-benar berlaku, untuk baris yang sedang ditampilkan.
     *
     * Presedensi harus dihitung dari SELURUH penugasan milik karyawan yang tampil,
     * bukan hanya baris di halaman ini: penugasan lain milik orang yang sama bisa
     * jatuh di halaman berikutnya, dan menghitung tanpa itu membuat baris ini
     * mengaku "Berlaku" padahal sudah tergantikan.
     *
     * @param  array<int, ScheduleAssignment>  $shown
     * @return array<int, true>
     */
    private function governingFor(array $shown, Carbon $from, Carbon $to, Collection $days): array
    {
        $employeeIds = collect($shown)->pluck('employee_id')->unique()->all();

        if ($employeeIds === []) {
            return [];
        }

        return $this->generator->governingAssignmentIds(
            ScheduleAssignment::query()->overlapping($from, $to)->whereIn('employee_id', $employeeIds)->get(),
            $days,
        );
    }

    private function resolvePerPage(Request $request): int
    {
        $perPage = (int) $request->input('per_page', self::PER_PAGE_OPTIONS[0]);

        return in_array($perPage, self::PER_PAGE_OPTIONS, true) ? $perPage : self::PER_PAGE_OPTIONS[0];
    }

    /**
     * Active employees still missing a schedule. Two modes:
     *  - "no_pattern"  (varian a): never assigned any pola, so the generator has nothing
     *    to build from — a persistent gap.
     *  - "no_schedule" (varian b): no materialized schedule rows for the selected month,
     *    e.g. the roster for that month simply hasn't been generated for them yet.
     */
    public function unscheduled(Request $request): View
    {
        $scope = DataScope::forTeam($request->user());
        $perPage = min(max((int) $request->input('per_page', 25), 10), 100);
        $mode = $this->unscheduledMode($request);
        $month = MonthInput::resolve($request->input('month'));
        $from = $month->copy()->startOfMonth();
        $to = $month->copy()->endOfMonth();

        $query = $this->unscheduledQuery($request, $scope, $mode, $from, $to)
            ->with(['branch', 'departments', 'jobPosition']);

        // In monthly mode, flag who already has a pola covering the month (they just
        // need the roster generated) versus who has no pola at all (needs assigning).
        if ($mode === 'no_schedule') {
            $query->withCount(['scheduleAssignments as covering_count' => fn ($q) => $q->overlapping($from, $to)]);
        }

        $employees = $query->orderBy('full_name')->paginate($perPage)->withQueryString();

        return view('attendance.schedules.unscheduled', [
            'employees' => $employees,
            'mode' => $mode,
            'month' => $month,
            'prevMonth' => $month->copy()->subMonth()->format('Y-m'),
            'nextMonth' => $month->copy()->addMonth()->format('Y-m'),
            'branches' => $scope->branches(),
            'departments' => $scope->departments(),
            'jobPositions' => JobPosition::query()->where('is_active', true)->orderBy('name')->get(),
            'filters' => [
                'branch_id' => $request->integer('branch_id') ?: null,
                'department_id' => $request->integer('department_id') ?: null,
                'job_position_id' => $request->integer('job_position_id') ?: null,
                'search' => $request->string('search')->toString(),
            ],
            'perPage' => $perPage,
            'hasNoScope' => $scope->isEmpty(),
            'hasNoTeam' => $scope->hasNoTeam(),
            // Feeds the bulk-assign bar: only patterns this user is allowed to use.
            'patterns' => SchedulePattern::query()->visibleTo($request->user())->where('is_active', true)->orderBy('name')->get(),
            'defaultStart' => $mode === 'no_schedule' ? $from->toDateString() : now()->startOfMonth()->toDateString(),
        ]);
    }

    /**
     * Export the "belum terjadwal" list (honouring the current mode/month/filters + scope).
     */
    public function unscheduledExport(Request $request): BinaryFileResponse
    {
        $mode = $this->unscheduledMode($request);
        $month = MonthInput::resolve($request->input('month'));

        $filters = $request->only(['branch_id', 'department_id', 'job_position_id', 'search']);
        $filters['mode'] = $mode;
        $filters['month'] = $month->format('Y-m');

        // Monthly mode is period-specific, so name the file after the month; the
        // pattern-gap list is timeless, so date-stamp it instead.
        $suffix = $mode === 'no_schedule' ? $month->format('Y-m') : now()->format('Y-m-d');

        return Excel::download(
            new UnscheduledEmployeesExport($filters, $request->user()),
            "karyawan-belum-terjadwal-{$suffix}.xlsx",
        );
    }

    private function unscheduledMode(Request $request): string
    {
        return $request->input('mode') === 'no_schedule' ? 'no_schedule' : 'no_pattern';
    }

    /**
     * Active, in-scope employees still missing a schedule for the given mode, narrowed
     * by the location / division / position / search filters.
     */
    private function unscheduledQuery(Request $request, DataScope $scope, string $mode, Carbon $from, Carbon $to): Builder
    {
        $branchId = $request->integer('branch_id') ?: null;
        $departmentId = $request->integer('department_id') ?: null;
        $jobPositionId = $request->integer('job_position_id') ?: null;
        $search = $request->string('search')->toString();

        return $scope->employees()
            ->active()
            // Karyawan "jam kantor" memang tidak dijadwalkan — jangan tampilkan sebagai
            // "belum terjadwal", jadwalnya sudah otomatis dari pola jam kantor default.
            ->where('follows_office_hours', false)
            ->when(
                $mode === 'no_schedule',
                fn ($q) => $q->whereDoesntHave('schedules', fn ($s) => $s
                    ->whereBetween('work_date', [$from->toDateString(), $to->toDateString()])),
                fn ($q) => $q->whereDoesntHave('scheduleAssignments'),
            )
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($departmentId, fn ($q) => $q->byDepartment($departmentId))
            ->when($jobPositionId, fn ($q) => $q->where('job_position_id', $jobPositionId))
            ->when($search, fn ($q, $s) => $q->where(fn ($q) => $q
                ->where('full_name', 'like', "%{$s}%")->orWhere('employee_number', 'like', "%{$s}%")));
    }

    /**
     * One employee's month: every day with its shift, the source (pola vs manual),
     * public holidays and approved leave — the "why is this person not working"
     * view that the roster grid can only hint at.
     */
    public function show(Request $request, Employee $employee): View
    {
        DataScope::forTeam($request->user())->authorize($employee);

        $month = MonthInput::resolve($request->input('month'));
        $from = $month->copy()->startOfMonth();
        $to = $month->copy()->endOfMonth();

        $employee->load(['branch', 'department', 'jobPosition']);

        $schedules = $employee->schedules()
            ->whereBetween('work_date', [$from->toDateString(), $to->toDateString()])
            ->with('shift')
            ->get()
            ->keyBy(fn ($schedule) => $schedule->work_date->toDateString());

        $holidays = Holiday::query()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->where(fn ($query) => $query->where('is_national', true)->orWhere('branch_id', $employee->branch_id))
            ->get()
            ->keyBy(fn (Holiday $holiday) => $holiday->date->toDateString());

        $leaves = $this->approvedLeaveByDate($from, $to, null, $employee)->get($employee->id, collect());

        $days = collect(CarbonPeriod::create($from, $to)->toArray());

        // Dihitung SEBELUM sel jam kantor disisipkan: yang bisa dihapus hanyalah baris
        // yang benar-benar tersimpan, bukan turunan pola yang cuma ada saat dibaca.
        $storedGenerated = $schedules->reject(fn (EmployeeSchedule $schedule) => $schedule->isManual())->count();

        // Karyawan "jam kantor": lengkapi hari tanpa baris jadwal dengan pola jam
        // kantor default supaya bulan tampil terisi, bukan "belum dijadwalkan".
        if ($this->officeSchedule->isConfiguredFor($employee)) {
            foreach ($days as $day) {
                $key = $day->toDateString();

                if (! $schedules->has($key) && $synth = $this->officeSchedule->scheduleFor($employee, $day)) {
                    $schedules->put($key, $synth);
                }
            }
        }

        $assignments = $employee->scheduleAssignments()
            ->with('pattern')
            ->orderByDesc('id')
            ->get();

        return view('attendance.schedules.employee', [
            'employee' => $employee,
            'days' => $days,
            'schedules' => $schedules,
            'holidays' => $holidays,
            'leaves' => $leaves,
            'assignments' => $assignments,
            'governingAssignments' => $this->generator->governingAssignmentIds($assignments, $days),
            'month' => $month,
            'prevMonth' => $month->copy()->subMonth()->format('Y-m'),
            'nextMonth' => $month->copy()->addMonth()->format('Y-m'),
            'storedGenerated' => $storedGenerated,
        ]);
    }

    /**
     * Buang jadwal hasil generate milik satu karyawan pada bulan yang sedang dibuka.
     *
     * Menghapus pola atau penugasannya tidak pernah menghapus baris yang terlanjur
     * dibuat — itu disengaja, tapi akibatnya sisa jadwal dari pola yang sudah tidak
     * ada bisa menetap tanpa satu pun cara membersihkannya. Ini caranya, dengan dua
     * hal yang tidak ikut terbawa:
     *
     *   - override manual, karena itu keputusan orang, bukan hasil pola;
     *   - hari yang absensinya sudah tercatat, karena baris jadwal itulah dasar
     *     perhitungannya — mencabutnya membuat hari yang sudah ditutup bisa berubah
     *     hasilnya begitu diproses ulang.
     */
    public function destroyPeriod(Request $request, Employee $employee): RedirectResponse
    {
        DataScope::forTeam($request->user())->authorize($employee);

        $month = MonthInput::resolve($request->input('month'));
        $range = [$month->copy()->startOfMonth()->toDateString(), $month->copy()->endOfMonth()->toDateString()];

        $rows = $employee->schedules()->whereBetween('work_date', $range)->get(['id', 'work_date', 'source']);

        $recorded = $employee->attendances()
            ->whereBetween('work_date', $range)
            ->pluck('work_date')
            ->map(fn ($date) => Carbon::parse($date)->toDateString())
            ->all();

        $manual = $rows->filter(fn (EmployeeSchedule $row) => $row->isManual());
        $locked = $rows->reject(fn (EmployeeSchedule $row) => $row->isManual())
            ->filter(fn (EmployeeSchedule $row) => in_array($row->work_date->toDateString(), $recorded, true));

        $removable = $rows->reject(fn (EmployeeSchedule $row) => $row->isManual())
            ->reject(fn (EmployeeSchedule $row) => in_array($row->work_date->toDateString(), $recorded, true));

        if ($removable->isNotEmpty()) {
            EmployeeSchedule::query()->whereIn('id', $removable->pluck('id'))->delete();
        }

        $summary = $this->periodDeletionSummary($month, $removable->count(), $manual->count(), $locked->count(), $employee, $range);

        ActivityLogger::log(
            'schedules',
            'deleted',
            "Menghapus jadwal {$month->translatedFormat('F Y')} milik {$employee->full_name}: {$summary}",
            $employee,
            ['bulan' => $month->format('Y-m'), 'hari_dihapus' => $removable->count(), 'dilewati' => $locked->count()],
        );

        return redirect()
            ->route('attendance.schedules.show', ['employee' => $employee, 'month' => $month->format('Y-m')])
            ->with('status', $summary);
    }

    /**
     * Laporan apa adanya untuk penghapusan jadwal satu periode. Yang dilewati harus
     * ikut disebut — kalau tidak, pengguna melihat baris yang tersisa dan mengira
     * penghapusannya gagal separuh jalan.
     *
     * @param  array{0: string, 1: string}  $range
     */
    private function periodDeletionSummary(Carbon $month, int $deleted, int $manual, int $locked, Employee $employee, array $range): string
    {
        $label = $month->translatedFormat('F Y');

        $summary = $deleted > 0
            ? "{$deleted} hari jadwal {$label} dihapus."
            : "Tidak ada jadwal {$label} yang bisa dihapus.";

        if ($manual > 0) {
            $summary .= " {$manual} override manual dipertahankan.";
        }

        if ($locked > 0) {
            $summary .= " {$locked} hari dilewati karena absensinya sudah tercatat.";
        }

        // Penugasan yang masih menutupi bulan ini akan menulis ulang jadwalnya pada
        // perpanjangan roster harian berikutnya, jadi katakan sekarang — bukan besok
        // ketika barisnya muncul lagi entah dari mana.
        $stillAssigned = $employee->scheduleAssignments()
            ->overlapping(Carbon::parse($range[0]), Carbon::parse($range[1]))
            ->exists();

        if ($deleted > 0 && $stillAssigned) {
            $summary .= ' Penugasan polanya masih berlaku pada periode ini, jadi jadwalnya akan terbentuk lagi — hentikan dulu penugasannya bila memang tidak diinginkan.';
        }

        return $summary;
    }

    /**
     * Approved leave expanded per day, so a roster cell can answer "is this person
     * off on leave today?" with a single lookup.
     *
     * @param  list<int>|null  $employeeIds  batasi ke karyawan ini saja (baris yang
     *                                       sedang tampil), alih-alih seluruh cakupan
     * @return Collection<int, Collection<string, LeaveRequest>> employee id => date => leave
     */
    private function approvedLeaveByDate(Carbon $from, Carbon $to, ?int $branchId = null, ?Employee $employee = null, ?DataScope $scope = null, ?array $employeeIds = null): Collection
    {
        $leaves = LeaveRequest::query()
            ->where('status', LeaveRequestStatus::Approved->value)
            ->whereDate('start_date', '<=', $to->toDateString())
            ->whereDate('end_date', '>=', $from->toDateString())
            ->when($employee, fn ($query) => $query->where('employee_id', $employee->id))
            ->when($employeeIds !== null, fn ($query) => $query->whereIn('employee_id', $employeeIds))
            ->when($branchId, fn ($query) => $query->whereHas('employee', fn ($q) => $q->where('branch_id', $branchId)))
            ->when($scope, fn ($query) => $scope->constrain($query))
            ->with('leaveType')
            ->get();

        $byEmployee = [];

        foreach ($leaves as $leave) {
            $start = $leave->start_date->greaterThan($from) ? $leave->start_date->copy() : $from->copy();
            $end = $leave->end_date->lessThan($to) ? $leave->end_date->copy() : $to->copy();

            foreach (CarbonPeriod::create($start, $end) as $day) {
                $byEmployee[$leave->employee_id][$day->toDateString()] = $leave;
            }
        }

        return collect($byEmployee)->map(fn (array $days) => collect($days));
    }

    public function create(Request $request): View
    {
        $scope = DataScope::forTeam($request->user());

        // The picker shows each employee's still-running/upcoming assignments so the
        // user can see which periods are already taken before choosing new dates.
        // `departments:id` feeds the client-side division filter (an employee may
        // belong to more than one division).
        $employees = $scope
            ->employees()
            ->active()
            ->with([
                'jobPosition:id,name',
                'department:id,name',
                'departments:id',
                'branch:id,name',
                'scheduleAssignments' => fn ($query) => $query
                    ->where(fn ($q) => $q->whereNull('end_date')->orWhereDate('end_date', '>=', now()->toDateString()))
                    ->with('pattern:id,name,type')
                    ->orderBy('start_date'),
            ])
            ->orderBy('full_name')
            ->get();

        return view('attendance.schedules.assign', [
            'employees' => $employees,
            // Hanya pola milik pengguna (kecuali pemegang attendance.view.all).
            'patterns' => SchedulePattern::query()->visibleTo($request->user())->where('is_active', true)->orderBy('name')->get(),
            'defaultStart' => now()->startOfMonth()->toDateString(),
            'selectedEmployee' => $request->integer('employee_id') ?: null,
            // Opsi filter pemilihan karyawan (lokasi/divisi/jabatan), dibatasi cakupan.
            'branches' => $scope->branches(),
            'departments' => $scope->departments(),
            'jobPositions' => JobPosition::query()->where('is_active', true)->orderBy('name')->get(),
            'hasNoScope' => $scope->isEmpty(),
            'hasNoTeam' => $scope->hasNoTeam(),
        ]);
    }

    public function store(ScheduleAssignmentRequest $request): RedirectResponse
    {
        ['days' => $days, 'start' => $start, 'skipped' => $skipped] = $this->createAssignments($request);

        return redirect()
            ->route('attendance.schedules.index', ['month' => $start->format('Y-m')])
            ->with('status', "Pola ditugaskan & {$days} hari jadwal dibuat.".$this->skippedNote($skipped));
    }

    /**
     * Assign one pattern to everybody ticked on the "belum terjadwal" list. Same
     * rules as the assign page — this is only a faster route to it — but it returns
     * to the list, where the people just handled have dropped off.
     */
    public function bulkAssign(ScheduleAssignmentRequest $request): RedirectResponse
    {
        ['days' => $days, 'employees' => $employees, 'skipped' => $skipped] = $this->createAssignments($request);

        return redirect()
            ->route('attendance.unscheduled.index', $request->only(
                'mode', 'month', 'branch_id', 'department_id', 'job_position_id', 'search', 'per_page',
            ))
            ->with('status', "Pola ditugaskan ke {$employees} karyawan & {$days} hari jadwal dibuat.".$this->skippedNote($skipped));
    }

    /**
     * Days the generator refused to touch are otherwise invisible: the assignment
     * reports success while part of the period keeps its old schedule. Say so.
     */
    private function skippedNote(int $skipped): string
    {
        if ($skipped === 0) {
            return '';
        }

        return " {$skipped} hari dilewati karena sudah diubah manual (override harian atau import Excel) — ubah lewat grid roster bila ingin mengikuti pola baru.";
    }

    /**
     * Create one assignment per selected employee and materialize its roster.
     * Every employee is scope-checked individually, and the pattern must be one the
     * user may use — a hand-crafted request cannot reach past either.
     *
     * @return array{days: int, employees: int, start: Carbon, skipped: int}
     */
    private function createAssignments(ScheduleAssignmentRequest $request): array
    {
        $patternId = $request->integer('schedule_pattern_id');
        $start = Carbon::parse($request->date('start_date'));
        $end = $request->date('end_date') ? Carbon::parse($request->date('end_date')) : null;

        $days = 0;
        $employees = 0;
        $employeeIds = [];
        $scope = DataScope::forTeam($request->user());

        // Hanya boleh menugaskan pola milik sendiri (kecuali pemegang attendance.view.all).
        abort_unless(
            SchedulePattern::query()->visibleTo($request->user())->whereKey($patternId)->exists(),
            403,
        );

        foreach ($request->input('employee_ids', []) as $employeeId) {
            $scope->authorize(Employee::find($employeeId));

            $assignment = ScheduleAssignment::query()->create([
                'employee_id' => $employeeId,
                'schedule_pattern_id' => $patternId,
                'start_date' => $start->toDateString(),
                'end_date' => $end?->toDateString(),
                'created_by' => $request->user()->id,
            ]);

            $days += $this->generator->forAssignment($assignment);
            $employeeIds[] = $employeeId;
            $employees++;
        }

        // Manual days inside the period are exactly the ones the generator skipped:
        // assigning a pattern never creates them, so counting them afterwards is safe.
        $rangeEnd = $end ?? $start->copy()->addDays(ScheduleGenerator::DEFAULT_HORIZON_DAYS);

        $skipped = $employeeIds === [] ? 0 : EmployeeSchedule::query()
            ->whereIn('employee_id', $employeeIds)
            ->whereBetween('work_date', [$start->toDateString(), $rangeEnd->toDateString()])
            ->where('source', ScheduleSource::Manual)
            ->count();

        return ['days' => $days, 'employees' => $employees, 'start' => $start, 'skipped' => $skipped];
    }

    /**
     * (Re)generate the roster for a month across the current scope. Manual overrides
     * are preserved by the generator.
     */
    public function generate(Request $request): RedirectResponse|JsonResponse
    {
        $month = MonthInput::resolve($request->input('month'));
        $from = $month->copy()->startOfMonth();
        $to = $month->copy()->endOfMonth();

        // Same scope and same filters as the grid, so this rebuilds exactly the rows
        // the user is looking at. Regenerating the whole location while a division is
        // filtered would quietly touch hundreds of people they never selected.
        $employees = $this->filtered(
            DataScope::forTeam($request->user())->employees()->active(),
            $request,
        )->get();

        $days = 0;

        foreach ($employees as $employee) {
            $days += $this->generator->forEmployee($employee, $from, $to);
        }

        $status = sprintf(
            'Roster %s diperbarui (%d hari, %d karyawan).',
            $month->translatedFormat('F Y'),
            $days,
            $employees->count(),
        );

        // Ribuan baris jadwal ditulis sekaligus di sini; yang dicatat tindakannya,
        // bukan tiap barisnya (lihat ActivityObserver).
        ActivityLogger::log('schedules', 'generated', $status, null, [
            'bulan' => $month->format('Y-m'),
            'hari_ditulis' => $days,
            'karyawan' => $employees->count(),
        ]);

        if ($request->expectsJson()) {
            return response()->json(['status' => $status, 'days' => $days]);
        }

        return redirect()
            ->route('attendance.schedules.index', $request->only(
                'month', 'branch_id', 'department_id', 'job_position_id', 'search',
            ))
            ->with('status', $status);
    }

    public function override(ScheduleOverrideRequest $request): RedirectResponse|JsonResponse
    {
        $employee = Employee::findOrFail($request->integer('employee_id'));
        DataScope::forTeam($request->user())->authorize($employee);

        $date = Carbon::parse($request->date('work_date'));

        $this->generator->override(
            $employee,
            $date,
            $request->boolean('is_day_off') ? null : $request->integer('shift_id'),
            $request->boolean('is_day_off'),
            $request->string('note')->toString() ?: null,
            $request->boolean('is_wfh'),
        );

        // AJAX: kirim balik sel yang sudah diperbarui (dirender dari partial yang sama
        // dengan grid) supaya halaman tidak perlu dimuat ulang.
        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'Jadwal harian diperbarui.',
                'cell' => view('attendance.schedules._cell', [
                    'employee' => $employee,
                    'day' => $date,
                    'sched' => $employee->schedules()->whereDate('work_date', $date->toDateString())->with('shift')->first(),
                    'leave' => $employee->leaveRequests()->approvedOn($date->toDateString())->with('leaveType')->first(),
                ])->render(),
            ]);
        }

        return redirect()
            ->route('attendance.schedules.index', ['month' => $date->format('Y-m'), 'branch_id' => $request->integer('branch_id') ?: null])
            ->with('status', 'Jadwal harian diperbarui.');
    }

    public function destroyAssignment(Request $request, ScheduleAssignment $assignment): RedirectResponse|JsonResponse
    {
        DataScope::forTeam($request->user())->authorize($assignment->employee);
        abort_unless(
            $request->user()->can(User::SCOPE_BYPASS_ATTENDANCE) || $assignment->created_by === $request->user()->id,
            403,
        );

        $month = Carbon::parse($assignment->start_date)->format('Y-m');
        $assignment->delete();

        $status = 'Penugasan pola dihapus. Jadwal yang sudah dibuat tetap tersimpan.';

        if ($request->expectsJson()) {
            return response()->json(['status' => $status]);
        }

        return redirect()
            ->route('attendance.schedules.index', ['month' => $month])
            ->with('status', $status);
    }

    /**
     * The roster template for a month, pre-filled with the employees currently in
     * view (same filters as the grid) and their existing schedule, so the file can
     * be edited and sent straight back.
     */
    public function importTemplate(Request $request): BinaryFileResponse
    {
        $month = MonthInput::resolve($request->input('month'));

        // Isi template mengikuti apa yang tampil di roster: bawahan si pengunduh saja,
        // kecuali superadmin. Aturannya tinggal di DataScope::forTeam(), tidak diulang
        // di sini — dulu penyaring bawahannya ditulis khusus di tempat ini dan itu
        // membuat template dan layarnya bisa menyimpang satu sama lain.
        $scope = DataScope::forTeam($request->user());

        $employees = $this->filtered($scope->employees()->active(), $request)
            ->orderBy('full_name')
            ->get();

        return Excel::download(
            new ScheduleTemplateExport(CarbonImmutable::parse($month)->startOfMonth(), $employees),
            'template-jadwal-'.$month->format('Y-m').'.xlsx',
        );
    }

    /**
     * Import a filled-in roster matrix. All-or-nothing, exactly like the employee
     * import: a single bad cell cancels the whole file and the problems are flashed
     * back for the modal (plus a downloadable, annotated copy of the upload).
     */
    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
        ], [], ['file' => 'file Excel']);

        $scope = DataScope::forTeam($request->user());

        abort_if($scope->isEmpty(), 403);

        $import = new ScheduleMatrixImport($request->user());

        try {
            Excel::import($import, $request->file('file'));
        } catch (\Throwable $exception) {
            report($exception);

            return back()->with('import_errors', ['Gagal membaca file. Pastikan file sesuai template. ('.$exception->getMessage().')']);
        }

        if ($import->errors() !== []) {
            return back()
                ->with('import_errors', $import->errors())
                ->with('import_error_token', ImportErrorStore::put($request->file('file'), $import->rowErrors()));
        }

        $period = $import->period();

        $status = sprintf(
            'Jadwal %s diimpor: %d hari untuk %d karyawan. Absensi pada tanggal yang sudah lewat ikut dihitung ulang mengikuti jadwal baru.',
            $period?->translatedFormat('F Y') ?? '',
            $import->importedDays(),
            $import->importedEmployees(),
        );

        return redirect()
            ->route('attendance.schedules.index', ['month' => $period?->format('Y-m')] + $request->only('branch_id', 'department_id', 'job_position_id', 'search'))
            ->with('status', $status);
    }

    /**
     * The just-uploaded roster returned with the offending cells highlighted.
     */
    public function importErrors(string $token): BinaryFileResponse
    {
        return ImportErrorStore::download($token, ScheduleMatrixImport::HEADER_ROW);
    }

    /**
     * The grid's own filters (lokasi / divisi / jabatan / pencarian), so the
     * template covers exactly the people the user is looking at.
     */
    private function filtered(Builder $query, Request $request): Builder
    {
        return $query
            ->when($request->integer('branch_id') ?: null, fn ($q, $id) => $q->where('branch_id', $id))
            ->when($request->integer('department_id') ?: null, fn ($q, $id) => $q->byDepartment($id))
            ->when($request->integer('job_position_id') ?: null, fn ($q, $id) => $q->where('job_position_id', $id))
            ->when($request->string('search')->toString() ?: null, fn ($q, $s) => $q->where(fn ($inner) => $inner
                ->where('full_name', 'like', "%{$s}%")
                ->orWhere('employee_number', 'like', "%{$s}%")));
    }
}
