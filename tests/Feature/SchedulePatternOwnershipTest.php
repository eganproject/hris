<?php

use App\Enums\SchedulePatternType;
use App\Models\Employee;
use App\Models\ScheduleAssignment;
use App\Models\SchedulePattern;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/** A schedule user WITHOUT attendance.view.all — sees only what they created. */
function ownerUser(): User
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    foreach (['schedule-patterns.view', 'schedule-patterns.create', 'schedule-patterns.update', 'schedule-patterns.delete', 'schedules.view', 'attendance.view.all'] as $p) {
        Permission::findOrCreate($p, 'web');
    }
    $user = User::factory()->create();
    $user->givePermissionTo(['schedule-patterns.view', 'schedule-patterns.create', 'schedule-patterns.update', 'schedule-patterns.delete', 'schedules.view']);

    return $user;
}

function patternFor(User $user, string $code): SchedulePattern
{
    return SchedulePattern::query()->create([
        'code' => $code, 'name' => "Pola {$code}", 'type' => SchedulePatternType::FixedWeekly,
        'cycle_length' => 7, 'is_active' => true, 'created_by' => $user->id,
    ]);
}

test('the patterns page only lists patterns created by the logged-in user', function () {
    $mine = ownerUser();
    $other = ownerUser();

    patternFor($mine, 'MINE');
    patternFor($other, 'OTHER');

    $this->actingAs($mine)->get('/attendance/schedule-patterns')
        ->assertOk()
        ->assertSee('Pola MINE')
        ->assertDontSee('Pola OTHER');
});

test('storing a pattern records the creator', function () {
    $user = ownerUser();
    $reg = Shift::query()->create(['code' => 'REG', 'name' => 'Reguler', 'start_time' => '08:00', 'end_time' => '17:00', 'is_active' => true]);

    $this->actingAs($user)->post('/attendance/schedule-patterns', [
        'code' => 'W1', 'name' => 'Mingguan', 'type' => 'fixed_weekly', 'is_active' => '1',
        'days' => [1 => $reg->id, 2 => $reg->id],
    ])->assertRedirect('/attendance/schedule-patterns');

    expect(SchedulePattern::query()->where('code', 'W1')->value('created_by'))->toBe($user->id);
});

test('a user cannot edit or delete a pattern created by someone else', function () {
    $mine = ownerUser();
    $other = ownerUser();
    $othersPattern = patternFor($other, 'OTHER');

    $this->actingAs($mine)->get("/attendance/schedule-patterns/{$othersPattern->id}/edit")->assertForbidden();
    $this->actingAs($mine)->delete("/attendance/schedule-patterns/{$othersPattern->id}")->assertForbidden();
    expect(SchedulePattern::query()->whereKey($othersPattern->id)->exists())->toBeTrue();
});

test('the roster active-assignments are limited to the users own creations', function () {
    $mine = ownerUser();
    $other = ownerUser();

    $branch = App\Models\Branch::query()->create(['code' => 'SBY', 'name' => 'Surabaya', 'is_active' => true]);
    $mine->accessBranches()->sync([$branch->id]); // cakupan lokasi agar bisa lihat karyawan

    $a = Employee::query()->create(['branch_id' => $branch->id, 'full_name' => 'Karyawan A', 'employment_status' => 'active', 'join_date' => now()->toDateString()]);
    $b = Employee::query()->create(['branch_id' => $branch->id, 'full_name' => 'Karyawan B', 'employment_status' => 'active', 'join_date' => now()->toDateString()]);

    $pMine = patternFor($mine, 'PM');
    $pOther = patternFor($other, 'PO');

    ScheduleAssignment::query()->create(['employee_id' => $a->id, 'schedule_pattern_id' => $pMine->id, 'start_date' => now()->startOfMonth()->toDateString(), 'end_date' => now()->endOfMonth()->toDateString(), 'created_by' => $mine->id]);
    ScheduleAssignment::query()->create(['employee_id' => $b->id, 'schedule_pattern_id' => $pOther->id, 'start_date' => now()->startOfMonth()->toDateString(), 'end_date' => now()->endOfMonth()->toDateString(), 'created_by' => $other->id]);

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->actingAs($mine)->get('/attendance/schedules')
        ->assertOk()
        ->assertSee('Pola PM')
        ->assertDontSee('Pola PO');
});
