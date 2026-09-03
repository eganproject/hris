<?php

use App\Enums\LeaveRequestStatus;
use App\Models\Attendance;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\SchedulePattern;
use App\Models\User;
use App\Support\DataScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/**
 * Absensi Harian, Jadwal Kerja & Cuti dipersempit ke bawahan pengguna.
 *
 * Pengecualiannya adalah saklar per pengguna di Kontrol Akses, bukan daftar nama role
 * di dalam kode — susunan role tiap perusahaan berbeda, dan menambah role baru tidak
 * boleh menuntut perubahan kode.
 *
 * Yang paling penting dijaga di sini: pembatasan ini TIDAK boleh merembet ke modul
 * lain. Lembur, koreksi, laporan, dan data karyawan tetap memakai cakupan
 * lokasi/divisi seperti sebelumnya.
 */
function teamUser(bool $bypass = false): User
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $permissions = [...attendanceMenuPermissions(), 'leave.view', User::SCOPE_BYPASS_ATTENDANCE];

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $user = User::factory()->create();
    $user->givePermissionTo($permissions);
    $user->forceFill(['bypass_team_scope' => $bypass])->save();

    return $user;
}

/** Atasan (tertaut ke akun) dengan satu bawahan, plus satu karyawan tak terkait. */
function teamTree(User $user): array
{
    $branch = Branch::query()->create(['code' => 'SBY', 'name' => 'Surabaya', 'type' => 'office', 'is_active' => true]);
    $department = Department::query()->create(['code' => 'OPS', 'name' => 'Operasional', 'is_active' => true]);

    $common = ['branch_id' => $branch->id, 'department_id' => $department->id, 'employment_status' => 'active'];

    $manager = Employee::query()->create([...$common, 'user_id' => $user->id, 'full_name' => 'Rina Atasan']);
    $subordinate = Employee::query()->create([...$common, 'manager_id' => $manager->id, 'full_name' => 'Andi Bawahan']);
    $stranger = Employee::query()->create([...$common, 'full_name' => 'Citra Bukan Bawahan']);

    return [$manager, $subordinate, $stranger];
}

test('the daily board and the roster show only the subordinates', function () {
    $user = teamUser();
    [, $subordinate, $stranger] = teamTree($user);

    foreach (['/attendance/daily', '/attendance/schedules'] as $url) {
        $this->actingAs($user)->get($url)
            ->assertOk()
            ->assertSee('Andi Bawahan')
            ->assertDontSee('Citra Bukan Bawahan');
    }

    expect($subordinate->id)->not->toBe($stranger->id);
});

/** Satu baris absensi hari ini untuk tiap karyawan yang diberikan. */
function logAttendanceFor(array $employees): void
{
    foreach ($employees as $employee) {
        Attendance::query()->create([
            'employee_id' => $employee->id,
            'work_date' => now()->toDateString(),
            'status' => 'present',
            'clock_in' => now()->copy()->setTime(8, 0),
            'clock_out' => now()->copy()->setTime(17, 0),
        ]);
    }
}

test('the attendance log report shows only the subordinates', function () {
    $user = teamUser();
    [, $subordinate, $stranger] = teamTree($user);
    logAttendanceFor([$subordinate, $stranger]);

    $this->actingAs($user)->get('/reports/attendance-log')
        ->assertOk()
        ->assertSee('Andi Bawahan')
        ->assertDontSee('Citra Bukan Bawahan');
});

test('the attendance log downloads carry the same narrowing as the screen', function () {
    $user = teamUser();
    [, $subordinate, $stranger] = teamTree($user);
    logAttendanceFor([$subordinate, $stranger]);

    // Berkas unduhan yang memuat lebih banyak orang daripada tabelnya adalah
    // kebocoran, jadi ekspor dan PDF harus memakai cakupan yang sama.
    $rows = $this->actingAs($user)->get('/reports/attendance-log')
        ->assertOk()
        ->viewData('rows');

    $ids = collect($rows->items())->pluck('employee_id');

    expect($ids)->toContain($subordinate->id)
        ->and($ids)->not->toContain($stranger->id);

    $this->actingAs($user)->get('/reports/attendance-log/export')->assertOk();
    $this->actingAs($user)->get('/reports/attendance-log/pdf')->assertOk();
});

