<?php

use App\Models\Device;
use App\Models\Employee;
use App\Models\EmployeeDevice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

function deviceAdmin(): User
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    foreach (['attendance.view', 'attendance.create', 'attendance.update', 'attendance.delete'] as $p) {
        Permission::findOrCreate($p, 'web');
    }
    $user = User::factory()->create();
    $user->givePermissionTo(['attendance.view', 'attendance.create', 'attendance.update', 'attendance.delete']);

    return $user;
}

test('a device can be registered through the form (the flow that previously errored)', function () {
    $user = deviceAdmin();

    $this->actingAs($user)->post('/attendance/devices', [
        'serial_number' => 'X100C-001',
        'name' => 'Mesin Lobby',
        'timezone' => 'Asia/Jakarta',
        'is_active' => '1',
    ])->assertRedirect('/attendance/devices');

    $device = Device::query()->firstWhere('serial_number', 'X100C-001');

    expect($device)->not->toBeNull()
        ->and($device->timezone)->toBe('Asia/Jakarta')
        ->and($device->is_active)->toBeTrue();
});

test('a device can be updated and deleted', function () {
    $user = deviceAdmin();
    $device = Device::query()->create(['serial_number' => 'SN1', 'name' => 'Awal', 'timezone' => 'Asia/Jakarta', 'is_active' => true]);

    $this->actingAs($user)->put("/attendance/devices/{$device->id}", [
        'serial_number' => 'SN1',
        'name' => 'Diperbarui',
        'timezone' => 'Asia/Makassar',
        'is_active' => '1',
    ])->assertRedirect('/attendance/devices');

    expect($device->fresh()->name)->toBe('Diperbarui')
        ->and($device->fresh()->timezone)->toBe('Asia/Makassar');

    $this->actingAs($user)->delete("/attendance/devices/{$device->id}")->assertRedirect('/attendance/devices');
    expect(Device::query()->count())->toBe(0);
});

test('mapping a PIN from the device page enrolls the employee', function () {
    $user = deviceAdmin();
    $device = Device::query()->create(['serial_number' => 'SN2', 'name' => 'Mesin', 'is_active' => true]);
    $employee = Employee::query()->create(['full_name' => 'Budi', 'employment_status' => 'active']);

    $this->actingAs($user)->post("/attendance/devices/{$device->id}/mappings", [
        'employee_id' => $employee->id,
        'machine_user_id' => '21',
    ])->assertRedirect();

    expect(EmployeeDevice::query()->where('device_id', $device->id)->where('machine_user_id', '21')->where('employee_id', $employee->id)->exists())->toBeTrue();
});
