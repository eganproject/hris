<?php

use App\Enums\AttendanceStatus;
use App\Enums\ScheduleSource;
use App\Exports\ScheduleTemplateExport;
use App\Imports\ScheduleMatrixImport;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\Shift;
use App\Models\User;
use App\Services\AttendanceResolver;
use App\Services\ScheduleGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/**
 * A month safely in the past, so every day it contains is eligible for the
 * attendance re-resolution the importer performs.
 */
function rosterMonth(): Carbon
{
    return Carbon::now()->subMonth()->startOfMonth();
}

function rosterShift(string $code = 'P', string $start = '08:00', string $end = '17:00'): Shift
{
    return Shift::query()->create([
        'code' => $code,
        'name' => 'Shift '.$code,
        'start_time' => $start,
        'end_time' => $end,
        'break_minutes' => 60,
        'late_tolerance_minutes' => 10,
        'early_leave_tolerance_minutes' => 10,
        'is_active' => true,
    ]);
}

function rosterEmployee(string $name = 'Budi Santoso'): Employee
{
    return Employee::query()->create([
        'full_name' => $name,
        'employment_status' => 'active',
    ]);
}

/**
 * One employee line of the matrix, padded out to the full month.
 *
 * @param  array<int, string>  $cells  day of month => cell value
 * @return list<string|int>
 */
function rosterRow(Employee $employee, array $cells, int $daysInMonth): array
{
    $row = [$employee->employee_number, $employee->full_name];

    for ($day = 1; $day <= $daysInMonth; $day++) {
        $row[] = $cells[$day] ?? '';
    }

    return $row;
}

/**
 * The sheet as ScheduleMatrixImport receives it: period row, header row, data.
 *
 * @param  list<list<string|int>>  $rows
 */
function runRosterImport(array $rows, ?string $period = null, ?int $daysInMonth = null, ?array $headerDays = null): ScheduleMatrixImport
{
    $month = rosterMonth();
    $period ??= $month->format('Y-m');
    $daysInMonth ??= $month->daysInMonth;

    $sheet = [
        ['Periode', $period],
        [
            ScheduleMatrixImport::COLUMN_EMPLOYEE_NUMBER,
            ScheduleMatrixImport::COLUMN_EMPLOYEE_NAME,
            ...($headerDays ?? range(1, $daysInMonth)),
        ],
        ...$rows,
    ];

    $import = new ScheduleMatrixImport;
    $import->collection(collect($sheet)->map(fn (array $row) => collect($row)));

    return $import;
}

/**
 * An HR user holding every attendance/schedule permission, plus the bypass that
 * puts the whole workforce inside their data scope.
 */
function rosterImporter(): User
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $permissions = [...attendanceMenuPermissions(), 'attendance.view.all'];

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $user = User::factory()->create();
    $user->givePermissionTo($permissions);

    return $user;
}

test('the matrix writes shifts and days off as manual overrides', function () {
    $shift = rosterShift();
    $employee = rosterEmployee();
    $month = rosterMonth();

    $import = runRosterImport([
        rosterRow($employee, [1 => 'P', 2 => 'P', 3 => 'L'], $month->daysInMonth),
    ]);

    expect($import->errors())->toBe([])
        ->and($import->importedEmployees())->toBe(1)
        ->and($import->importedDays())->toBe(3);

    $schedules = EmployeeSchedule::query()
        ->where('employee_id', $employee->id)
        ->orderBy('work_date')
        ->get();

    expect($schedules)->toHaveCount(3)
        ->and($schedules[0]->shift_id)->toBe($shift->id)
        ->and($schedules[0]->is_day_off)->toBeFalse()
        // Manual, so the nightly rolling generator will never overwrite it.
        ->and($schedules[0]->source)->toBe(ScheduleSource::Manual)
        ->and($schedules[2]->is_day_off)->toBeTrue()
        ->and($schedules[2]->shift_id)->toBeNull();
});

test('an empty cell leaves the existing schedule untouched', function () {
    $shift = rosterShift();
    $employee = rosterEmployee();
    $month = rosterMonth();

    // A day already rostered by the generator, which the file does not mention.
    app(ScheduleGenerator::class)->override($employee, $month->copy()->setDay(2), $shift->id, false);

    runRosterImport([
        rosterRow($employee, [1 => 'L'], $month->daysInMonth),
    ]);

    $untouched = EmployeeSchedule::query()
        ->where('employee_id', $employee->id)
        ->whereDate('work_date', $month->copy()->setDay(2)->toDateString())
        ->firstOrFail();

    expect($untouched->shift_id)->toBe($shift->id)
        ->and($untouched->is_day_off)->toBeFalse();
});

