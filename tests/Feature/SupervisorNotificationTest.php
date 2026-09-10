<?php

use App\Enums\LeaveRequestStatus;
use App\Models\AttendanceCorrection;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\EmployeeContract;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\OvertimeApproval;
use App\Models\User;
use App\Support\ApprovalNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Apa yang sampai ke ATASAN, dan apa yang berhenti sebelum sampai.
 *
 * Seorang atasan tidak otomatis memegang menu HR, jadi tembusan ke atasan disaring
 * dua kali: izin membuka halamannya, lalu cakupan datanya. Yang diuji di sini adalah
 * kedua saringan itu — bukan sekadar "atasan dapat notifikasi".
 *
 * Helper scopedUser() & inboxTitles() ada di tests/Pest.php.
 *
 * @return array{atasanAtas: Employee, atasan: Employee, karyawan: Employee, userAtasanAtas: User, userAtasan: User}
 */
function chainOfThree(array $atasanPermissions = [], array $atasanAtasPermissions = []): array
{
    $branch = Branch::query()->create(['code' => 'HO', 'name' => 'Head Office', 'is_active' => true]);
    $common = ['branch_id' => $branch->id, 'employment_status' => 'active'];

    $userAtasanAtas = scopedUser([...$atasanAtasPermissions, User::SCOPE_BYPASS_ATTENDANCE, User::SCOPE_BYPASS_EMPLOYEES]);
    $userAtasanAtas->forceFill(['bypass_team_scope' => true])->save();
    $atasanAtas = Employee::query()->create([...$common, 'user_id' => $userAtasanAtas->id, 'full_name' => 'Rina Manajer']);

    $userAtasan = scopedUser([...$atasanPermissions, User::SCOPE_BYPASS_ATTENDANCE, User::SCOPE_BYPASS_EMPLOYEES]);
    $userAtasan->forceFill(['bypass_team_scope' => true])->save();
    $atasan = Employee::query()->create([
        ...$common, 'user_id' => $userAtasan->id, 'full_name' => 'Sari Atasan', 'manager_id' => $atasanAtas->id,
    ]);

    $karyawan = Employee::query()->create([...$common, 'full_name' => 'Budi Staf', 'manager_id' => $atasan->id]);

    return compact('atasanAtas', 'atasan', 'karyawan', 'userAtasanAtas', 'userAtasan');
}

test('pengajuan cuti yang mengendap diingatkan ke atasan, lalu dinaikkan ke atasan di atasnya', function () {
    // Atasan di atasnya cukup punya "leave.view": halaman Cuti & Izin memakai garis
    // atasan berjenjang, jadi pengajuan cucu-buahnya memang muncul di sana.
    $f = chainOfThree(atasanAtasPermissions: ['leave.view']);

    $type = LeaveType::query()->create([
        'code' => 'IZ', 'name' => 'Izin', 'attendance_status' => 'leave',
        'is_paid' => true, 'counts_against_balance' => false, 'is_active' => true,
    ]);

    $leave = LeaveRequest::query()->create([
        'employee_id' => $f['karyawan']->id,
        'leave_type_id' => $type->id,
        'supervisor_id' => $f['atasan']->id,
        'start_date' => today()->addDays(5)->toDateString(),
        'end_date' => today()->addDays(5)->toDateString(),
        'status' => LeaveRequestStatus::PendingSupervisor,
    ]);

    $notifier = app(ApprovalNotifier::class);

    // H+3: baru sebatas mengingatkan atasannya.
    $notifier->leavePendingReminder($leave, 3, escalate: false);

    expect(inboxTitles($f['userAtasan']))->toBe(['Pengajuan Izin menunggu keputusan Anda'])
        ->and(inboxTitles($f['userAtasanAtas']))->toBe([]);

    // H+7: didiamkan seminggu bukan lagi soal lupa.
    $notifier->leavePendingReminder($leave, 7, escalate: true);

    expect(inboxTitles($f['userAtasan']))->toBe([
        'Pengajuan Izin menunggu keputusan Anda',
        'Pengajuan Izin menunggu keputusan Anda',
    ])->and(inboxTitles($f['userAtasanAtas']))->toBe(['Pengajuan Izin di tim Anda mengendap']);
});

test('kenaikan berhenti bila atasan di atasnya tidak bisa membuka halamannya', function () {
    // Tanpa "leave.view" ia hanya akan menemukan 403 di ujung tautannya.
    $f = chainOfThree();

    $type = LeaveType::query()->create([
        'code' => 'IZ', 'name' => 'Izin', 'attendance_status' => 'leave',
        'is_paid' => true, 'counts_against_balance' => false, 'is_active' => true,
    ]);

    $leave = LeaveRequest::query()->create([
        'employee_id' => $f['karyawan']->id,
        'leave_type_id' => $type->id,
        'supervisor_id' => $f['atasan']->id,
        'start_date' => today()->addDays(5)->toDateString(),
        'end_date' => today()->addDays(5)->toDateString(),
        'status' => LeaveRequestStatus::PendingSupervisor,
    ]);

    app(ApprovalNotifier::class)->leavePendingReminder($leave, 7, escalate: true);

    expect(inboxTitles($f['userAtasanAtas']))->toBe([]);
});

