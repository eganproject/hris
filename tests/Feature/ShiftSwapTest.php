<?php

use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\Shift;
use App\Models\ShiftSwapRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/**
 * @return array{0: User, 1: Employee}
 */
function swapEmployee(string $name): array
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::findOrCreate('schedule.swap', 'web');
    $user = User::factory()->create();
    $user->givePermissionTo('schedule.swap');
    $employee = Employee::query()->create(['user_id' => $user->id, 'full_name' => $name, 'employment_status' => 'active']);

    return [$user, $employee];
}

function swapHr(): User
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    foreach (['attendance.view', 'attendance.view.all', 'attendance.update'] as $p) {
        Permission::findOrCreate($p, 'web');
    }
    $user = User::factory()->create();
    $user->givePermissionTo(['attendance.view', 'attendance.view.all', 'attendance.update']);

    return $user;
}

function scheduleRow(Employee $employee, string $date, ?Shift $shift): void
{
    EmployeeSchedule::query()->create([
        'employee_id' => $employee->id, 'work_date' => $date,
        'shift_id' => $shift?->id, 'is_day_off' => $shift === null, 'source' => 'generated',
    ]);
}

test('an employee submits a shift swap and it awaits the partner', function () {
    $pagi = Shift::query()->create(['code' => 'PG', 'name' => 'Pagi', 'start_time' => '07:00', 'end_time' => '15:00', 'is_active' => true]);
    $siang = Shift::query()->create(['code' => 'SG', 'name' => 'Siang', 'start_time' => '15:00', 'end_time' => '23:00', 'is_active' => true]);
    [$user, $me] = swapEmployee('Andi');
    [, $partner] = swapEmployee('Budi');
    $date = now()->addDays(3)->toDateString();
    scheduleRow($me, $date, $pagi);
    scheduleRow($partner, $date, $siang);

    $this->actingAs($user)->post('/my-schedule/swaps', [
        'type' => 'swap',
        'partner_id' => $partner->id,
        'requester_date' => $date,
        'partner_date' => $date,
        'reason' => 'Ada urusan',
    ])->assertRedirect(route('my-schedule.index'));

    expect(ShiftSwapRequest::query()->where('requester_id', $me->id)->where('status', 'pending_partner')->count())->toBe(1);
});

test('a swap on a day with no shift is rejected at submission', function () {
    [$user, $me] = swapEmployee('Andi');
    [, $partner] = swapEmployee('Budi');
    $date = now()->addDays(3)->toDateString();
    // requester has NO schedule that day.

    $this->actingAs($user)
        ->from(route('my-schedule.index'))
        ->post('/my-schedule/swaps', ['type' => 'cover', 'partner_id' => $partner->id, 'requester_date' => $date])
        ->assertSessionHasErrors('partner_id');

    expect(ShiftSwapRequest::query()->count())->toBe(0);
});

test('the partner accepts and it advances to HR; others cannot respond', function () {
    $pagi = Shift::query()->create(['code' => 'PG', 'name' => 'Pagi', 'start_time' => '07:00', 'end_time' => '15:00', 'is_active' => true]);
    [, $me] = swapEmployee('Andi');
    [$partnerUser, $partner] = swapEmployee('Budi');
    [$otherUser] = swapEmployee('Cici');
    $date = now()->addDays(3)->toDateString();
    scheduleRow($me, $date, $pagi);

    $swap = ShiftSwapRequest::query()->create(['requester_id' => $me->id, 'requester_date' => $date, 'partner_id' => $partner->id, 'partner_date' => null, 'type' => 'cover', 'status' => 'pending_partner']);

    $this->actingAs($otherUser)->patch(route('my-schedule.swaps.respond', $swap), ['decision' => 'accept'])->assertForbidden();

    $this->actingAs($partnerUser)->patch(route('my-schedule.swaps.respond', $swap), ['decision' => 'accept'])->assertRedirect();
    expect($swap->fresh()->status)->toBe('pending_hr');
});