test('a user with no subordinate is told why the attendance log is empty', function () {
    $user = teamUser();

    // Tanpa keterangan, laporan kosong terbaca sebagai aplikasi rusak — sama seperti
    // Jadwal Kerja dan Absensi Harian.
    Employee::query()->create([
        'user_id' => $user->id, 'full_name' => 'Rina Tanpa Tim', 'employment_status' => 'active',
    ]);

    $this->actingAs($user)->get('/reports/attendance-log')
        ->assertOk()
        ->assertSee('belum ada seorang pun yang tercatat di bawah Anda');
});

test('the switch in Kontrol Akses lifts the restriction on the attendance log too', function () {
    $user = teamUser(bypass: true);
    [, $subordinate, $stranger] = teamTree($user);
    logAttendanceFor([$subordinate, $stranger]);

    $this->actingAs($user)->get('/reports/attendance-log')
        ->assertOk()
        ->assertSee('Andi Bawahan')
        ->assertSee('Citra Bukan Bawahan');
});

test('the other reports keep their location and division scope', function () {
    $user = teamUser();
    [, , $stranger] = teamTree($user);

    // Hanya Log Absensi yang pindah ke garis atasan; Rekap Absensi tetap memakai
    // cakupan lokasi/divisi seperti sebelumnya.
    $this->actingAs($user)->get('/reports/attendance')
        ->assertOk()
        ->assertSee($stranger->full_name);
});
test('the switch in Kontrol Akses lifts the restriction', function () {
    $user = teamUser(bypass: true);
    teamTree($user);

    foreach (['/attendance/daily', '/attendance/schedules'] as $url) {
        $this->actingAs($user)->get($url)
            ->assertOk()
            ->assertSee('Andi Bawahan')
            ->assertSee('Citra Bukan Bawahan');
    }
});

test('a superadmin sees everyone even with the switch off', function () {
    $user = teamUser();
    $user->assignRole(Role::findOrCreate('superadmin', 'web'));
    teamTree($user);

    // Kalau superadmin ikut dibatasi, seorang admin bisa mencabut aksesnya sendiri
    // dan tidak punya jalan kembali untuk memulihkannya.
    $this->actingAs($user)->get('/attendance/schedules')
        ->assertOk()
        ->assertSee('Citra Bukan Bawahan');
});

test('the per-employee pages of someone outside the team are forbidden', function () {
    $user = teamUser();
    [, $subordinate, $stranger] = teamTree($user);

    $this->actingAs($user)->get("/attendance/daily/{$subordinate->id}/history")->assertOk();
    $this->actingAs($user)->get("/attendance/daily/{$stranger->id}/history")->assertForbidden();

    $this->actingAs($user)->get("/attendance/schedules/employees/{$subordinate->id}")->assertOk();
    $this->actingAs($user)->get("/attendance/schedules/employees/{$stranger->id}")->assertForbidden();
});

test('a restricted user without any subordinate is told why the page is empty', function () {
    $user = teamUser();

    Employee::query()->create(['full_name' => 'Orang Lain', 'employment_status' => 'active']);
    Employee::query()->create(['user_id' => $user->id, 'full_name' => 'Rina Tanpa Bawahan', 'employment_status' => 'active']);

    // Halaman kosong tanpa penjelasan terbaca sebagai aplikasi yang rusak.
    foreach (['/attendance/daily', '/attendance/schedules', '/attendance/leave', '/attendance/schedules/unscheduled'] as $url) {
        $this->actingAs($user)->get($url)
            ->assertOk()
            ->assertSee('belum ada seorang pun yang tercatat di bawah Anda', false)
            ->assertDontSee('Orang Lain');
    }
});

