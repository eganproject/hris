<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Self-service account & profile for the signed-in user: view own data, update
 * personal contact fields (phone/address), and change own password. Sensitive
 * fields (name, placement, contract, login email) stay HR-managed. Foto profil
 * boleh diganti sendiri — aturannya sama persis dengan yang dipakai HR di form
 * karyawan, supaya standar fotonya tidak berbeda tergantung siapa yang mengunggah.
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
        $employee = $this->employee();

        $data = $request->validateWithBag('updateProfile', [
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:1000'],
        ]);

        $employee->update($data);

        return back()->with('status', 'profile-updated');
    }

    /**
     * Ganti foto profil sendiri. Foto lama dihapus dari disk supaya tidak menumpuk.
     */
    public function updatePhoto(Request $request): RedirectResponse
    {
        $employee = $this->employee();

        $request->validateWithBag('updatePhoto', [
            // Disamakan dengan EmployeeRequest: satu standar foto untuk seluruh sistem.
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:min_width=300,min_height=300,max_width=3000,max_height=3000'],
        ], [
            'photo.required' => 'Pilih berkas fotonya dulu.',
            'photo.max' => 'Ukuran foto maksimal 2 MB.',
            'photo.mimes' => 'Foto harus berformat JPG, PNG, atau WebP.',
            'photo.dimensions' => 'Resolusi foto minimal 300x300 px dan maksimal 3000x3000 px.',
        ]);

        $previous = $employee->photo_path;

        $employee->update([
            'photo_path' => $request->file('photo')->store('employees/photos', 'public'),
        ]);

        $this->forget($previous, $employee->photo_path);

        return back()->with('status', 'photo-updated');
    }

    public function destroyPhoto(): RedirectResponse
    {
        $employee = $this->employee();
        $previous = $employee->photo_path;

        if (! $previous) {
            return back()->with('status', 'photo-removed');
        }

        $employee->update(['photo_path' => null]);
        $this->forget($previous, null);

        return back()->with('status', 'photo-removed');
    }

    /** Hapus berkas lama, kecuali kalau ternyata masih dipakai baris yang sama. */
    private function forget(?string $previous, ?string $current): void
    {
        if ($previous && $previous !== $current) {
            Storage::disk('public')->delete($previous);
        }
    }

    private function employee(): Employee
    {
        $employee = auth()->user()->employee;

        abort_unless($employee, 403, 'Akun Anda belum tertaut ke data karyawan.');

        return $employee;
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
