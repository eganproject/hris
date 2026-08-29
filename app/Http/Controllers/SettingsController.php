<?php

namespace App\Http\Controllers;

use App\Models\SchedulePattern;
use App\Models\Setting;
use App\Services\DefaultOfficeSchedule;
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
        ], [
            'default_office_pattern_id.in' => 'Pola default harus salah satu pola yang dicentang sebagai pola jam kantor.',
        ], [
            'office_pattern_ids' => 'pola jam kantor',
            'default_office_pattern_id' => 'pola default',
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
