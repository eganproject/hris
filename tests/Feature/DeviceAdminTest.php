<?php

use App\Models\Device;
use App\Models\DeviceCommunication;
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
    $permissions = [...attendanceMenuPermissions(), 'attendance.view.all'];

    foreach ($permissions as $p) {
        Permission::findOrCreate($p, 'web');
    }
    $user = User::factory()->create();
    $user->givePermissionTo($permissions);

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

test('iclock interactions are logged and shown on the monitor, marking the device online', function () {
    $user = deviceAdmin();
    $device = Device::query()->create(['serial_number' => 'MON1', 'name' => 'Mesin Lobby', 'is_active' => true]);

    // Public device endpoints (no auth): a handshake and a poll.
    $this->get('/iclock/cdata?SN=MON1')->assertOk();
    $this->get('/iclock/getrequest?SN=MON1')->assertOk();

    expect(DeviceCommunication::query()->where('device_id', $device->id)->count())->toBe(2)
        ->and($device->fresh()->isOnline())->toBeTrue()
        ->and($device->fresh()->status_label)->toBe('Online');

    $this->actingAs($user)->get('/attendance/devices/monitor')
        ->assertOk()
        ->assertSee('Monitor Mesin')
        ->assertSee('Handshake');
});

test('a device with no recent contact is reported offline', function () {
    $device = Device::query()->create(['serial_number' => 'OFF1', 'name' => 'Mesin Lama', 'is_active' => true, 'last_seen_at' => now()->subHour()]);

    expect($device->isOnline())->toBeFalse()
        ->and($device->status_label)->toBe('Offline');
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
