<?php

use App\Enums\AttendanceStatus;
use App\Enums\SchedulePatternType;
use App\Models\Employee;
use App\Models\SchedulePattern;
use App\Models\Setting;
use App\Models\Shift;
use App\Models\User;
use App\Services\AttendanceResolver;
use App\Services\DefaultOfficeSchedule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/**
 * Pola jam kantor ganda: tiap karyawan boleh menunjuk polanya sendiri, dan yang
 * tidak menunjuk apa-apa tetap ikut pola default global — perilaku sebelum fitur
 * ini ada. 2026-07-20 adalah hari Senin dan dipakai sebagai hari kerja acuan.
 */
const OFFICE_MONDAY = '2026-07-20';

function officeShiftNamed(string $code, string $start, string $end): Shift
{
    return Shift::query()->create([
        'code' => $code, 'name' => "Shift {$code}",
        'start_time' => $start, 'end_time' => $end,
        'break_minutes' => 60, 'late_tolerance_minutes' => 10, 'is_active' => true,
    ]);
}

/** Bekerja Senin–Sabtu pada shift yang diberikan; Minggu (slot 0) tanpa shift = libur. */
function officePatternFor(string $code, Shift $shift, bool $registered = true): SchedulePattern
{
    $pattern = SchedulePattern::query()->create([
        'code' => $code, 'name' => "Jam Kantor {$code}", 'type' => SchedulePatternType::FixedWeekly,
        'cycle_length' => 7, 'is_active' => true, 'is_office_pattern' => $registered,
    ]);

    foreach (range(1, 6) as $index) {
        $pattern->days()->create(['day_index' => $index, 'shift_id' => $shift->id]);
    }

    return $pattern;
}

function officeHoursEmployee(string $name, ?SchedulePattern $pattern = null): Employee
{
    return Employee::query()->create([
        'full_name' => $name, 'employment_status' => 'active',
        'follows_office_hours' => true, 'office_pattern_id' => $pattern?->id,
    ]);
}

/** Pola default global + shift-nya, dikembalikan sebagai [shift, pola]. */
function globalOfficeDefault(): array
{
    $shift = officeShiftNamed('PAGI', '08:00', '17:00');
    $pattern = officePatternFor('HQ', $shift);

    Setting::set(DefaultOfficeSchedule::SETTING_KEY, (string) $pattern->id);

    return [$shift, $pattern];
}

test('an employee with their own office pattern uses it instead of the global default', function () {
    [$defaultShift] = globalOfficeDefault();
    $eveningShift = officeShiftNamed('SIANG', '13:00', '22:00');
    $evening = officePatternFor('CAB', $eveningShift);

    $result = app(AttendanceResolver::class)
        ->compute(officeHoursEmployee('Cabang', $evening), Carbon::parse(OFFICE_MONDAY), '13:00', '22:00');

    expect($result['shift_id'])->toBe($eveningShift->id)
        ->and($result['shift_id'])->not->toBe($defaultShift->id)
        ->and($result['status'])->toBe(AttendanceStatus::Present);
});

test('an office-hours employee without a pattern of their own falls back to the global default', function () {
    [$defaultShift] = globalOfficeDefault();
    officePatternFor('CAB', officeShiftNamed('SIANG', '13:00', '22:00'));

    $result = app(AttendanceResolver::class)
        ->compute(officeHoursEmployee('Pusat'), Carbon::parse(OFFICE_MONDAY), '08:00', '17:00');

    expect($result['shift_id'])->toBe($defaultShift->id)
        ->and($result['status'])->toBe(AttendanceStatus::Present);
});

test('two office-hours employees on different patterns resolve differently on the same day', function () {
    [$morningShift] = globalOfficeDefault();
    $eveningShift = officeShiftNamed('SIANG', '13:00', '22:00');

    $pusat = officeHoursEmployee('Pusat');
    $cabang = officeHoursEmployee('Cabang', officePatternFor('CAB', $eveningShift));

    $resolver = app(AttendanceResolver::class);
    $date = Carbon::parse(OFFICE_MONDAY);

    // Datang 13:00: tepat waktu bagi shift siang, telat 5 jam bagi shift pagi.
    expect($resolver->compute($cabang, $date, '13:00', '22:00')['status'])->toBe(AttendanceStatus::Present)
        ->and($resolver->compute($pusat, $date, '13:00', '22:00')['status'])->toBe(AttendanceStatus::Late)
        ->and($resolver->compute($pusat, $date, '13:00', '22:00')['shift_id'])->toBe($morningShift->id);
});

