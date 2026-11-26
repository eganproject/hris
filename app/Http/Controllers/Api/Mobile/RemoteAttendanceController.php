<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Exceptions\RemoteAttendanceException;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Employee;
use App\Services\RemoteSelfAttendance;
use App\Support\UploadMessages;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Absen selfie dari HP, khusus hari kerja jarak jauh (WFH terjadwal, atau WFH/dinas
 * luar yang disetujui) — aturannya sama persis dengan menu web "Absensi Saya".
 * Tanpa geofence: titik lokasinya hanya disimpan. Karyawan kantor tidak mendapat
 * tombol ini; mereka absen di tablet kantor atau mesin gudang.
 */
class RemoteAttendanceController extends Controller
{
    public function __construct(private readonly RemoteSelfAttendance $remote) {}

    public function show(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->state($this->employee($request))]);
    }

    public function store(Request $request): JsonResponse
    {
        $employee = $this->employee($request);

        $data = $request->validate([
            'side' => ['required', 'in:in,out'],
            // Foto dikompres di HP (lebar ±1080 px), jadi 4 MB sudah sangat longgar.
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:4096'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'numeric', 'min:0', 'max:100000'],
        ], [
            ...UploadMessages::photo('photo', 4, 'Foto selfie', ['JPG', 'PNG']),
            'photo.required' => 'Ambil foto selfie dulu sebelum absen.',
            'latitude.required' => 'Lokasi tidak terbaca. Nyalakan GPS dan izinkan akses lokasi, lalu coba lagi.',
            'longitude.required' => 'Lokasi tidak terbaca. Nyalakan GPS dan izinkan akses lokasi, lalu coba lagi.',
        ]);

        $args = [
            $request->file('photo'),
            (float) $data['latitude'],
            (float) $data['longitude'],
            isset($data['accuracy']) ? (float) $data['accuracy'] : null,
        ];

        try {
            $r = $data['side'] === 'in'
                ? $this->remote->clockIn($employee, ...$args)
                : $this->remote->clockOut($employee, ...$args);
        } catch (RemoteAttendanceException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Absen '.($data['side'] === 'in' ? 'masuk' : 'pulang').' '.$r['status']->label().' tercatat pukul '.$r['time'].'.',
            'data' => $this->state($employee),
        ]);
    }

    /** @return array<string, mixed> */
    private function state(Employee $employee): array
    {
        $workDate = $this->remote->workDate($employee);
        $status = $this->remote->statusOn($employee, $workDate);
        /** @var Attendance|null $a */
        $a = $employee->attendances()->whereDate('work_date', $workDate->toDateString())->first();

        return [
            'allowed' => $status !== null,
            'status' => $status?->value,
            'status_label' => $status?->label(),
            'work_date' => $workDate->toDateString(),
            'clock_in' => $a?->clock_in?->toIso8601String(),
            'clock_out' => $a?->clock_out?->toIso8601String(),
            // in = belum absen masuk, out = sudah masuk belum pulang, done = selesai.
            'next' => $status === null ? null : (! $a?->clock_in ? 'in' : (! $a->clock_out ? 'out' : 'done')),
            'server_time' => now()->toIso8601String(),
        ];
    }

    private function employee(Request $request): Employee
    {
        return $request->attributes->get('employee') ?? $request->user()->employee ?? abort(403, 'Akun ini belum terhubung ke data karyawan.');
    }
}
