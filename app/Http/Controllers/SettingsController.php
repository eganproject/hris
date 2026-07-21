<?php

namespace App\Http\Controllers;

use App\Models\SchedulePattern;
use App\Models\Setting;
use App\Services\DefaultOfficeSchedule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function index(): View
    {
        return view('settings.index', [
            'rosterAutogenerate' => Setting::getBool('roster_autogenerate', true),
            'officePatterns' => SchedulePattern::query()->where('is_active', true)->orderBy('name')->get(),
            'officePatternId' => (int) Setting::get(DefaultOfficeSchedule::SETTING_KEY) ?: null,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'default_office_pattern_id' => ['nullable', 'integer', 'exists:schedule_patterns,id'],
        ]);

        Setting::set('roster_autogenerate', $request->boolean('roster_autogenerate') ? '1' : '0');
        Setting::set(DefaultOfficeSchedule::SETTING_KEY, (string) ($request->integer('default_office_pattern_id') ?: ''));

        return back()->with('status', 'Pengaturan berhasil disimpan.');
    }
}