test('a per-employee pattern keeps its own day off', function () {
    globalOfficeDefault();
    $employee = officeHoursEmployee('Cabang', officePatternFor('CAB', officeShiftNamed('SIANG', '13:00', '22:00')));

    // 2026-07-19 adalah Minggu — tidak ada slot di pola, jadi libur.
    $result = app(AttendanceResolver::class)->compute($employee, Carbon::parse('2026-07-19'));

    expect($result['status'])->toBe(AttendanceStatus::DayOff)
        ->and($result['shift_id'])->toBeNull();
});

test('archiving a pattern leaves the schedule of everyone still on it untouched', function () {
    globalOfficeDefault();
    $eveningShift = officeShiftNamed('SIANG', '13:00', '22:00');
    $cabang = officePatternFor('CAB', $eveningShift);
    $employee = officeHoursEmployee('Cabang', $cabang);

    // Menghapus pola kini berarti mengarsipkannya. Dulu barisnya benar-benar dibuang,
    // dan FK nullOnDelete diam-diam melempar orang ini ke pola default global —
    // jadwal DAN absensinya berubah tanpa ada yang memintanya.
    $cabang->delete();
    $employee->refresh();

    expect($employee->office_pattern_id)->toBe($cabang->id);

    $result = app(AttendanceResolver::class)->compute($employee, Carbon::parse(OFFICE_MONDAY), '13:00', '22:00');

    expect($result['shift_id'])->toBe($eveningShift->id);
});

test('a pattern still in use keeps working after it is unregistered as an office pattern', function () {
    globalOfficeDefault();
    $eveningShift = officeShiftNamed('SIANG', '13:00', '22:00');
    $cabang = officePatternFor('CAB', $eveningShift);
    $employee = officeHoursEmployee('Cabang', $cabang);

    // Mencabut pendaftaran hanya menghilangkannya dari pilihan — jadwal karyawan yang
    // sudah memakainya tidak boleh berubah diam-diam.
    $cabang->update(['is_office_pattern' => false]);

    $result = app(AttendanceResolver::class)->compute($employee->fresh(), Carbon::parse(OFFICE_MONDAY), '13:00', '22:00');

    expect($result['shift_id'])->toBe($eveningShift->id);
});

test('the roster grid renders both patterns without querying once per employee', function () {
    [$morningShift] = globalOfficeDefault();
    $eveningShift = officeShiftNamed('SIANG', '13:00', '22:00');
    $evening = officePatternFor('CAB', $eveningShift);

    foreach (range(1, 12) as $i) {
        officeHoursEmployee("Pusat {$i}");
        officeHoursEmployee("Cabang {$i}", $evening);
    }

    $user = scheduleManager();

    DB::enableQueryLog();

    $response = $this->actingAs($user)->get(route('attendance.schedules.index', ['month' => '2026-07']));

    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    $response->assertOk()->assertSee($morningShift->code)->assertSee($eveningShift->code);

    // Dua pola dipakai 24 karyawan; aktual 22 query. Tanpa cache per-pola di
    // DefaultOfficeSchedule ini akan tumbuh sebanding jumlah karyawan (~90).
    expect($queries)->toBeLessThan(30);
});