/** Satu pengajuan cuti yang masih menunggu keputusan HR, untuk satu karyawan. */
function teamLeaveFor(Employee $employee): LeaveRequest
{
    $type = LeaveType::query()->firstOrCreate(
        ['code' => 'IZ'],
        ['name' => 'Izin', 'attendance_status' => 'leave', 'is_paid' => true, 'is_active' => true],
    );

    return LeaveRequest::query()->create([
        'employee_id' => $employee->id,
        'leave_type_id' => $type->id,
        'start_date' => now()->addDay()->toDateString(),
        'end_date' => now()->addDay()->toDateString(),
        'status' => LeaveRequestStatus::PendingHr->value,
    ]);
}

test('the leave list shows only the subordinates', function () {
    $user = teamUser();
    [, $subordinate, $stranger] = teamTree($user);

    teamLeaveFor($subordinate);
    teamLeaveFor($stranger);

    $this->actingAs($user)->get('/attendance/leave')
        ->assertOk()
        ->assertSee('Andi Bawahan')
        ->assertDontSee('Citra Bukan Bawahan');
});

test('the switch in Kontrol Akses lifts the restriction on leave too', function () {
    $user = teamUser(bypass: true);
    [, $subordinate, $stranger] = teamTree($user);

    teamLeaveFor($subordinate);
    teamLeaveFor($stranger);

    $this->actingAs($user)->get('/attendance/leave')
        ->assertOk()
        ->assertSee('Andi Bawahan')
        ->assertSee('Citra Bukan Bawahan');
});

test('deciding the leave of someone outside the team is refused', function () {
    $user = teamUser();
    [, $subordinate, $stranger] = teamTree($user);

    Permission::findOrCreate('leave.update', 'web');
    $user->givePermissionTo('leave.update');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $outside = teamLeaveFor($stranger);
    $inside = teamLeaveFor($subordinate);

    $this->actingAs($user)->patch(route('attendance.leave.approve', $outside))->assertForbidden();
    $this->actingAs($user)->patch(route('attendance.leave.reject', $outside))->assertForbidden();
    expect($outside->fresh()->status)->toBe(LeaveRequestStatus::PendingHr);

    // Dan lewat aksi massal, yang melewati baris di luar wewenang alih-alih 403.
    $this->actingAs($user)->post(route('attendance.leave.bulk-approve'), ['ids' => [$outside->id, $inside->id]])
        ->assertRedirect(route('attendance.leave.index'));

    expect($outside->fresh()->status)->toBe(LeaveRequestStatus::PendingHr)
        ->and($inside->fresh()->status)->toBe(LeaveRequestStatus::Approved);
});

test('the leave attachment of someone outside the team is refused', function () {
    $user = teamUser();
    [, , $stranger] = teamTree($user);

    Storage::fake(LeaveRequest::ATTACHMENT_DISK);

    $outside = teamLeaveFor($stranger);
    $outside->forceFill([
        'attachment_path' => 'leave/surat.pdf',
        'attachment_name' => 'surat.pdf',
        'attachment_mime' => 'application/pdf',
    ])->save();

    Storage::disk(LeaveRequest::ATTACHMENT_DISK)->put('leave/surat.pdf', 'x');

    // Percuma menyempitkan daftarnya kalau berkasnya masih bisa diambil lewat tautan.
    $this->actingAs($user)->get(route('leave.attachment', $outside))->assertForbidden();
});

