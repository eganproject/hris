<?php

namespace App\Http\Controllers;

use App\Models\SchedulePattern;
use App\Models\Setting;
use App\Services\DefaultOfficeSchedule;
use App\Services\Mobile\MobileAttendanceService;
use App\Support\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function index(): View
    {
        // Jumlah pemakai ditampilkan agar mencabut tanda dari sebuah pola tidak
        // dilakukan tanpa tahu berapa karyawan yang masih memakainya.
        $patterns = SchedulePattern::query()
            ->where('is_active', true)
            ->withCount('officeEmployees')
            ->orderBy('name')
            ->get();

        return view('settings.index', [
            'rosterAutogenerate' => Setting::getBool('roster_autogenerate', true),
            'officePatterns' => $patterns,
            'officePatternIds' => $patterns->where('is_office_pattern', true)->pluck('id')->all(),
            'officePatternId' => (int) Setting::get(DefaultOfficeSchedule::SETTING_KEY) ?: null,
            'mobileSessionMaxMinutes' => MobileAttendanceService::sessionMaxMinutes(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'office_pattern_ids' => ['array'],
            'office_pattern_ids.*' => ['integer', 'exists:schedule_patterns,id'],
            // Default harus salah satu pola yang dicentang: memilih default di luar
            // daftar kandidat akan membuat dropdown di data karyawan tidak pernah
            // menampilkan pola yang justru dipakai mayoritas.
            'default_office_pattern_id' => ['nullable', 'integer', Rule::in($request->input('office_pattern_ids', []))],
            // Batas sesi absen aplikasi HP (keputusan awal: 23 jam 50 menit).
            'mobile_session_hours' => ['nullable', 'integer', 'min:1', 'max:47'],
            'mobile_session_minutes' => ['nullable', 'integer', 'min:0', 'max:59'],
        ], [
            'default_office_pattern_id.in' => 'Pola default harus salah satu pola yang dicentang sebagai pola jam kantor.',
        ], [
            'office_pattern_ids' => 'pola jam kantor',
            'default_office_pattern_id' => 'pola default',
            'mobile_session_hours' => 'batas sesi (jam)',
            'mobile_session_minutes' => 'batas sesi (menit)',
        ]);

        // Keadaan sebelum disimpan, supaya catatan aktivitasnya bisa menyebut
        // perubahannya — bukan sekadar "pengaturan disimpan".
        $before = [
            'autogenerate' => Setting::getBool('roster_autogenerate', true),
            'patterns' => SchedulePattern::query()->where('is_office_pattern', true)->pluck('id')->all(),
            'default' => (int) Setting::get(DefaultOfficeSchedule::SETTING_KEY) ?: null,
        ];

        Setting::set('roster_autogenerate', $request->boolean('roster_autogenerate') ? '1' : '0');

        $this->syncOfficePatterns(array_map('intval', $validated['office_pattern_ids'] ?? []));

        Setting::set(DefaultOfficeSchedule::SETTING_KEY, (string) ($request->integer('default_office_pattern_id') ?: ''));

        $sessionBefore = MobileAttendanceService::sessionMaxMinutes();
        if ($request->filled('mobile_session_hours')) {
            Setting::set(
                MobileAttendanceService::SESSION_SETTING_KEY,
                (string) ($request->integer('mobile_session_hours') * 60 + $request->integer('mobile_session_minutes')),
            );
        }

        // Setting ditulis lewat Setting::set() dan pola dicentang lewat mass update;
        // keduanya tidak memicu event Eloquent, jadi dicatat di sini secara eksplisit.
        ActivityLogger::log(
            'settings',
            'updated',
            'Menyimpan Pengaturan aplikasi.',
            null,
            ['changes' => [
                'roster_autogenerate' => ['dari' => $before['autogenerate'], 'jadi' => $request->boolean('roster_autogenerate')],
                'pola_jam_kantor' => ['dari' => implode(', ', $before['patterns']) ?: '(kosong)', 'jadi' => implode(', ', array_map('intval', $validated['office_pattern_ids'] ?? [])) ?: '(kosong)'],
                'pola_default' => ['dari' => $before['default'] ?: '(kosong)', 'jadi' => $request->integer('default_office_pattern_id') ?: '(kosong)'],
                'batas_sesi_absen_hp_menit' => ['dari' => $sessionBefore, 'jadi' => MobileAttendanceService::sessionMaxMinutes()],
            ]],
        );

        return back()->with('status', 'Pengaturan berhasil disimpan.');
    }

    /**
     * Tandai pola yang boleh ditawarkan sebagai jam kantor, cabut dari sisanya.
     *
     * Mencabut tanda hanya menghilangkan pola itu dari pilihan ke depan — karyawan
     * yang terlanjur memakainya tetap berjalan, karena resolusi jadwal sengaja tidak
     * melihat tanda ini (lihat DefaultOfficeSchedule).
     *
     * @param  list<int>  $ids
     */
    private function syncOfficePatterns(array $ids): void
    {
        SchedulePattern::query()
            ->where('is_office_pattern', true)
            ->whereNotIn('id', $ids)
            ->update(['is_office_pattern' => false]);

        if ($ids !== []) {
            SchedulePattern::query()->whereIn('id', $ids)->update(['is_office_pattern' => true]);
        }
    }
}