test('a "/WFH" suffix keeps the shift and marks the day as work from home', function () {
    $shift = rosterShift();
    $employee = rosterEmployee();
    $month = rosterMonth();

    $import = runRosterImport([
        rosterRow($employee, [1 => 'P/WFH'], $month->daysInMonth),
    ]);

    expect($import->errors())->toBe([]);

    $schedule = EmployeeSchedule::query()->where('employee_id', $employee->id)->firstOrFail();

    expect($schedule->shift_id)->toBe($shift->id)
        ->and($schedule->is_wfh)->toBeTrue()
        ->and($schedule->is_day_off)->toBeFalse();
});

test('an unknown shift code cancels the whole file', function () {
    rosterShift();
    $employee = rosterEmployee();
    $month = rosterMonth();

    $import = runRosterImport([
        rosterRow($employee, [1 => 'P', 2 => 'ZZ'], $month->daysInMonth),
    ]);

    expect($import->errors())->toHaveCount(1)
        ->and($import->errors()[0])->toContain('kode shift "ZZ" tidak dikenali')
        // All-or-nothing: the valid day 1 must not have been written either.
        ->and(EmployeeSchedule::query()->count())->toBe(0);
});

test('the day column is reported so the failing cell can be highlighted', function () {
    rosterShift();
    $employee = rosterEmployee();
    $month = rosterMonth();

    $import = runRosterImport([
        rosterRow($employee, [4 => 'ZZ'], $month->daysInMonth),
    ]);

    expect($import->rowErrors()[0]['row'])->toBe(ScheduleMatrixImport::FIRST_DATA_ROW)
        ->and($import->rowErrors()[0]['column'])->toBe('4');
});

test('an unreadable period is rejected before anything is written', function () {
    rosterShift();
    $employee = rosterEmployee();
    $month = rosterMonth();

    $import = runRosterImport(
        [rosterRow($employee, [1 => 'P'], $month->daysInMonth)],
        period: 'bukan-bulan',
    );

    expect($import->errors())->toHaveCount(1)
        ->and($import->errors()[0])->toContain('bukan bulan yang valid')
        ->and(EmployeeSchedule::query()->count())->toBe(0);
});

test('a day column outside the month is rejected rather than silently dropped', function () {
    rosterShift();
    $employee = rosterEmployee();

    // February 2026 has 28 days; a leftover "30" column must not be accepted.
    $import = runRosterImport(
        [[$employee->employee_number, $employee->full_name, 'P', 'P']],
        period: '2026-02',
        headerDays: [1, 30],
    );

    expect($import->errors())->toHaveCount(1)
        ->and($import->errors()[0])->toContain('Kolom tanggal "30" tidak ada pada')
        ->and(EmployeeSchedule::query()->count())->toBe(0);
});

test('the same employee twice in one file is rejected', function () {
    rosterShift();
    $employee = rosterEmployee();
    $month = rosterMonth();

    $import = runRosterImport([
        rosterRow($employee, [1 => 'P'], $month->daysInMonth),
        rosterRow($employee, [2 => 'P'], $month->daysInMonth),
    ]);

    expect($import->errors())->toHaveCount(1)
        ->and($import->errors()[0])->toContain('muncul lebih dari sekali');
});

test('an unknown employee number is rejected', function () {
    rosterShift();
    rosterEmployee(); // at least one active employee exists, so the file itself is at fault

    $import = runRosterImport([
        ['TIDAK-ADA-001', 'Orang Asing', 'P'],
    ]);

    expect($import->errors())->toHaveCount(1)
        ->and($import->errors()[0])->toContain('tidak ditemukan');
});

test('a past day already closed out as Libur becomes Alfa once the shift is imported', function () {
    $shift = rosterShift();
    $employee = rosterEmployee();
    $month = rosterMonth();
    $date = $month->copy()->setDay(3);

    // The day was processed while nobody knew the employee was meant to work:
    // no schedule, no punch, so the resolver recorded it as a rest day.
    $before = app(AttendanceResolver::class)->resolve($employee, $date);
    expect($before->status)->toBe(AttendanceStatus::DayOff);

    $import = runRosterImport([
        rosterRow($employee, [3 => 'P'], $month->daysInMonth),
    ]);

    expect($import->errors())->toBe([]);

    $after = Attendance::query()
        ->where('employee_id', $employee->id)
        ->whereDate('work_date', $date->toDateString())
        ->firstOrFail();

    expect($after->status)->toBe(AttendanceStatus::Absent)
        ->and($after->shift_id)->toBe($shift->id);
});

