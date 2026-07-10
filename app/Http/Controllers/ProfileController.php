<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Self-service account & profile for the signed-in user: view own data, update
 * personal contact fields (phone/address), and change own password. Sensitive
 * fields (name, placement, contract, login email) stay HR-managed.
 */
class ProfileController extends Controller
{
    public function edit(): View
    {
        return view('profile.edit', [
            'user' => auth()->user(),
            'employee' => auth()->user()->employee?->load(['branch', 'department', 'jobPosition', 'currentContract']),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $employee = auth()->user()->employee;

        abort_unless($employee, 403, 'Akun Anda belum tertaut ke data karyawan.');

        $data = $request->validateWithBag('updateProfile', [
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:1000'],
        ]);

        $employee->update($data);

        return back()->with('status', 'profile-updated');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $request->validateWithBag('updatePassword', [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        // The User model casts `password` to "hashed", so this is stored hashed.
        auth()->user()->update(['password' => $request->input('password')]);

        return back()->with('status', 'password-updated');
    }
}