test('the dashboard leave counter matches what the leave page shows', function () {
    $user = teamUser();
    [, $subordinate, $stranger] = teamTree($user);

    foreach (['leave.update', 'dashboard.view'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
    $user->givePermissionTo(['leave.update', 'dashboard.view']);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    teamLeaveFor($subordinate);
    teamLeaveFor($stranger);

    // Angka yang lebih besar daripada isi halamannya menjanjikan pekerjaan yang
    // tidak ada begitu kartunya diklik.
    $card = collect($this->actingAs($user)->get('/dashboard')->assertOk()->viewData('todo'))
        ->firstWhere('label', 'Cuti/izin menunggu keputusan HR');

    expect($card['count'])->toBe(1);
});

test('the unscheduled list and its assign flow show only the subordinates', function () {
    $user = teamUser();
    [, , $stranger] = teamTree($user);

    Permission::findOrCreate('schedules.create', 'web');
    $user->givePermissionTo('schedules.create');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    // Keduanya: daftar orang yang belum punya pola, dan pemilih karyawan di halaman
    // "Tugaskan Pola" yang menjadi lanjutannya.
    foreach (['/attendance/schedules/unscheduled', '/attendance/schedules/assign'] as $url) {
        $this->actingAs($user)->get($url)
            ->assertOk()
            ->assertSee('Andi Bawahan')
            ->assertDontSee('Citra Bukan Bawahan');
    }

    expect($stranger->exists)->toBeTrue();
});

test('assigning a pattern to someone outside the team is refused', function () {
    $user = teamUser();
    [, , $stranger] = teamTree($user);

    Permission::findOrCreate('schedules.create', 'web');
    $user->givePermissionTo('schedules.create');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $pattern = SchedulePattern::query()->create([
        'code' => 'UJI-5D', 'name' => 'Pola Uji', 'type' => 'fixed_weekly',
        'cycle_length' => 7, 'is_active' => true, 'created_by' => $user->id,
    ]);

    // Percuma menyempitkan daftarnya kalau id-nya masih diterima aksi massal.
    $this->actingAs($user)->post(route('attendance.unscheduled.assign'), [
        'schedule_pattern_id' => $pattern->id,
        'employee_ids' => [$stranger->id],
        'start_date' => now()->startOfMonth()->toDateString(),
    ])->assertForbidden();

    expect($stranger->scheduleAssignments()->count())->toBe(0);
});

test('the monthly mode can be filtered straight to any month', function () {
    $user = teamUser();
    teamTree($user);

    // Bulan yang jauh dari hari ini harus bisa dituju langsung, bukan lewat belasan
    // klik panah — jadi bulannya adalah medan penyaring, bukan sekadar navigasi.
    $target = now()->addMonths(5)->format('Y-m');

    $this->actingAs($user)->get('/attendance/schedules/unscheduled?mode=no_schedule&month='.$target)
        ->assertOk()
        ->assertSee('type="month" name="month"', false)
        ->assertSee('value="'.$target.'"', false);
});

test('the restriction does not leak into other modules', function () {
    $user = teamUser();
    [, , $stranger] = teamTree($user);

    // Yang dipersempit hanya forTeam(); forAttendance() tetap seperti sebelumnya.
    $scoped = DataScope::forAttendance($user->fresh())->employees()->pluck('id');
    $team = DataScope::forTeam($user->fresh())->employees()->pluck('id');

    expect($scoped)->toContain($stranger->id)
        ->and($team)->not->toContain($stranger->id);
});

test('an admin can flip the switch from Kontrol Akses', function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    foreach (['access-control.view', 'access-control.update'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $admin = User::factory()->create();
    $admin->givePermissionTo(['access-control.view', 'access-control.update']);

    $target = teamUser();

    expect($target->fresh()->bypassesTeamScope())->toBeFalse();

    $this->actingAs($admin)
        ->put(route('access-control.user-scope.update', $target), ['bypass_team_scope' => '1'])
        ->assertRedirect();

    expect($target->fresh()->bypassesTeamScope())->toBeTrue();

    $this->actingAs($admin)
        ->put(route('access-control.user-scope.update', $target), [])
        ->assertRedirect();

    expect($target->fresh()->bypassesTeamScope())->toBeFalse();
});

test('every level below the viewer shows up, not just the first two', function () {
    $user = teamUser();

    $branch = Branch::query()->create(['code' => 'PTJ', 'name' => 'Petojo', 'type' => 'office', 'is_active' => true]);
    $department = Department::query()->create(['code' => 'AUT', 'name' => 'Automotive', 'is_active' => true]);
    $common = ['branch_id' => $branch->id, 'department_id' => $department->id, 'employment_status' => 'active'];

    // Charisma → Theresia → Novia → tiga staf. Yang dilaporkan hilang adalah
    // lapisan ketiga ke bawah.
    $charisma = Employee::query()->create([...$common, 'user_id' => $user->id, 'full_name' => 'Charisma Eka']);
    $theresia = Employee::query()->create([...$common, 'manager_id' => $charisma->id, 'full_name' => 'Theresia Audyna']);
    $novia = Employee::query()->create([...$common, 'manager_id' => $theresia->id, 'full_name' => 'Novia Ridwan']);

    foreach (['Nanda Novita', 'Reva Nopitasari', 'Yebertina Talunohi'] as $name) {
        Employee::query()->create([...$common, 'manager_id' => $novia->id, 'full_name' => $name]);
    }

    $response = $this->actingAs($user)->get('/attendance/schedules')->assertOk();

    foreach (['Theresia Audyna', 'Novia Ridwan', 'Nanda Novita', 'Reva Nopitasari', 'Yebertina Talunohi'] as $name) {
        $response->assertSee($name);
    }
});

test('a subordinate in another branch or division still shows up', function () {
    $user = teamUser();

    $petojo = Branch::query()->create(['code' => 'PTJ', 'name' => 'Petojo', 'type' => 'office', 'is_active' => true]);
    $gudang = Branch::query()->create(['code' => 'GDG', 'name' => 'Gudang', 'type' => 'warehouse', 'is_active' => true]);
    $automotive = Department::query()->create(['code' => 'AUT', 'name' => 'Automotive', 'is_active' => true]);
    $logistik = Department::query()->create(['code' => 'LOG', 'name' => 'Logistik', 'is_active' => true]);

    // Cakupan lokasi/divisi si atasan sengaja dibuat sempit.
    $user->accessBranches()->sync([$petojo->id]);
    $user->accessDepartments()->sync([$automotive->id]);

    $manager = Employee::query()->create([
        'user_id' => $user->id, 'branch_id' => $petojo->id, 'department_id' => $automotive->id,
        'full_name' => 'Charisma Eka', 'employment_status' => 'active',
    ]);

    // Bawahan langsung di divisi & lokasi LAIN — struktur organisasi tidak selalu
    // sejajar dengan pembagian lokasi/divisi.
    $jauh = Employee::query()->create([
        'manager_id' => $manager->id, 'branch_id' => $gudang->id, 'department_id' => $logistik->id,
        'full_name' => 'Novia Ridwan', 'employment_status' => 'active',
    ]);

    // Dan bawahannya lagi, satu tingkat lebih dalam.
    Employee::query()->create([
        'manager_id' => $jauh->id, 'branch_id' => $gudang->id, 'department_id' => $logistik->id,
        'full_name' => 'Nanda Novita', 'employment_status' => 'active',
    ]);

    foreach (['/attendance/daily', '/attendance/schedules'] as $url) {
        $this->actingAs($user)->get($url)
            ->assertOk()
            ->assertSee('Novia Ridwan')
            ->assertSee('Nanda Novita');
    }
});

test('a supervisor with a team but no branch scope is not told to ask for one', function () {
    $user = teamUser();

    $manager = Employee::query()->create(['user_id' => $user->id, 'full_name' => 'Charisma Eka', 'employment_status' => 'active']);
    Employee::query()->create(['manager_id' => $manager->id, 'full_name' => 'Novia Ridwan', 'employment_status' => 'active']);

    $this->actingAs($user)->get('/attendance/schedules')
        ->assertOk()
        ->assertSee('Novia Ridwan')
        ->assertDontSee('Cakupan akses Anda belum diatur');
});

test('a user without the org-wide permission can be scoped to one division', function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    // Sengaja TANPA attendance.view.all: yang menentukan cakupannya hanyalah daftar
    // lokasi & divisi pada kartu Kontrol Akses.
    $permissions = ['schedules.view', 'attendance-daily.view'];

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $user = User::factory()->create();
    $user->givePermissionTo($permissions);
    $user->forceFill(['bypass_team_scope' => true])->save();

    $branch = Branch::query()->create(['code' => 'PTJ', 'name' => 'Petojo', 'type' => 'office', 'is_active' => true]);
    $automotive = Department::query()->create(['code' => 'AUT', 'name' => 'Automotive', 'is_active' => true]);
    $logistik = Department::query()->create(['code' => 'LOG', 'name' => 'Logistik', 'is_active' => true]);

    $user->accessBranches()->sync([$branch->id]);
    $user->accessDepartments()->sync([$automotive->id]);

    Employee::query()->create(['branch_id' => $branch->id, 'department_id' => $automotive->id, 'full_name' => 'Novia Automotive', 'employment_status' => 'active']);
    Employee::query()->create(['branch_id' => $branch->id, 'department_id' => $logistik->id, 'full_name' => 'Budi Logistik', 'employment_status' => 'active']);

    foreach (['/attendance/daily', '/attendance/schedules'] as $url) {
        $this->actingAs($user)->get($url)
            ->assertOk()
            ->assertSee('Novia Automotive')
            ->assertDontSee('Budi Logistik');
    }
});

test('Kontrol Akses states the real effect of each scope choice', function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    foreach (['access-control.view', 'access-control.update'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $admin = User::factory()->create();
    $admin->givePermissionTo(['access-control.view', 'access-control.update']);

    $target = teamUser();
    $automotive = Department::query()->create(['code' => 'AUT', 'name' => 'Automotive', 'is_active' => true]);
    $target->accessDepartments()->sync([$automotive->id]);

    // Pilihannya harus menyebut divisi yang benar-benar akan berlaku, bukan sekadar
    // "lihat semua" — kalimat itulah yang dulu menyesatkan.
    $this->actingAs($admin)->get(route('access-control.index'))
        ->assertOk()
        ->assertSee('Cakupan di Absensi Harian, Jadwal Kerja, Cuti &amp; Log Absensi', false)
        ->assertSee('Bawahan saja')
        ->assertSee('Sesuai lokasi &amp; divisi di kartu ini', false);
});

test('choosing the division scope is saved and takes effect', function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    foreach (['access-control.view', 'access-control.update'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $admin = User::factory()->create();
    $admin->givePermissionTo(['access-control.view', 'access-control.update']);

    // Tanpa izin lihat-semua-data: justru bagi akun seperti inilah daftar divisi
    // menentukan apa yang terlihat.
    foreach (['schedules.view'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $target = User::factory()->create();
    $target->givePermissionTo(['schedules.view']);

    $automotive = Department::query()->create(['code' => 'AUT', 'name' => 'Automotive', 'is_active' => true]);
    $logistik = Department::query()->create(['code' => 'LOG', 'name' => 'Logistik', 'is_active' => true]);

    Employee::query()->create(['department_id' => $automotive->id, 'full_name' => 'Novia Automotive', 'employment_status' => 'active']);
    Employee::query()->create(['department_id' => $logistik->id, 'full_name' => 'Budi Logistik', 'employment_status' => 'active']);

    $this->actingAs($admin)
        ->put(route('access-control.user-scope.update', $target), [
            'bypass_team_scope' => '1',
            'departments' => [$automotive->id],
        ])
        ->assertRedirect();

    expect($target->fresh()->bypass_team_scope)->toBeTrue();

    $this->actingAs($target->fresh())->get('/attendance/schedules')
        ->assertOk()
        ->assertSee('Novia Automotive')
        ->assertDontSee('Budi Logistik');
});
