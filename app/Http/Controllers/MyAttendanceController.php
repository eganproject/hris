<?php

namespace App\Http\Controllers;

use App\Enums\AttendanceStatus;
use App\Http\Requests\SelfAttendanceRequest;
use App\Http\Requests\StoreAttendanceCorrectionRequest;
use App\Models\Attendance;
use App\Models\AttendanceCorrection;
use App\Models\Employee;
use App\Services\AttendanceResolver;
use App\Support\ApprovalNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class MyAttendanceController extends Controller
{
    public function __construct(private readonly AttendanceResolver $resolver) {}

    /**
     * The employee's own attendance history plus their correction requests.
     */
    public function index(): View
    {
        $employee = $this->employee();

        return view('attendance.my-attendance.index', [
            'attendances' => $employee->attendances()
                ->whereDate('work_date', '>=', now()->subDays(30)->toDateString())
                ->with('shift')
                ->orderByDesc('work_date')
                ->get(),
            'corrections' => $employee->attendanceCorrections()
                ->with('reviewer')
                ->latest('id')
                ->get(),
            // Absen mandiri: hanya ditawarkan saat hari ini memang hari kerja jarak
            // jauh (WFH terjadwal/disetujui, atau dinas luar disetujui).
            'remoteToday' => $remote = $this->remoteStatusToday($employee),
            'todayAttendance' => $employee->attendances()->whereDate('work_date', now()->toDateString())->first(),
            // Superadmin boleh mencoba alat absennya di hari biasa untuk memastikan
            // kamera & lokasi berfungsi. Pada hari WFH/dinas luar tidak perlu — panel
            // absen sungguhannya sudah muncul.
            'selfieTestMode' => ! $remote && (bool) auth()->user()?->isSuperAdmin(),
        ]);
    }

    /**
     * Uji coba alat absen selfie untuk superadmin: memvalidasi foto dan koordinat
     * persis seperti absen sungguhan, lalu memantulkan hasilnya kembali ke layar.
     * Sengaja TIDAK menulis baris absensi apa pun — ini cuma pemeriksaan perangkat.
     */
    public function selfieTest(SelfAttendanceRequest $request): RedirectResponse
    {
        abort_unless($request->user()->isSuperAdmin(), 403);

        /** @var UploadedFile $photo */
        $photo = $request->file('photo');
        $sizeKb = (int) round($photo->getSize() / 1024); // dibaca sebelum berkas dipindah

        // Satu berkas per superadmin, ditimpa tiap kali diuji — hasil uji lama tidak
        // perlu disimpan dan tidak boleh menumpuk di storage.
        $path = $photo->storeAs('attendance/selfie-tests', $request->user()->id.'.jpg', 'public');

        return back()->with('selfie_test', [
            // Nama berkasnya tetap, jadi tambahkan penanda versi agar browser tidak
            // menampilkan foto uji sebelumnya dari cache.
            'photo_url' => Storage::disk('public')->url($path).'?v='.now()->timestamp,
            'photo_kb' => $sizeKb,
            'latitude' => $request->float('latitude'),
            'longitude' => $request->float('longitude'),
            'accuracy' => $request->filled('accuracy') ? (int) round($request->float('accuracy')) : null,
            'tested_at' => now()->format('H:i:s'),
        ]);
    }

    /**
     * Record today's clock-in straight into the attendance row (no machine needed away
     * from the office), together with the selfie and coordinates taken at that moment.
     * Only allowed on an approved WFH / business-trip day.
     */
    public function checkIn(SelfAttendanceRequest $request): RedirectResponse
    {
        $employee = $this->employee();
        $status = $this->remoteStatusToday($employee);

        if (! $status) {
            return back()->with('error', 'Absen mandiri hanya untuk hari WFH atau dinas luar yang sudah disetujui.');
        }

        $today = now();
        $existing = $employee->attendances()->whereDate('work_date', $today->toDateString())->first();

        if ($existing?->clock_in) {
            return back()->with('error', 'Anda sudah absen masuk hari ini pukul '.$existing->clock_in->format('H:i').'.');
        }

        $attendance = $this->resolver->resolve($employee, $today, $today->format('H:i'), $existing?->clock_out?->format('H:i'), $existing?->note);
        $this->attachProof($attendance, 'in', $request);

        return back()->with('status', 'Absen masuk '.$status->label().' tercatat pukul '.$today->format('H:i').'.');
    }

    public function checkOut(SelfAttendanceRequest $request): RedirectResponse
    {
        $employee = $this->employee();
        $status = $this->remoteStatusToday($employee);

        if (! $status) {
            return back()->with('error', 'Absen mandiri hanya untuk hari WFH atau dinas luar yang sudah disetujui.');
        }

        $today = now();
        $existing = $employee->attendances()->whereDate('work_date', $today->toDateString())->first();

        if (! $existing?->clock_in) {
            return back()->with('error', 'Absen masuk dulu sebelum absen pulang.');
        }

        if ($existing->clock_out) {
            return back()->with('error', 'Anda sudah absen pulang hari ini pukul '.$existing->clock_out->format('H:i').'.');
        }

        $attendance = $this->resolver->resolve($employee, $today, $existing->clock_in->format('H:i'), $today->format('H:i'), $existing->note);
        $this->attachProof($attendance, 'out', $request);

        return back()->with('status', 'Absen pulang '.$status->label().' tercatat pukul '.$today->format('H:i').'.');
    }

    /**
     * Simpan selfie + koordinat ke baris absensi yang baru saja di-resolve. Kolom ini
     * tidak ikut dihitung resolver, jadi aman dari penulisan ulang saat hari itu
     * diproses ulang oleh HR.
     */
    private function attachProof(Attendance $attendance, string $side, SelfAttendanceRequest $request): void
    {
        /** @var UploadedFile $photo */
        $photo = $request->file('photo');

        $path = $photo->store('attendance/selfies/'.$attendance->work_date->format('Y/m'), 'public');

        $old = $attendance->{"clock_{$side}_photo_path"};

        $attendance->forceFill([
            "clock_{$side}_photo_path" => $path,
            "clock_{$side}_latitude" => $request->float('latitude'),
            "clock_{$side}_longitude" => $request->float('longitude'),
            "clock_{$side}_accuracy_m" => $request->filled('accuracy') ? (int) round($request->float('accuracy')) : null,
        ])->save();

        // Tidak seharusnya terjadi (absen dua kali sudah ditolak di atas), tapi jangan
        // sampai file yatim menumpuk kalau toh terjadi.
        if ($old && $old !== $path) {
            Storage::disk('public')->delete($old);
        }
    }

    /**
     * Hari ini karyawan bekerja jarak jauh dengan status apa — WFH dari roster (hari
     * WFH terjadwal), atau WFH/dinas luar dari pengajuan yang disetujui? Null berarti
     * hari kantor biasa, absen mandiri tidak berlaku.
     */
    private function remoteStatusToday(Employee $employee): ?AttendanceStatus
    {
        $today = now()->toDateString();

        $scheduled = $employee->schedules()
            ->whereDate('work_date', $today)
            ->where('is_wfh', true)
            ->exists();

        if ($scheduled) {
            return AttendanceStatus::Wfh;
        }

        $remoteValues = array_map(fn (AttendanceStatus $s) => $s->value, AttendanceResolver::REMOTE_STATUSES);

        $leave = $employee->leaveRequests()
            ->approvedOn($today)
            ->whereHas('leaveType', fn ($query) => $query->whereIn('attendance_status', $remoteValues))
            ->with('leaveType')
            ->first();

        return $leave?->leaveType?->attendance_status;
    }

    public function store(StoreAttendanceCorrectionRequest $request): RedirectResponse
    {
        $correction = $this->employee()->attendanceCorrections()->create([
            ...$request->validated(),
            'status' => AttendanceCorrection::STATUS_PENDING,
        ]);

        app(ApprovalNotifier::class)->correctionSubmitted($correction);

        return redirect()->route('my-attendance.index')->with('status', 'Pengajuan koreksi absensi terkirim.');
    }

    public function cancel(AttendanceCorrection $correction): RedirectResponse
    {
        abort_unless($correction->employee_id === $this->employee()->id && $correction->isPending(), 403);

        // HR tidak perlu lagi memutuskannya — beri tahu sebelum datanya hilang.
        app(ApprovalNotifier::class)->correctionCancelled($correction);

        $correction->delete();

        return redirect()->route('my-attendance.index')->with('status', 'Pengajuan koreksi dibatalkan.');
    }

    private function employee(): Employee
    {
        $employee = auth()->user()->employee;

        abort_unless($employee, 403, 'Akun Anda belum tertaut ke data karyawan.');

        return $employee;
    }
}
