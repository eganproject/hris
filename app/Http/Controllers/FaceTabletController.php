<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\AttendanceTablet;
use App\Models\Employee;
use App\Models\EmployeeFaceProfile;
use App\Models\Setting;
use App\Models\TabletAttendanceLog;
use App\Services\Tablet\TabletSettings;
use App\Support\ActivityLogger;
use App\Support\DataScope;
use App\Support\PrivateFile;
use App\Support\TabletPin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Menu HR "Absensi > Tablet Absensi Wajah": daftar tablet per Lokasi Kerja,
 * data wajah + PIN cadangan karyawan, pengaturan pengenalan wajah, dan riwayat
 * absen dari tablet (dengan foto bukti dan tanda untuk HR).
 */
class FaceTabletController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $scope = DataScope::forAttendance($user);
        $branchIds = $scope->isUnrestricted() ? null : $scope->branches()->pluck('id')->all();

        $search = $request->string('search')->toString() ?: null;
        $branch = $request->integer('branch') ?: null;
        $face = $request->string('face')->toString() ?: null;

        $employees = $scope->employees()
            ->active()
            ->with(['branch', 'jobPosition', 'faceProfile.enrolledTablet'])
            ->when($search, fn ($q, $s) => $q->where(fn ($w) => $w
                ->where('full_name', 'like', "%{$s}%")
                ->orWhere('employee_number', 'like', "%{$s}%")))
            ->when($branch, fn ($q, $b) => $q->where('branch_id', $b))
            ->when($face === 'registered', fn ($q) => $q->whereHas('faceProfile', fn ($p) => $p->whereNotNull('embeddings')))
            ->when($face === 'missing', fn ($q) => $q->whereDoesntHave('faceProfile', fn ($p) => $p->whereNotNull('embeddings')))
            ->orderBy('full_name')
            ->paginate(15, ['*'], 'employees_page')
            ->withQueryString();

        $tablets = AttendanceTablet::query()
            ->with('branch')
            ->when($branchIds !== null, fn ($q) => $q->whereIn('branch_id', $branchIds))
            ->orderByRaw("CASE status WHEN 'revoked' THEN 1 ELSE 0 END")
            ->orderBy('name')
            ->get();

        $today = now()->startOfDay();
        $todayLogs = TabletAttendanceLog::query()
            ->where('result', TabletAttendanceLog::RESULT_ACCEPTED)
            ->where('captured_at', '>=', $today)
            ->tap(fn ($q) => $scope->constrain($q));

        $tabletBranchIds = $tablets->where('status', '!=', AttendanceTablet::STATUS_REVOKED)->pluck('branch_id')->unique();

        return view('attendance.tablets.index', [
            'tab' => 'overview',
            'tablets' => $tablets,
            'employees' => $employees,
            'branches' => $scope->branches(),
            'filters' => compact('search', 'branch', 'face'),
            'stats' => [
                'today' => (clone $todayLogs)->distinct()->count('employee_id'),
                'pin' => (clone $todayLogs)->where('method', 'pin')->count(),
                'missing' => $scope->employees()->active()
                    ->whereIn('branch_id', $tabletBranchIds)
                    ->whereDoesntHave('faceProfile', fn ($p) => $p->whereNotNull('embeddings'))
                    ->count(),
            ],
            'settings' => [
                'liveness' => TabletSettings::livenessEnabled(),
                'level' => TabletSettings::matchLevel(),
                'pin' => TabletSettings::pinEnabled(),
            ],
            'pairing' => session('pairing'),
        ]);
    }

    public function logs(Request $request): View
    {
        $tab = $request->string('tab')->toString() ?: 'all';
        abort_unless(in_array($tab, ['all', 'flagged', 'other'], true), 404);

        $scope = DataScope::forAttendance($request->user());
        $search = $request->string('search')->toString() ?: null;
        $date = $request->string('date')->toString() ?: null;

        $logs = TabletAttendanceLog::query()
            ->with(['employee', 'tablet', 'branch'])
            ->tap(fn ($query) => $scope->constrain($query))
            ->when($tab === 'all', fn ($q) => $q->where('result', TabletAttendanceLog::RESULT_ACCEPTED))
            ->when($tab === 'flagged', fn ($q) => $q->where('result', TabletAttendanceLog::RESULT_ACCEPTED)->whereNotNull('flags'))
            ->when($tab === 'other', fn ($q) => $q->where('result', '!=', TabletAttendanceLog::RESULT_ACCEPTED))
            ->when($search, fn ($q, $s) => $q->whereHas('employee', fn ($e) => $e
                ->where('full_name', 'like', "%{$s}%")
                ->orWhere('employee_number', 'like', "%{$s}%")))
            ->when($date, fn ($q, $d) => $q->whereDate('captured_at', $d))
            ->latest('captured_at')
            ->paginate(25)
            ->withQueryString();

        return view('attendance.tablets.logs', [
            'tab' => $tab,
            'logs' => $logs,
            'filters' => compact('search', 'date'),
        ]);
    }

    public function storeTablet(Request $request): RedirectResponse
    {
        $scope = DataScope::forAttendance($request->user());

        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'branch_id' => ['required', 'integer', Rule::in($scope->branches()->pluck('id')->all())],
        ], [], ['name' => 'nama tablet', 'branch_id' => 'lokasi kerja']);

        $tablet = AttendanceTablet::query()->create([
            'name' => $data['name'],
            'branch_id' => $data['branch_id'],
            'status' => AttendanceTablet::STATUS_PENDING,
            'created_by' => $request->user()->id,
        ]);

        $code = $tablet->issuePairingCode();
        ActivityLogger::log('face-tablets', 'create', "Mendaftarkan tablet {$tablet->name}.", $tablet);

        return redirect()->route('attendance.tablets.index')
            ->with('status', "Tablet {$tablet->name} didaftarkan. Masukkan kode pasangan di tablet, lalu atur PIN HR-nya.")
            ->with('pairing', ['tablet' => $tablet->name, 'code' => $code, 'expires' => $tablet->pairing_expires_at->format('H:i')]);
    }

    public function pairingCode(Request $request, AttendanceTablet $tablet): RedirectResponse
    {
        $this->authorizeTablet($request, $tablet);
        abort_if($tablet->status === AttendanceTablet::STATUS_REVOKED, 422, 'Tablet yang dicabut tidak bisa dipasangkan lagi. Daftarkan tablet baru.');

        $code = $tablet->issuePairingCode();
        ActivityLogger::log('face-tablets', 'pairing-code', "Membuat kode pasangan baru untuk tablet {$tablet->name}.", $tablet);

        return back()
            ->with('status', 'Kode pasangan baru dibuat. Bila tablet ini sudah terpasang, ia tetap jalan sampai kode dipakai di tablet lain.')
            ->with('pairing', ['tablet' => $tablet->name, 'code' => $code, 'expires' => $tablet->pairing_expires_at->format('H:i')]);
    }

    public function hrPin(Request $request, AttendanceTablet $tablet): RedirectResponse
    {
        $this->authorizeTablet($request, $tablet);

        $request->validate(['pin' => [...TabletPin::rules(), 'confirmed']], [
            'pin.regex' => 'PIN harus 6 angka.',
        ], ['pin' => 'PIN HR']);

        $tablet->forceFill(['hr_pin_hash' => TabletPin::hash($request->string('pin')->toString())])->save();
        ActivityLogger::log('face-tablets', 'hr-pin', "Mengatur PIN HR tablet {$tablet->name}.", $tablet);

        return back()->with('status', "PIN HR tablet {$tablet->name} disimpan. Tablet memakainya setelah sinkron berikutnya (paling lama 1 menit).");
    }

    public function revoke(Request $request, AttendanceTablet $tablet): RedirectResponse
    {
        $this->authorizeTablet($request, $tablet);

        DB::transaction(function () use ($tablet) {
            $tablet->tokens()->delete();
            $tablet->forceFill([
                'status' => AttendanceTablet::STATUS_REVOKED,
                'revoked_at' => now(),
                'pairing_code_hash' => null,
                'pairing_expires_at' => null,
            ])->save();
        });

        ActivityLogger::log('face-tablets', 'revoke', "Mencabut tablet {$tablet->name}.", $tablet);

        return back()->with('status', "Tablet {$tablet->name} dicabut. Absen yang masih tertahan di tablet itu tidak bisa dikirim lagi.");
    }

    public function facePhoto(Request $request, Employee $employee): StreamedResponse
    {
        DataScope::forAttendance($request->user())->authorize($employee);

        $path = $employee->faceProfile?->photo_path;
        $disk = Storage::disk(Attendance::SELFIE_DISK);
        abort_unless($path && $disk->exists($path), 404, 'Foto wajah tidak ditemukan.');

        return PrivateFile::response($disk, $path, 'wajah-'.$employee->id.'.jpg', 'image/jpeg', false);
    }

    public function destroyFace(Request $request, Employee $employee): RedirectResponse
    {
        DataScope::forAttendance($request->user())->authorize($employee);

        $profile = $employee->faceProfile;
        abort_unless($profile?->hasFace(), 404);

        if ($profile->photo_path) {
            Storage::disk(Attendance::SELFIE_DISK)->delete($profile->photo_path);
        }

        $profile->forceFill([
            'embeddings' => null,
            'embedding_model' => null,
            'embedding_dim' => null,
            'photo_path' => null,
            'consented_at' => null,
            'enrolled_at' => null,
            'enrolled_tablet_id' => null,
            'enrolled_by' => null,
        ])->save();

        ActivityLogger::log('face-tablets', 'face-delete', "Menghapus data wajah {$employee->full_name}.", $employee);

        return back()->with('status', "Data wajah {$employee->full_name} dihapus. Tablet melupakannya setelah sinkron berikutnya.");
    }

    public function employeePin(Request $request, Employee $employee): RedirectResponse
    {
        DataScope::forAttendance($request->user())->authorize($employee);

        if ($request->boolean('clear')) {
            $employee->faceProfile?->forceFill(['pin_hash' => null, 'pin_set_at' => null])->save();
            ActivityLogger::log('face-tablets', 'employee-pin-clear', "Menghapus PIN absen {$employee->full_name}.", $employee);

            return back()->with('status', "PIN absen {$employee->full_name} dihapus.");
        }

        $request->validate(['pin' => TabletPin::rules()], ['pin.regex' => 'PIN harus 6 angka.'], ['pin' => 'PIN']);

        $profile = EmployeeFaceProfile::query()->firstOrNew(['employee_id' => $employee->id]);
        $profile->forceFill(['pin_hash' => TabletPin::hash($request->string('pin')->toString()), 'pin_set_at' => now()])->save();

        ActivityLogger::log('face-tablets', 'employee-pin', "Mengatur PIN absen {$employee->full_name}.", $employee);

        return back()->with('status', "PIN absen {$employee->full_name} disimpan. Berikan PIN ini langsung ke karyawannya.");
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'liveness' => ['required', 'boolean'],
            'level' => ['required', Rule::in(array_keys(TabletSettings::MATCH_LEVELS))],
            'pin' => ['required', 'boolean'],
        ]);

        Setting::set(TabletSettings::LIVENESS_KEY, $data['liveness'] ? '1' : '0');
        Setting::set(TabletSettings::MATCH_LEVEL_KEY, $data['level']);
        Setting::set(TabletSettings::PIN_KEY, $data['pin'] ? '1' : '0');

        ActivityLogger::log('face-tablets', 'settings', 'Mengubah pengaturan pengenalan wajah tablet.', null, $data);

        return back()->with('status', 'Pengaturan disimpan. Semua tablet memakainya setelah sinkron berikutnya (paling lama 1 menit).');
    }

    public function logPhoto(Request $request, TabletAttendanceLog $log): StreamedResponse
    {
        DataScope::forAttendance($request->user())->authorize($log->employee);

        $disk = Storage::disk(Attendance::SELFIE_DISK);
        abort_unless($log->photo_path && $disk->exists($log->photo_path), 404, 'Foto tidak ditemukan.');

        return PrivateFile::response($disk, $log->photo_path, 'absen-tablet-'.$log->id.'.jpg', 'image/jpeg', false);
    }

    private function authorizeTablet(Request $request, AttendanceTablet $tablet): void
    {
        $scope = DataScope::forAttendance($request->user());

        abort_unless($scope->isUnrestricted() || $scope->branches()->contains('id', $tablet->branch_id), 403);
    }
}