test('the generated template is a file the importer can read straight back', function () {
    $shift = rosterShift();
    $employee = rosterEmployee();
    $month = rosterMonth();

    // Give the employee a roster the template will pre-fill.
    $generator = app(ScheduleGenerator::class);
    $generator->override($employee, $month->copy()->setDay(1), $shift->id, false);
    $generator->override($employee, $month->copy()->setDay(2), null, true);
    $generator->override($employee, $month->copy()->setDay(3), $shift->id, false, null, true);

    $path = tempnam(sys_get_temp_dir(), 'roster-').'.xlsx';

    file_put_contents($path, Excel::raw(
        new ScheduleTemplateExport(
            CarbonImmutable::parse($month)->startOfMonth(),
            Employee::query()->whereKey($employee->id)->get(),
        ),
        ExcelWriter::XLSX,
    ));

    // Wipe the roster so the re-import has to rebuild it from the file alone.
    EmployeeSchedule::query()->delete();

    $import = new ScheduleMatrixImport;
    Excel::import($import, $path);
    unlink($path);

    expect($import->errors())->toBe([])
        // The period survived as text rather than being coerced into a date.
        ->and($import->period()?->format('Y-m'))->toBe($month->format('Y-m'))
        ->and($import->importedDays())->toBe(3);

    $schedules = EmployeeSchedule::query()->orderBy('work_date')->get();

    expect($schedules)->toHaveCount(3)
        ->and($schedules[0]->shift_id)->toBe($shift->id)
        ->and($schedules[1]->is_day_off)->toBeTrue()
        ->and($schedules[2]->is_wfh)->toBeTrue();
});

test('a day with no attendance row yet is left for the nightly job', function () {
    rosterShift();
    $employee = rosterEmployee();
    $month = rosterMonth();

    $import = runRosterImport([
        rosterRow($employee, [3 => 'P'], $month->daysInMonth),
    ]);

    expect($import->errors())->toBe([])
        ->and(Attendance::query()->count())->toBe(0);
});

test('the template route returns a spreadsheet for the requested month', function () {
    rosterShift();
    rosterEmployee();
    $month = rosterMonth();

    $this->actingAs(rosterImporter())
        ->get(route('attendance.schedules.import.template', ['month' => $month->format('Y-m')]))
        ->assertOk()
        ->assertDownload('template-jadwal-'.$month->format('Y-m').'.xlsx');
});

test('uploading a filled roster writes the schedule and reports what changed', function () {
    $shift = rosterShift();
    $employee = rosterEmployee();
    $month = rosterMonth();

    $path = tempnam(sys_get_temp_dir(), 'roster-').'.xlsx';
    file_put_contents($path, Excel::raw(new class($month, $shift, $employee) implements FromArray
    {
        public function __construct(private $month, private $shift, private $employee) {}

        public function array(): array
        {
            return [
                ['Periode', $this->month->format('Y-m')],
                ['Nomor Karyawan', 'Nama Lengkap', 1, 2],
                [$this->employee->employee_number, $this->employee->full_name, $this->shift->code, 'L'],
            ];
        }
    }, ExcelWriter::XLSX));

    $response = $this->actingAs(rosterImporter())->post(route('attendance.schedules.import'), [
        'file' => new UploadedFile($path, 'roster.xlsx', null, null, true),
    ]);

    $response->assertRedirect()->assertSessionHas('status');

    expect(session('status'))->toContain('2 hari untuk 1 karyawan')
        ->and(EmployeeSchedule::query()->where('employee_id', $employee->id)->count())->toBe(2);
});

test('a rejected upload keeps the schedule untouched and offers the error report', function () {
    rosterShift();
    $employee = rosterEmployee();
    $month = rosterMonth();

    $path = tempnam(sys_get_temp_dir(), 'roster-').'.xlsx';
    file_put_contents($path, Excel::raw(new class($month, $employee) implements FromArray
    {
        public function __construct(private $month, private $employee) {}

        public function array(): array
        {
            return [
                ['Periode', $this->month->format('Y-m')],
                ['Nomor Karyawan', 'Nama Lengkap', 1],
                [$this->employee->employee_number, $this->employee->full_name, 'TIDAK-ADA'],
            ];
        }
    }, ExcelWriter::XLSX));

    $this->actingAs(rosterImporter())
        ->post(route('attendance.schedules.import'), [
            'file' => new UploadedFile($path, 'roster.xlsx', null, null, true),
        ])
        ->assertRedirect()
        ->assertSessionHas('import_errors')
        ->assertSessionHas('import_error_token');

    expect(EmployeeSchedule::query()->count())->toBe(0);
});

test('importing a roster requires the schedules.update permission', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('attendance.schedules.import.template'))
        ->assertForbidden();
});
