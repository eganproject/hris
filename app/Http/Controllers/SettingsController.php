<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function index(): View
    {
        return view('settings.index', [
            'rosterAutogenerate' => Setting::getBool('roster_autogenerate', true),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        Setting::set('roster_autogenerate', $request->boolean('roster_autogenerate') ? '1' : '0');

        return back()->with('status', 'Pengaturan berhasil disimpan.');
    }
}
