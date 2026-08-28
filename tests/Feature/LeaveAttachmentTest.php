<?php

use App\Enums\LeaveRequestStatus;
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

function leaveType(): LeaveType
{
    return LeaveType::query()->firstOrCreate(
        ['code' => 'SK'],
        ['name' => 'Sakit', 'attendance_status' => 'sick', 'is_paid' => true, 'counts_against_balance' => false, 'is_active' => true],
    );
}

/** Karyawan berakun yang boleh mengajukan cuti untuk dirinya sendiri. */
function requester(string $name = 'Budi', ?Employee $manager = null): array
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::findOrCreate('my-leave.view', 'web');

    $user = User::factory()->create();
    $user->givePermissionTo('my-leave.view');
    $employee = Employee::query()->create([
        'user_id' => $user->id, 'full_name' => $name, 'employment_status' => 'active',
        'manager_id' => $manager?->id,
    ]);

    return [$user, $employee];
}

test('an employee can attach a document to their own leave request', function () {
    Storage::fake('local');
    [$user, $employee] = requester();

    $this->actingAs($user)->post('/my-leave', [
        'leave_type_id' => leaveType()->id,
        'start_date' => now()->addDay()->toDateString(),
        'end_date' => now()->addDay()->toDateString(),
        'reason' => 'Demam.',
        'attachment' => UploadedFile::fake()->create('surat-dokter.pdf', 400, 'application/pdf'),
    ])->assertRedirect();

    $leave = LeaveRequest::query()->firstOrFail();

    expect($leave->attachment_path)->not->toBeNull()
        ->and($leave->attachment_name)->toBe('surat-dokter.pdf')
        ->and($leave->attachment_mime)->toBe('application/pdf')
        ->and($leave->hasAttachment())->toBeTrue();

    // Disimpan di disk privat, di bawah folder karyawannya.
    Storage::disk('local')->assertExists($leave->attachment_path);
    expect($leave->attachment_path)->toStartWith("leave-attachments/{$employee->id}/")
        // Nama asli tidak pernah dipakai sebagai nama berkas di disk.
        ->and($leave->attachment_path)->not->toContain('surat-dokter');
});

