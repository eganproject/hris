<?php

namespace App\Http\Controllers;

use App\Enums\AttendanceStatus;
use App\Exceptions\RemoteAttendanceException;
use App\Http\Requests\SelfAttendanceRequest;
use App\Http\Requests\StoreAttendanceCorrectionRequest;
use App\Models\Attendance;
use App\Models\AttendanceCorrection;
use App\Models\Employee;
use App\Services\RemoteSelfAttendance;
use App\Support\ApprovalNotifier;
use App\Support\PrivateFile;
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
        private readonly RemoteSelfAttendance $remote,
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
     * Only allowed on an approved WFH / business-trip day. Logikanya dipakai bersama
     * aplikasi HP lewat RemoteSelfAttendance.
     */
    public function checkIn(SelfAttendanceRequest $request): RedirectResponse
    {
        try {
            $r = $this->remote->clockIn($this->employee(), ...$this->proof($request));
        } catch (RemoteAttendanceException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Absen masuk '.$r['status']->label().' tercatat pukul '.$r['time'].'.');
    }

    public function checkOut(SelfAttendanceRequest $request): RedirectResponse
    {
        try {
            $r = $this->remote->clockOut($this->employee(), ...$this->proof($request));
        } catch (RemoteAttendanceException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Absen pulang '.$r['status']->label().' tercatat pukul '.$r['time'].'.');
    }

    /** @return array{0: UploadedFile, 1: float, 2: float, 3: ?float} */
    private function proof(SelfAttendanceRequest $request): array
    {
        return [
            $request->file('photo'),
            $request->float('latitude'),
            $request->float('longitude'),
            $request->filled('accuracy') ? $request->float('accuracy') : null,
        ];
    }

    private function activeWorkDate(Employee $employee): Carbon
    {
        return $this->remote->workDate($employee);
    }

    private function remoteStatusOn(Employee $employee, Carbon $workDate): ?AttendanceStatus
    {
        return $this->remote->statusOn($employee, $workDate);
    }

    public function store(StoreAttendanceCorrectionRequest $request): RedirectResponse
    {
        $employee = $this->employee();

        $correction = $employee->attendanceCorrections()->create([
            ...$request->safe()->except('attachment'),
            ...$this->attachmentColumns($employee, $request->file('attachment')),
            'status' => AttendanceCorrection::STATUS_PENDING,
        ]);

        app(ApprovalNotifier::class)->correctionSubmitted($correction);

        return redirect()->route('my-attendance.index')->with('status', 'Pengajuan koreksi absensi terkirim.');
    }

    /**
     * Simpan bukti ke disk privat dan kembalikan kolom-kolomnya, sama seperti lampiran
     * cuti & tukar jadwal: berkas ditaruh per karyawan agar mudah ditelusuri, dengan
     * nama acak dari Laravel — nama asli dari pengguna tidak pernah dipakai sebagai
     * nama berkas di disk.
     *
     * @return array<string, mixed>
     */
    private function attachmentColumns(Employee $employee, ?UploadedFile $attachment): array
    {
        if (! $attachment) {
            return [];
        }

        return [
            'attachment_path' => $attachment->store("correction-attachments/{$employee->id}", AttendanceCorrection::ATTACHMENT_DISK),
            'attachment_name' => $attachment->getClientOriginalName(),
            'attachment_mime' => PrivateFile::detectedMime($attachment),
            'attachment_size' => $attachment->getSize(),
        ];
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
