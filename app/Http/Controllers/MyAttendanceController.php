<?php

namespace App\Http\Controllers;

use App\Enums\AttendanceStatus;
use App\Http\Requests\SelfAttendanceRequest;
use App\Http\Requests\StoreAttendanceCorrectionRequest;
use App\Models\Attendance;
use App\Models\AttendanceCorrection;
use App\Models\Employee;
use App\Services\AttendanceResolver;
use App\Services\AttendanceRollup;
use App\Support\ApprovalNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MyAttendanceController extends Controller
{
    public function __construct(
        private readonly AttendanceResolver $resolver,
        private readonly AttendanceRollup $rollup,
    ) {}

    /**
     * The employee's own attendance history plus their correction requests.
     */
    public function index(): View
    {
        $employee = $this->employee();
        $workDate = $this->activeWorkDate($employee);

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
            // Absen mandiri: hanya ditawarkan saat shift yang sedang berjalan memang
            // kerja jarak jauh (WFH terjadwal/disetujui, atau dinas luar disetujui).
            'remoteStatus' => $remote = $this->remoteStatusOn($employee, $workDate),
            'remoteWorkDate' => $workDate,
            'remoteAttendance' => $employee->attendances()->whereDate('work_date', $workDate->toDateString())->first(),
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
        $path = $photo->storeAs('attendance/selfie-tests', $request->user()->id.'.jpg', Attendance::SELFIE_DISK);

        return back()->with('selfie_test', [
            // Nama berkasnya tetap, jadi tambahkan penanda versi agar browser tidak
            // menampilkan foto uji sebelumnya dari cache.
            'photo_url' => route('my-attendance.selfie-test.show').'?v='.now()->timestamp,
            'photo_kb' => $sizeKb,
            'latitude' => $request->float('latitude'),
            'longitude' => $request->float('longitude'),
            'accuracy' => $request->filled('accuracy') ? (int) round($request->float('accuracy')) : null,
            'tested_at' => now()->format('H:i:s'),
        ]);
    }

    /**
     * Menyajikan foto hasil uji coba superadmin. Tiap superadmin hanya bisa melihat
     * berkas miliknya sendiri, dan berkasnya ada di disk privat seperti selfie absensi.
     */
    public function selfieTestShow(Request $request): StreamedResponse
    {
        abort_unless($request->user()->isSuperAdmin(), 403);

        $disk = Storage::disk(Attendance::SELFIE_DISK);
        $path = 'attendance/selfie-tests/'.$request->user()->id.'.jpg';

        abort_unless($disk->exists($path), 404);

        return $disk->response($path, 'uji-selfie.jpg', ['Content-Type' => 'image/jpeg']);
    }

    /**
     * Record today's clock-in straight into the attendance row (no machine needed away
     * from the office), together with the selfie and coordinates taken at that moment.
     * Only allowed on an approved WFH / business-trip day.
     */
    public function checkIn(SelfAttendanceRequest $request): RedirectResponse
    {
        $employee = $this->employee();
        $workDate = $this->activeWorkDate($employee);
        $status = $this->remoteStatusOn($employee, $workDate);

        if (! $status) {
            return back()->with('error', 'Absen mandiri hanya untuk hari WFH atau dinas luar yang sudah disetujui.');
        }

        $now = now();
        $existing = $employee->attendances()->whereDate('work_date', $workDate->toDateString())->first();

        if ($existing?->clock_in) {
            return back()->with('error', 'Anda sudah absen masuk untuk '.$this->shiftLabel($workDate).' pukul '.$existing->clock_in->format('H:i').'.');
        }

        // Jamnya diambil dari server dan disimpan sebagai "H:i" pada work_date shift
        // ini. Untuk shift malam, jam pulang yang lebih awal dari jam masuk digulirkan
        // ke hari berikutnya oleh AttendanceResolver, jadi rentang kerjanya tetap utuh.
        $attendance = $this->resolver->resolve($employee, $workDate, $now->format('H:i'), $existing?->clock_out?->format('H:i'), $existing?->note);
        $this->attachProof($attendance, 'in', $request);

        return back()->with('status', 'Absen masuk '.$status->label().' tercatat pukul '.$now->format('H:i').'.');
    }

    public function checkOut(SelfAttendanceRequest $request): RedirectResponse
    {
        $employee = $this->employee();
        $workDate = $this->activeWorkDate($employee);
        $status = $this->remoteStatusOn($employee, $workDate);

        if (! $status) {
            return back()->with('error', 'Absen mandiri hanya untuk hari WFH atau dinas luar yang sudah disetujui.');
        }

        $now = now();
        $existing = $employee->attendances()->whereDate('work_date', $workDate->toDateString())->first();

        if (! $existing?->clock_in) {
            return back()->with('error', 'Absen masuk dulu sebelum absen pulang.');
        }

        if ($existing->clock_out) {
            return back()->with('error', 'Anda sudah absen pulang untuk '.$this->shiftLabel($workDate).' pukul '.$existing->clock_out->format('H:i').'.');
        }

        $attendance = $this->resolver->resolve($employee, $workDate, $existing->clock_in->format('H:i'), $now->format('H:i'), $existing->note);
        $this->attachProof($attendance, 'out', $request);

        return back()->with('status', 'Absen pulang '.$status->label().' tercatat pukul '.$now->format('H:i').'.');
    }

    /**
     * Tanggal kerja yang sedang dijalani karyawan saat ini. Untuk shift lintas tengah
     * malam ini adalah tanggal KEMARIN sampai shiftnya berakhir: absen pulang pukul
     * 06:00 adalah milik shift yang dimulai kemarin pukul 22:00.
     *
     * Sebelumnya semuanya memakai now()->toDateString(), sehingga begitu lewat tengah
     * malam panel absennya hilang dan absen pulang ditolak — malam kerjanya berhenti
     * di jam masuk saja dan tercatat nol jam.
     */
    private function activeWorkDate(Employee $employee): Carbon
    {
        return $this->rollup->workDateFor($employee, now());
    }

    /**
     * Penyebut tanggal shift untuk pesan, supaya "sudah absen masuk" pada shift malam
     * tidak berbunyi "hari ini" saat jamnya sudah lewat tengah malam.
     */
    private function shiftLabel(Carbon $workDate): string
    {
        return $workDate->isSameDay(now()) ? 'hari ini' : 'shift '.$workDate->translatedFormat('d M Y');
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

        $path = $photo->store('attendance/selfies/'.$attendance->work_date->format('Y/m'), Attendance::SELFIE_DISK);

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
            Storage::disk(Attendance::SELFIE_DISK)->delete($old);
        }
    }

    /**
     * Pada tanggal kerja itu karyawan bekerja jarak jauh dengan status apa — WFH dari
     * roster (hari WFH terjadwal), atau WFH/dinas luar dari pengajuan yang disetujui?
     * Null berarti hari kantor biasa, absen mandiri tidak berlaku.
     */
    private function remoteStatusOn(Employee $employee, Carbon $workDate): ?AttendanceStatus
    {
        $date = $workDate->toDateString();

        $scheduled = $employee->schedules()
            ->whereDate('work_date', $date)
            ->where('is_wfh', true)
            ->exists();

        if ($scheduled) {
            return AttendanceStatus::Wfh;
        }

        $remoteValues = array_map(fn (AttendanceStatus $s) => $s->value, AttendanceResolver::REMOTE_STATUSES);

        $leave = $employee->leaveRequests()
            ->approvedOn($date)
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