test('a 3 MB scan is accepted', function () {
    // Ukurannya sengaja ditulis tetap, bukan diturunkan dari ATTACHMENT_MAX_MB:
    // berkas yang ikut berubah mengikuti konstanta akan lolos berapa pun batasnya
    // dan tidak menjaga apa-apa. Angka ini mewakili hasil pindai surat dokter yang
    // dulu ditolak pada batas 2 MB — menurunkan batas lagi akan menggagalkannya.
    Storage::fake('local');
    [$user] = requester();

    $this->actingAs($user)->post('/my-leave', [
        'leave_type_id' => leaveType()->id,
        'start_date' => now()->addDay()->toDateString(),
        'end_date' => now()->addDay()->toDateString(),
        'attachment' => UploadedFile::fake()->create('pindaian-surat-dokter.pdf', 3 * 1024, 'application/pdf'),
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect(LeaveRequest::query()->firstOrFail()->hasAttachment())->toBeTrue();
});

test('an attachment over the size limit or of the wrong type is rejected', function () {
    Storage::fake('local');
    [$user] = requester();

    $payload = fn (UploadedFile $file) => [
        'leave_type_id' => leaveType()->id,
        'start_date' => now()->addDay()->toDateString(),
        'end_date' => now()->addDay()->toDateString(),
        'attachment' => $file,
    ];

    $this->actingAs($user)
        ->post('/my-leave', $payload(UploadedFile::fake()->create('besar.pdf', (LeaveRequest::ATTACHMENT_MAX_MB * 1024) + 64, 'application/pdf')))
        ->assertSessionHasErrors('attachment');

    $this->actingAs($user)
        ->post('/my-leave', $payload(UploadedFile::fake()->create('skrip.exe', 10, 'application/octet-stream')))
        ->assertSessionHasErrors('attachment');

    expect(LeaveRequest::query()->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles())->toBeEmpty();
});

test('the attachment is optional', function () {
    Storage::fake('local');
    [$user] = requester();

    $this->actingAs($user)->post('/my-leave', [
        'leave_type_id' => leaveType()->id,
        'start_date' => now()->addDay()->toDateString(),
        'end_date' => now()->addDay()->toDateString(),
    ])->assertRedirect();

    expect(LeaveRequest::query()->firstOrFail()->hasAttachment())->toBeFalse();
});

test('the requester, their supervisor and HR can each open the attachment', function () {
    Storage::fake('local');

    [$bossUser, $boss] = requester('Atasan');
    [$staffUser, $staff] = requester('Bawahan', $boss);

    $leave = LeaveRequest::query()->create([
        'employee_id' => $staff->id, 'leave_type_id' => leaveType()->id,
        'supervisor_id' => $boss->id,
        'start_date' => now()->toDateString(), 'end_date' => now()->toDateString(),
        'status' => LeaveRequestStatus::PendingSupervisor->value,
        'attachment_path' => 'leave-attachments/'.$staff->id.'/abc.pdf',
        'attachment_name' => 'surat.pdf', 'attachment_mime' => 'application/pdf', 'attachment_size' => 1024,
    ]);
    Storage::disk('local')->put($leave->attachment_path, 'isi berkas');

    // 1. Pengajunya sendiri.
    $this->actingAs($staffUser)->get(route('leave.attachment', $leave))->assertOk();

    // 2. Atasan yang ditunjuk memutuskan — tanpa perlu izin leave.view.
    $this->actingAs($bossUser)->get(route('leave.attachment', $leave))->assertOk();

    // 3. HR dengan izin leave.view dan cakupan penuh.
    Permission::findOrCreate('leave.view', 'web');
    Permission::findOrCreate(User::SCOPE_BYPASS_ATTENDANCE, 'web');
    $hr = User::factory()->create();
    $hr->givePermissionTo(['leave.view', User::SCOPE_BYPASS_ATTENDANCE]);

    $this->actingAs($hr)->get(route('leave.attachment', $leave))->assertOk();
});

test('an unrelated employee cannot open someone else attachment', function () {
    Storage::fake('local');

    [, $owner] = requester('Pemilik');
    [$otherUser] = requester('Orang Lain');

    $leave = LeaveRequest::query()->create([
        'employee_id' => $owner->id, 'leave_type_id' => leaveType()->id,
        'start_date' => now()->toDateString(), 'end_date' => now()->toDateString(),
        'status' => LeaveRequestStatus::PendingHr->value,
        'attachment_path' => 'leave-attachments/'.$owner->id.'/rahasia.pdf',
        'attachment_name' => 'rahasia.pdf', 'attachment_mime' => 'application/pdf', 'attachment_size' => 1024,
    ]);
    Storage::disk('local')->put($leave->attachment_path, 'isi berkas');

    // Rekan kerja biasa: bukan pengaju, bukan atasannya, tanpa izin leave.view.
    $this->actingAs($otherUser)->get(route('leave.attachment', $leave))->assertForbidden();

    // Tamu tanpa login sama sekali.
    auth()->logout();
    $this->get(route('leave.attachment', $leave))->assertRedirect(route('login'));
});

test('a request with no attachment returns 404 rather than an error', function () {
    Storage::fake('local');
    [$user, $employee] = requester();

    $leave = LeaveRequest::query()->create([
        'employee_id' => $employee->id, 'leave_type_id' => leaveType()->id,
        'start_date' => now()->toDateString(), 'end_date' => now()->toDateString(),
        'status' => LeaveRequestStatus::PendingHr->value,
    ]);

    $this->actingAs($user)->get(route('leave.attachment', $leave))->assertNotFound();
});

test('a path that outlived its file returns 404, not a 500', function () {
    Storage::fake('local');
    [$user, $employee] = requester();

    $leave = LeaveRequest::query()->create([
        'employee_id' => $employee->id, 'leave_type_id' => leaveType()->id,
        'start_date' => now()->toDateString(), 'end_date' => now()->toDateString(),
        'status' => LeaveRequestStatus::PendingHr->value,
        'attachment_path' => 'leave-attachments/hilang.pdf',
        'attachment_name' => 'hilang.pdf', 'attachment_mime' => 'application/pdf', 'attachment_size' => 10,
    ]);

    $this->actingAs($user)->get(route('leave.attachment', $leave))->assertNotFound();
});

test('deleting a request takes its attachment off the disk', function () {
    Storage::fake('local');
    [$user, $employee] = requester();

    $leave = LeaveRequest::query()->create([
        'employee_id' => $employee->id, 'leave_type_id' => leaveType()->id,
        'start_date' => now()->toDateString(), 'end_date' => now()->toDateString(),
        'status' => LeaveRequestStatus::PendingHr->value,
        'attachment_path' => 'leave-attachments/'.$employee->id.'/buang.pdf',
        'attachment_name' => 'buang.pdf', 'attachment_mime' => 'application/pdf', 'attachment_size' => 10,
    ]);
    Storage::disk('local')->put($leave->attachment_path, 'isi');

    $this->actingAs($user)->delete(route('my-leave.destroy', $leave))->assertRedirect();

    Storage::disk('local')->assertMissing('leave-attachments/'.$employee->id.'/buang.pdf');
    expect(LeaveRequest::query()->count())->toBe(0);
});

test('HR filing on behalf of an employee can attach the document too', function () {
    Storage::fake('local');

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::findOrCreate('leave.create', 'web');
    Permission::findOrCreate('leave.view', 'web');
    Permission::findOrCreate(User::SCOPE_BYPASS_ATTENDANCE, 'web');

    $hr = User::factory()->create();
    $hr->givePermissionTo(['leave.create', 'leave.view', User::SCOPE_BYPASS_ATTENDANCE]);

    $employee = Employee::query()->create(['full_name' => 'Karyawan', 'employment_status' => 'active']);

    $this->actingAs($hr)->post('/attendance/leave', [
        'employee_id' => $employee->id,
        'leave_type_id' => leaveType()->id,
        'start_date' => now()->toDateString(),
        'end_date' => now()->toDateString(),
        'reason' => 'Surat dari klinik.',
        'attachment' => UploadedFile::fake()->image('surat.jpg'),
    ])->assertRedirect();

    $leave = LeaveRequest::query()->firstOrFail();

    expect($leave->attachment_name)->toBe('surat.jpg')
        ->and($leave->attachmentIsImage())->toBeTrue();
    Storage::disk('local')->assertExists($leave->attachment_path);
});

test('both leave forms render the attachment field with its live preview', function () {
    [$user] = requester();

    // Formulir karyawan.
    $this->actingAs($user)->get('/my-leave/create')
        ->assertOk()
        ->assertSee('data-attachment-input', escape: false)
        ->assertSee('data-attachment-preview', escape: false)
        ->assertSee('enctype="multipart/form-data"', escape: false)
        ->assertSee('maksimal '.LeaveRequest::ATTACHMENT_MAX_MB.' MB');

    // Formulir HR (dibuatkan atas nama karyawan).
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::findOrCreate('leave.create', 'web');
    Permission::findOrCreate(User::SCOPE_BYPASS_ATTENDANCE, 'web');
    $hr = User::factory()->create();
    $hr->givePermissionTo(['leave.create', User::SCOPE_BYPASS_ATTENDANCE]);

    $this->actingAs($hr)->get('/attendance/leave/create')
        ->assertOk()
        ->assertSee('data-attachment-input', escape: false)
        ->assertSee('data-attachment-preview', escape: false)
        ->assertSee('enctype="multipart/form-data"', escape: false);
});