test('an office-hours employee sees their month on Jadwal Saya instead of "belum dijadwalkan"', function () {
    [$morningShift] = globalOfficeDefault();
    $eveningShift = officeShiftNamed('SIANG', '13:00', '22:00');

    $employee = officeHoursEmployee('Cabang', officePatternFor('CAB', $eveningShift));
    $user = User::factory()->create();
    $employee->update(['user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('my-roster.index', ['month' => now()->format('Y-m')]))
        ->assertOk()
        ->assertSee($eveningShift->code)
        ->assertDontSee($morningShift->code);
});

test('the employee form only offers patterns registered as office patterns', function () {
    globalOfficeDefault();
    $registered = officePatternFor('CAB', officeShiftNamed('SIANG', '13:00', '22:00'));
    $unregistered = officePatternFor('GDG', officeShiftNamed('MALAM', '22:00', '06:00'), registered: false);

    $user = employeeManager();
    hrMasterData();

    $this->actingAs($user)
        ->get('/employees/create')
        ->assertOk()
        ->assertSee($registered->name)
        ->assertDontSee($unregistered->name);
});

test('creating an employee stores the chosen office pattern', function () {
    globalOfficeDefault();
    $cabang = officePatternFor('CAB', officeShiftNamed('SIANG', '13:00', '22:00'));

    $user = employeeManager();
    ['branch' => $branch, 'department' => $department, 'position' => $position] = hrMasterData();

    $this->actingAs($user)
        ->post('/employees', [
            'branch_id' => $branch->id,
            'department_id' => $department->id,
            'job_position_id' => $position->id,
            'machine_pins' => [['device_id' => null, 'machine_user_id' => '1']],
            'full_name' => 'Kantoran Cabang',
            'join_date' => '2026-07-05',
            'employment_status' => 'active',
            'follows_office_hours' => '1',
            'office_pattern_id' => $cabang->id,
            'contract_number' => 'CTR-OFC-1',
            'contract_type' => 'PKWT',
            'contract_start_date' => '2026-07-05',
            'contract_end_date' => '2027-07-04',
            'contract_status' => 'active',
        ])
        ->assertRedirect('/employees');

    $employee = Employee::query()->where('full_name', 'Kantoran Cabang')->firstOrFail();

    expect($employee->follows_office_hours)->toBeTrue()
        ->and($employee->office_pattern_id)->toBe($cabang->id);
});

test('a pattern chosen while the office-hours toggle is off is not stored', function () {
    globalOfficeDefault();
    $cabang = officePatternFor('CAB', officeShiftNamed('SIANG', '13:00', '22:00'));

    $user = employeeManager();
    ['branch' => $branch, 'department' => $department, 'position' => $position] = hrMasterData();

    // Toggle mati tapi dropdown-nya masih mengirim nilai: polanya harus dibuang, bukan
    // disimpan diam-diam dan hidup lagi saat toggle dinyalakan nanti.
    $this->actingAs($user)
        ->post('/employees', [
            'branch_id' => $branch->id,
            'department_id' => $department->id,
            'job_position_id' => $position->id,
            'machine_pins' => [['device_id' => null, 'machine_user_id' => '2']],
            'full_name' => 'Pekerja Shift',
            'join_date' => '2026-07-05',
            'employment_status' => 'active',
            'follows_office_hours' => '0',
            'office_pattern_id' => $cabang->id,
            'contract_number' => 'CTR-OFC-2',
            'contract_type' => 'PKWT',
            'contract_start_date' => '2026-07-05',
            'contract_end_date' => '2027-07-04',
            'contract_status' => 'active',
        ])
        ->assertRedirect('/employees');

    $employee = Employee::query()->where('full_name', 'Pekerja Shift')->firstOrFail();

    expect($employee->follows_office_hours)->toBeFalse()
        ->and($employee->office_pattern_id)->toBeNull();
});

test('the bulk action stores the chosen pattern and clears it when unmarking', function () {
    globalOfficeDefault();
    $cabang = officePatternFor('CAB', officeShiftNamed('SIANG', '13:00', '22:00'));

    $user = employeeManager();
    ['branch' => $branch, 'department' => $department, 'position' => $position] = hrMasterData();

    $make = fn (string $name) => Employee::query()->create([
        'branch_id' => $branch->id, 'department_id' => $department->id,
        'job_position_id' => $position->id, 'full_name' => $name,
        'join_date' => now()->subMonth()->toDateString(), 'employment_status' => 'active',
    ]);

    $a = $make('Kantoran Satu');
    $b = $make('Kantoran Dua');

    $this->actingAs($user)
        ->post('/employees/bulk/office-hours', [
            'employee_ids' => [$a->id, $b->id], 'follows' => 1, 'office_pattern_id' => $cabang->id,
        ])
        ->assertRedirect('/employees');

    expect($a->fresh()->office_pattern_id)->toBe($cabang->id)
        ->and($b->fresh()->office_pattern_id)->toBe($cabang->id);

    // Mengembalikan ke penjadwalan manual harus ikut mengosongkan polanya.
    $this->actingAs($user)
        ->post('/employees/bulk/office-hours', ['employee_ids' => [$a->id], 'follows' => 0])
        ->assertRedirect('/employees');

    expect($a->fresh()->follows_office_hours)->toBeFalse()
        ->and($a->fresh()->office_pattern_id)->toBeNull()
        ->and($b->fresh()->office_pattern_id)->toBe($cabang->id);
});

test('the bulk action refuses a pattern that is not registered as an office pattern', function () {
    globalOfficeDefault();
    $unregistered = officePatternFor('GDG', officeShiftNamed('MALAM', '22:00', '06:00'), registered: false);

    $user = employeeManager();
    ['branch' => $branch, 'department' => $department, 'position' => $position] = hrMasterData();

    $employee = Employee::query()->create([
        'branch_id' => $branch->id, 'department_id' => $department->id,
        'job_position_id' => $position->id, 'full_name' => 'Kantoran',
        'join_date' => now()->subMonth()->toDateString(), 'employment_status' => 'active',
    ]);

    $this->actingAs($user)
        ->post('/employees/bulk/office-hours', [
            'employee_ids' => [$employee->id], 'follows' => 1, 'office_pattern_id' => $unregistered->id,
        ])
        ->assertSessionHas('bulk_error');

    expect($employee->fresh()->follows_office_hours)->toBeFalse()
        ->and($employee->fresh()->office_pattern_id)->toBeNull();
});

/** Pengguna yang boleh membuka & menyimpan Pengaturan. */
function settingsAdmin(): User
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    foreach (['settings.view', 'settings.update'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $user = User::factory()->create();
    $user->givePermissionTo(['settings.view', 'settings.update']);

    return $user;
}

test('the settings page lists active patterns and ticks the registered ones', function () {
    [, $default] = globalOfficeDefault();
    $spare = officePatternFor('GDG', officeShiftNamed('MALAM', '22:00', '06:00'), registered: false);

    $this->actingAs(settingsAdmin())
        ->get(route('settings.index'))
        ->assertOk()
        ->assertSee($default->name)
        ->assertSee($spare->name);

    expect($default->fresh()->is_office_pattern)->toBeTrue()
        ->and($spare->fresh()->is_office_pattern)->toBeFalse();
});

test('saving the settings registers the ticked patterns and unregisters the rest', function () {
    [, $default] = globalOfficeDefault();
    $cabang = officePatternFor('CAB', officeShiftNamed('SIANG', '13:00', '22:00'), registered: false);

    $this->actingAs(settingsAdmin())
        ->put(route('settings.update'), [
            'roster_autogenerate' => '1',
            'office_pattern_ids' => [$cabang->id],
            'default_office_pattern_id' => $cabang->id,
        ])
        ->assertRedirect();

    // Yang dicentang jadi kandidat, yang tidak dicabut — termasuk bekas default.
    expect($cabang->fresh()->is_office_pattern)->toBeTrue()
        ->and($default->fresh()->is_office_pattern)->toBeFalse()
        ->and(Setting::get(DefaultOfficeSchedule::SETTING_KEY))->toBe((string) $cabang->id);
});

test('the default pattern must be one of the ticked patterns', function () {
    [, $default] = globalOfficeDefault();
    $cabang = officePatternFor('CAB', officeShiftNamed('SIANG', '13:00', '22:00'), registered: false);

    $this->actingAs(settingsAdmin())
        ->put(route('settings.update'), [
            'roster_autogenerate' => '1',
            'office_pattern_ids' => [$cabang->id],
            // Tidak dicentang, jadi tidak boleh jadi default.
            'default_office_pattern_id' => $default->id,
        ])
        ->assertSessionHasErrors('default_office_pattern_id');

    // Ditolak seluruhnya: pendaftaran pola pun tidak ikut berubah.
    expect($cabang->fresh()->is_office_pattern)->toBeFalse()
        ->and(Setting::get(DefaultOfficeSchedule::SETTING_KEY))->toBe((string) $default->id);
});

test('clearing every pattern turns the office-hours feature off', function () {
    [$defaultShift] = globalOfficeDefault();
    $employee = officeHoursEmployee('Kantoran');

    $this->actingAs(settingsAdmin())
        ->put(route('settings.update'), ['roster_autogenerate' => '1'])
        ->assertRedirect();

    // Tanpa pola default, karyawan jam kantor kembali ke perilaku "tidak dijadwalkan".
    $result = app(AttendanceResolver::class)->compute($employee, Carbon::parse(OFFICE_MONDAY), '08:25', '17:00');

    expect($result['shift_id'])->toBeNull()
        ->and($result['shift_id'])->not->toBe($defaultShift->id)
        ->and($result['status'])->toBe(AttendanceStatus::Present);
});

/**
 * Simulasi database yang sudah berjalan sebelum fitur pola ganda: satu pola jam
 * kantor dipilih sebagai default global. Skemanya dikembalikan ke sebelum migration,
 * lalu migration-nya dijalankan sungguhan di atas data itu.
 */
test('upgrading an existing single-pattern setup keeps it working and still selectable', function () {
    $shift = officeShiftNamed('PAGI', '08:00', '17:00');
    $inUse = officePatternFor('HQ', $shift, registered: false);
    $unused = officePatternFor('CAB', officeShiftNamed('SIANG', '13:00', '22:00'), registered: false);

    Setting::set(DefaultOfficeSchedule::SETTING_KEY, (string) $inUse->id);

    // Karyawan lama: kolom office_pattern_id belum pernah diisi.
    $employee = officeHoursEmployee('Kantoran');
    expect($employee->office_pattern_id)->toBeNull();

    // SQLite menolak membuang kolom yang masih ber-index, jadi indexnya lebih dulu.
    Schema::table('schedule_patterns', fn (Blueprint $table) => $table->dropIndex(['is_office_pattern']));
    Schema::table('schedule_patterns', fn (Blueprint $table) => $table->dropColumn('is_office_pattern'));

    (require database_path('migrations/2026_08_27_090000_add_is_office_pattern_to_schedule_patterns.php'))->up();

    // Pola yang sudah dipakai ikut terdaftar otomatis; pola lain tidak disentuh.
    expect($inUse->fresh()->is_office_pattern)->toBeTrue()
        ->and($unused->fresh()->is_office_pattern)->toBeFalse();

    // Absensi karyawan lama tetap diresolusi dari pola yang sama seperti sebelumnya.
    $result = app(AttendanceResolver::class)->compute($employee, Carbon::parse(OFFICE_MONDAY), '08:00', '17:00');
    expect($result['shift_id'])->toBe($shift->id);

    // Dan membuka lalu menyimpan Pengaturan apa adanya tidak mematikan konfigurasi
    // yang sudah berjalan — pola yang dipakai sudah tercentang lebih dulu.
    $admin = settingsAdmin();

    $this->actingAs($admin)->get(route('settings.index'))->assertOk();
    $this->actingAs($admin)
        ->put(route('settings.update'), [
            'roster_autogenerate' => '1',
            'office_pattern_ids' => [$inUse->id],
            'default_office_pattern_id' => $inUse->id,
        ])
        ->assertSessionHasNoErrors();

    expect(Setting::get(DefaultOfficeSchedule::SETTING_KEY))->toBe((string) $inUse->id)
        ->and($inUse->fresh()->is_office_pattern)->toBeTrue();
});

test('an employee who follows no office pattern still opens Jadwal Saya without error', function () {
    $employee = Employee::query()->create([
        'full_name' => 'Shift', 'employment_status' => 'active', 'follows_office_hours' => false,
    ]);
    $user = User::factory()->create();
    $employee->update(['user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('my-roster.index', ['month' => now()->format('Y-m')]))
        ->assertOk();
});
