<?php

use App\Enums\AttendanceStatus;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/**
 * Pengajuan sakit wajib melampirkan surat keterangan. Yang menentukan bukan nama
 * jenis cutinya melainkan status absensi yang dipetakan padanya, jadi perusahaan
 * bebas memakai lebih dari satu jenis sakit.
 */
function sickRequester(): array
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::findOrCreate('my-leave.view', 'web');

    $user = User::factory()->create();
    $user->givePermissionTo('my-leave.view');

    $employee = Employee::query()->create([
        'user_id' => $user->id, 'full_name' => 'Budi Sakit', 'employment_status' => 'active',
        'join_date' => now()->subYear()->toDateString(),
    ]);

    return [$user, $employee];
}

function leaveTypeWith(AttendanceStatus $status, string $code, string $name): LeaveType
{
    return LeaveType::query()->create([
        'code' => $code, 'name' => $name, 'attendance_status' => $status->value,
        'is_paid' => true, 'counts_against_balance' => false, 'is_active' => true,
    ]);
}

/** @return array<string, mixed> */
function leavePayload(LeaveType $type, array $overrides = []): array
{
    return [
        'leave_type_id' => $type->id,
        'start_date' => now()->addDay()->toDateString(),
        'end_date' => now()->addDay()->toDateString(),
        'reason' => 'Uji pengajuan.',
        ...$overrides,
    ];
}

test('a sick request without the doctor note is refused', function () {
    [$user] = sickRequester();
    $sick = leaveTypeWith(AttendanceStatus::Sick, 'SK', 'Sakit');

    $this->actingAs($user)
        ->from(route('my-leave.create'))
        ->post(route('my-leave.store'), leavePayload($sick))
        ->assertRedirect(route('my-leave.create'))
        ->assertSessionHasErrors('attachment');

    expect(LeaveRequest::query()->count())->toBe(0)
        ->and(session('errors')->first('attachment'))
        ->toContain('surat keterangan sakit wajib dilampirkan');
});

test('a sick request goes through once the note is attached', function () {
    Storage::fake('local');
    [$user, $employee] = sickRequester();
    $sick = leaveTypeWith(AttendanceStatus::Sick, 'SK', 'Sakit');

    $this->actingAs($user)
        ->post(route('my-leave.store'), leavePayload($sick, [
            'attachment' => UploadedFile::fake()->image('surat-sakit.jpg', 900, 1200),
        ]))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $request = LeaveRequest::query()->where('employee_id', $employee->id)->firstOrFail();

    expect($request->leave_type_id)->toBe($sick->id)
        ->and($request->attachment_path)->not->toBeNull();
});

test('every other leave type keeps the attachment optional', function () {
    [$user] = sickRequester();

    $optional = [
        leaveTypeWith(AttendanceStatus::Leave, 'CT', 'Cuti Tahunan'),
        leaveTypeWith(AttendanceStatus::Permit, 'IZ', 'Izin'),
        leaveTypeWith(AttendanceStatus::Wfh, 'WFH', 'Work From Home'),
    ];

    foreach ($optional as $index => $type) {
        $this->actingAs($user)
            ->post(route('my-leave.store'), leavePayload($type, [
                // Rentang berbeda per jenis: pengajuan yang beririsan ditolak penjaga.
                'start_date' => now()->addDays(3 + $index * 3)->toDateString(),
                'end_date' => now()->addDays(3 + $index * 3)->toDateString(),
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();
    }

    expect(LeaveRequest::query()->count())->toBe(count($optional));
});

test('a second sick type mapped to Sakit is covered too, without naming it in code', function () {
    [$user] = sickRequester();
    $longSick = leaveTypeWith(AttendanceStatus::Sick, 'SKP', 'Sakit Berkepanjangan');

    $this->actingAs($user)
        ->post(route('my-leave.store'), leavePayload($longSick))
        ->assertRedirect()
        ->assertSessionHasErrors('attachment');

    expect(LeaveRequest::query()->count())->toBe(0);
});

test('the form marks the sick options so the field can demand the note', function () {
    [$user] = sickRequester();
    leaveTypeWith(AttendanceStatus::Sick, 'SK', 'Sakit');
    leaveTypeWith(AttendanceStatus::Leave, 'CT', 'Cuti Tahunan');

    $html = $this->actingAs($user)->get(route('my-leave.create'))->assertOk()->getContent();

    // Satu penanda saja: hanya jenis sakit yang mewajibkan lampiran.
    expect(substr_count($html, 'data-requires-attachment'))->toBe(1)
        ->and($html)->toContain('data-attachment-requirement="#attachment-field"');
});