test('HR approves a same-day swap and the two schedules are exchanged', function () {
    $pagi = Shift::query()->create(['code' => 'PG', 'name' => 'Pagi', 'start_time' => '07:00', 'end_time' => '15:00', 'is_active' => true]);
    $siang = Shift::query()->create(['code' => 'SG', 'name' => 'Siang', 'start_time' => '15:00', 'end_time' => '23:00', 'is_active' => true]);
    [, $me] = swapEmployee('Andi');
    [, $partner] = swapEmployee('Budi');
    $hr = swapHr();
    $date = now()->addDays(3)->toDateString();
    scheduleRow($me, $date, $pagi);
    scheduleRow($partner, $date, $siang);

    $swap = ShiftSwapRequest::query()->create(['requester_id' => $me->id, 'requester_date' => $date, 'partner_id' => $partner->id, 'partner_date' => $date, 'type' => 'swap', 'status' => 'pending_hr']);

    $this->actingAs($hr)->patch(route('attendance.swaps.approve', $swap))->assertRedirect();

    expect($swap->fresh()->status)->toBe('approved')
        ->and(EmployeeSchedule::query()->where('employee_id', $me->id)->where('work_date', $date)->value('shift_id'))->toBe($siang->id)
        ->and(EmployeeSchedule::query()->where('employee_id', $partner->id)->where('work_date', $date)->value('shift_id'))->toBe($pagi->id)
        ->and(EmployeeSchedule::query()->where('employee_id', $me->id)->where('work_date', $date)->first()->source->value)->toBe('manual');
});

test('HR approves a cover and the partner takes the shift while requester is off', function () {
    $pagi = Shift::query()->create(['code' => 'PG', 'name' => 'Pagi', 'start_time' => '07:00', 'end_time' => '15:00', 'is_active' => true]);
    [, $me] = swapEmployee('Andi');
    [, $partner] = swapEmployee('Budi');
    $hr = swapHr();
    $date = now()->addDays(3)->toDateString();
    scheduleRow($me, $date, $pagi);
    // partner is free that day.

    $swap = ShiftSwapRequest::query()->create(['requester_id' => $me->id, 'requester_date' => $date, 'partner_id' => $partner->id, 'partner_date' => null, 'type' => 'cover', 'status' => 'pending_hr']);

    $this->actingAs($hr)->patch(route('attendance.swaps.approve', $swap))->assertRedirect();

    expect(EmployeeSchedule::query()->where('employee_id', $partner->id)->where('work_date', $date)->value('shift_id'))->toBe($pagi->id)
        ->and((bool) EmployeeSchedule::query()->where('employee_id', $me->id)->where('work_date', $date)->value('is_day_off'))->toBeTrue();
});

test('a cross-date swap that would double-book is rejected', function () {
    $pagi = Shift::query()->create(['code' => 'PG', 'name' => 'Pagi', 'start_time' => '07:00', 'end_time' => '15:00', 'is_active' => true]);
    [$user, $me] = swapEmployee('Andi');
    [, $partner] = swapEmployee('Budi');
    $d1 = now()->addDays(3)->toDateString();
    $d2 = now()->addDays(4)->toDateString();
    scheduleRow($me, $d1, $pagi);
    scheduleRow($me, $d2, $pagi);       // requester already works d2 → double-book
    scheduleRow($partner, $d2, $pagi);

    $this->actingAs($user)
        ->from(route('my-schedule.index'))
        ->post('/my-schedule/swaps', ['type' => 'swap', 'partner_id' => $partner->id, 'requester_date' => $d1, 'partner_date' => $d2])
        ->assertSessionHasErrors('partner_id');
});

test('the self-service and review pages render', function () {
    [$user] = swapEmployee('Andi');
    $this->actingAs($user)->get('/my-schedule')->assertOk();

    $hr = swapHr();
    $this->actingAs($hr)->get('/attendance/swaps')->assertOk();
});
