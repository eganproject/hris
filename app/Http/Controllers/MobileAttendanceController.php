<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\MobileAttendanceLog;
use App\Models\MobileDevice;
use App\Services\Mobile\MobileDeviceRegistry;
use App\Support\ActivityLogger;
use App\Support\DataScope;
use App\Support\PrivateFile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Menu HR "Absen Aplikasi HP": daftar absen dari aplikasi, termasuk yang bertanda
 * (di luar radius, lokasi tidak terbaca, terkirim terlambat, ...) sebagai penanda
 * saja tanpa persetujuan, percobaan yang ditolak sistem (fake GPS, root), dan
 * persetujuan ganti HP.
 */
class MobileAttendanceController extends Controller
{
    public function __construct(private readonly MobileDeviceRegistry $devices) {}

    public function index(Request $request): View
    {
        $tab = $request->string('tab')->toString() ?: 'all';
        if ($tab === 'review') {
            $tab = 'flagged'; // tautan lama
        }
        abort_unless(in_array($tab, ['all', 'flagged', 'rejected'], true), 404);

        $scope = DataScope::forAttendance($request->user());
        $search = $request->string('search')->toString() ?: null;
        $date = $request->string('date')->toString() ?: null;

        $logs = MobileAttendanceLog::query()
            ->with(['employee', 'branch', 'device'])
            ->tap(fn ($query) => $scope->constrain($query))
            ->when($tab === 'flagged', fn ($q) => $q->where('result', MobileAttendanceLog::RESULT_ACCEPTED)->whereNotNull('flags'))
            ->when($tab === 'rejected', fn ($q) => $q->where('result', MobileAttendanceLog::RESULT_REJECTED))
            ->when($search, fn ($q, $s) => $q->whereHas('employee', fn ($e) => $e
                ->where('full_name', 'like', "%{$s}%")
                ->orWhere('employee_number', 'like', "%{$s}%")))
            ->when($date, fn ($q, $d) => $q->whereDate('captured_at', $d))
            ->latest('captured_at')
            ->paginate(20)
            ->withQueryString();

        return view('attendance.mobile.index', [
            'logs' => $logs,
            'tab' => $tab,
            'filters' => compact('search', 'date'),
            'pendingDevices' => MobileDevice::query()
                ->where('status', MobileDevice::STATUS_PENDING)
                ->tap(fn ($query) => $scope->constrain($query))
                ->count(),
        ]);
    }

    public function photo(Request $request, MobileAttendanceLog $log): StreamedResponse
    {
        DataScope::forAttendance($request->user())->authorize($log->employee);

        $disk = Storage::disk(Attendance::SELFIE_DISK);
        abort_unless($log->photo_path && $disk->exists($log->photo_path), 404, 'Foto tidak ditemukan.');

        return PrivateFile::response($disk, $log->photo_path, 'selfie-'.$log->id.'.jpg', 'image/jpeg', false);
    }

    public function devices(Request $request): View
    {
        $status = $request->string('status')->toString() ?: MobileDevice::STATUS_PENDING;
        $scope = DataScope::forAttendance($request->user());
        $search = $request->string('search')->toString() ?: null;

        $devices = MobileDevice::query()
            ->with(['employee.branch', 'decider'])
            ->tap(fn ($query) => $scope->constrain($query))
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($search, fn ($q, $s) => $q->whereHas('employee', fn ($e) => $e
                ->where('full_name', 'like', "%{$s}%")
                ->orWhere('employee_number', 'like', "%{$s}%")))
            ->latest('updated_at')
            ->paginate(20)
            ->withQueryString();

        return view('attendance.mobile.devices', [
            'devices' => $devices,
            'status' => $status,
            'filters' => compact('search'),
            'pendingDevices' => MobileDevice::query()->where('status', MobileDevice::STATUS_PENDING)->tap(fn ($query) => $scope->constrain($query))->count(),
            'activeFor' => MobileDevice::query()
                ->active()
                ->whereIn('employee_id', $devices->pluck('employee_id'))
                ->get()
                ->keyBy('employee_id'),
        ]);
    }

    public function decideDevice(Request $request, MobileDevice $device): RedirectResponse
    {
        DataScope::forAttendance($request->user())->authorize($device->employee);

        $data = $request->validate([
            'action' => ['required', 'in:approve,reject,revoke'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        match ($data['action']) {
            'approve' => abort_unless(in_array($device->status, [MobileDevice::STATUS_PENDING, MobileDevice::STATUS_REJECTED], true), 422),
            'reject' => abort_unless($device->status === MobileDevice::STATUS_PENDING, 422),
            'revoke' => abort_unless($device->status === MobileDevice::STATUS_ACTIVE, 422),
        };

        $actor = $request->user();

        match ($data['action']) {
            'approve' => $this->devices->approve($device, $actor, $data['note'] ?? null),
            'reject' => $this->devices->reject($device, $actor, $data['note'] ?? null),
            'revoke' => $this->devices->revoke($device, $actor, $data['note'] ?? null),
        };

        $verb = ['approve' => 'Menyetujui', 'reject' => 'Menolak', 'revoke' => 'Mencabut'][$data['action']];
        ActivityLogger::log('mobile-attendance', 'device-'.$data['action'], "{$verb} HP {$device->label} milik {$device->employee?->full_name}.", $device);

        return back()->with('status', [
            'approve' => 'HP disetujui. HP lama karyawan ini otomatis dicabut.',
            'reject' => 'Permintaan HP ditolak.',
            'revoke' => 'HP dicabut. Karyawan harus masuk lagi dari HP yang disetujui.',
        ][$data['action']]);
    }
}