test('lembur yang mengendap juga diingatkan lalu dinaikkan', function () {
    $f = chainOfThree(atasanAtasPermissions: ['overtime.view']);

    $overtime = OvertimeApproval::query()->create([
        'employee_id' => $f['karyawan']->id,
        'supervisor_id' => $f['atasan']->id,
        'work_date' => today()->subDays(8)->toDateString(),
        'start_time' => '17:00', 'end_time' => '19:00',
        'requested_minutes' => 120,
        'reason' => 'Tutup buku.',
        'requested_at' => now()->subDays(7),
        'status' => OvertimeApproval::STATUS_PENDING,
    ]);

    app(ApprovalNotifier::class)->overtimePendingReminder($overtime, 7, escalate: true);

    expect(inboxTitles($f['userAtasan']))->toBe(['Pengajuan lembur menunggu keputusan Anda'])
        ->and(inboxTitles($f['userAtasanAtas']))->toBe(['Pengajuan lembur di tim Anda mengendap']);
});

test('perintah terjadwal menagih tepat pada H+3 dan H+7, bukan setiap hari', function () {
    $f = chainOfThree(atasanAtasPermissions: ['leave.view']);

    $type = LeaveType::query()->create([
        'code' => 'IZ', 'name' => 'Izin', 'attendance_status' => 'leave',
        'is_paid' => true, 'counts_against_balance' => false, 'is_active' => true,
    ]);

    $buat = function (int $daysAgo) use ($f, $type) {
        $leave = LeaveRequest::query()->create([
            'employee_id' => $f['karyawan']->id,
            'leave_type_id' => $type->id,
            'supervisor_id' => $f['atasan']->id,
            'start_date' => today()->addDays(20)->toDateString(),
            'end_date' => today()->addDays(20)->toDateString(),
            'status' => LeaveRequestStatus::PendingSupervisor,
        ]);

        return $leave->forceFill(['created_at' => now()->subDays($daysAgo)])->save();
    };

    // Satu berumur 3 hari (ditagih), satu 5 hari (dilewati), satu 7 hari (dinaikkan).
    $buat(3);
    $buat(5);
    $buat(7);

    $this->artisan('approvals:notify-stale')->assertSuccessful();

    expect(inboxTitles($f['userAtasan']))->toBe([
        'Pengajuan Izin menunggu keputusan Anda',
        'Pengajuan Izin menunggu keputusan Anda',
    ])->and(inboxTitles($f['userAtasanAtas']))->toBe(['Pengajuan Izin di tim Anda mengendap']);
});

test('koreksi absensi bawahan ditembuskan ke atasan yang boleh membuka halamannya', function () {
    $f = chainOfThree(atasanPermissions: ['corrections.view']);

    $correction = AttendanceCorrection::query()->create([
        'employee_id' => $f['karyawan']->id,
        'work_date' => today()->subDay(),
        'requested_clock_in' => '08:00:00',
        'reason' => 'Lupa absen.',
        'status' => AttendanceCorrection::STATUS_PENDING,
    ]);

    app(ApprovalNotifier::class)->correctionSubmitted($correction);

    expect(inboxTitles($f['userAtasan']))->toBe(['Koreksi absensi bawahan']);
});

test('atasan yang justru memutuskan koreksi tidak menerima tembusannya dua kali', function () {
    // Ia sudah dapat "Koreksi absensi baru" lewat jalur pemegang izin keputusan;
    // tembusannya akan jadi baris kedua yang isinya sama.
    $f = chainOfThree(atasanPermissions: ['corrections.view', 'corrections.update']);

    $correction = AttendanceCorrection::query()->create([
        'employee_id' => $f['karyawan']->id,
        'work_date' => today()->subDay(),
        'requested_clock_in' => '08:00:00',
        'reason' => 'Lupa absen.',
        'status' => AttendanceCorrection::STATUS_PENDING,
    ]);

    app(ApprovalNotifier::class)->correctionSubmitted($correction);

    expect(inboxTitles($f['userAtasan']))->toBe(['Koreksi absensi baru']);
});

test('atasan diberi tahu saat kontrak bawahannya akan berakhir dan saat orangnya dinonaktifkan', function () {
    $f = chainOfThree(atasanPermissions: ['employees.view']);

    $contract = EmployeeContract::query()->create([
        'employee_id' => $f['karyawan']->id,
        'contract_number' => 'PKWT-001',
        'contract_type' => 'pkwt',
        'start_date' => today()->subYear()->toDateString(),
        'end_date' => today()->addDays(7)->toDateString(),
        'status' => 'active',
    ]);

    $notifier = app(ApprovalNotifier::class);
    $notifier->contractExpiring($f['karyawan'], $contract, 7);
    $notifier->contractAutoDeactivated($f['karyawan'], $contract);

    expect(inboxTitles($f['userAtasan']))->toBe([
        'Kontrak bawahan akan berakhir',
        'Bawahan Anda dinonaktifkan',
    ]);
});

test('cuti yang dibuatkan orang lain tidak disebut sebagai pengajuan karyawannya sendiri', function () {
    $f = chainOfThree(atasanPermissions: ['leave.view']);

    $hr = scopedUser(['leave.view', 'leave.create', 'leave.update', User::SCOPE_BYPASS_ATTENDANCE]);
    $hr->forceFill(['name' => 'Dewi HR', 'bypass_team_scope' => true])->save();

    $type = LeaveType::query()->create([
        'code' => 'IZ', 'name' => 'Izin', 'attendance_status' => 'leave',
        'is_paid' => true, 'counts_against_balance' => false, 'is_active' => true,
    ]);

    $this->actingAs($hr)->post('/attendance/leave', [
        'employee_id' => $f['karyawan']->id,
        'leave_type_id' => $type->id,
        'start_date' => now()->startOfMonth()->addDays(9)->toDateString(),
        'end_date' => now()->startOfMonth()->addDays(9)->toDateString(),
    ])->assertRedirect();

    $message = $f['userAtasan']->notifications()->first()->data['message'];

    expect($message)->toContain('Dewi HR membuat pengajuan Izin atas nama Budi Staf')
        ->and($message)->not->toContain('Budi Staf mengajukan');
});
