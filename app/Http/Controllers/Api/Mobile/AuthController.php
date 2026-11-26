<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\MobileDevice;
use App\Models\User;
use App\Services\Mobile\MobileDeviceRegistry;
use App\Support\Mobile\Present;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Login aplikasi HP: akun yang sama dengan HRIS web (email atau NIK). Boleh
 * dari HP mana saja; HP-nya dicatat dan token diikat ke HP itu.
 */
class AuthController extends Controller
{
    public function __construct(private readonly MobileDeviceRegistry $devices) {}

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
            'device_id' => ['required', 'string', 'min:8', 'max:100'],
            'platform' => ['required', 'in:android,ios'],
            'model' => ['nullable', 'string', 'max:255'],
            'os_version' => ['nullable', 'string', 'max:50'],
            'app_version' => ['nullable', 'string', 'max:30'],
        ], [], ['login' => 'email atau NIK', 'password' => 'kata sandi']);

        $key = 'mobile-login:'.Str::lower($data['login']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'login' => 'Terlalu banyak percobaan. Coba lagi dalam '.RateLimiter::availableIn($key).' detik.',
            ]);
        }

        $user = $this->findUser($data['login']);

        if (! $user || ! $user->is_active || ! Hash::check($data['password'], $user->password)) {
            RateLimiter::hit($key);

            throw ValidationException::withMessages(['login' => 'Email/NIK atau kata sandi salah.']);
        }

        RateLimiter::clear($key);

        $employee = $user->employee;

        if (! $employee || $employee->isInactive()) {
            return response()->json([
                'message' => 'Akun ini belum tertaut ke data karyawan aktif. Hubungi HR.',
                'code' => 'no_employee',
            ], 403);
        }

        $device = $this->devices->register($user, $employee, $data);

        // Satu token per HP: login ulang di HP yang sama mengganti token lamanya.
        $this->devices->deleteTokens($device);
        $token = $user->createToken($device->tokenName());

        $device->forceFill(['last_seen_at' => now()])->save();

        return response()->json(['data' => [
            'token' => $token->plainTextToken,
            'user' => $this->profile($user, $employee),
            'device' => $this->deviceJson($device),
        ]]);
    }

    /**
     * Persetujuan karyawan atas penyimpanan selfie & lokasi (UU PDP), diberikan
     * sekali per HP pada layar "Daftarkan HP ini".
     */
    public function consent(Request $request): JsonResponse
    {
        $request->validate(['agree' => ['accepted']], ['agree.accepted' => 'Centang persetujuan untuk melanjutkan.']);

        /** @var MobileDevice $device */
        $device = $request->attributes->get('mobile_device');
        $device->forceFill(['consented_at' => $device->consented_at ?? now()])->save();

        return response()->json(['data' => ['device' => $this->deviceJson($device)]]);
    }

    /**
     * @return array<string, mixed>
     */
    private function deviceJson(MobileDevice $device): array
    {
        return [
            'id' => $device->id,
            'status' => $device->status,
            'model' => $device->model,
            'platform' => $device->platform,
            'consented' => $device->consented_at !== null,
            'consented_at' => $device->consented_at?->toIso8601String(),
        ];
    }

    public function logout(Request $request): JsonResponse
    {
        // HP yang keluar tidak boleh lagi menerima notifikasi akun ini.
        $request->attributes->get('mobile_device')?->forceFill(['push_token' => null, 'push_token_updated_at' => now()])->save();
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['data' => ['ok' => true]]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json(['data' => $this->profile($user, $user->employee) + [
            'device' => $this->deviceJson($request->attributes->get('mobile_device')),
        ]]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function profile(User $user, Employee $employee): array
    {
        $employee->loadMissing(['branch', 'department', 'jobPosition', 'manager']);

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'employee' => [
                'id' => $employee->id,
                'number' => $employee->employee_number,
                'full_name' => $employee->full_name,
                'photo_url' => Present::photoUrl($employee),
                'phone' => $employee->phone,
                'branch' => $employee->branch ? ['id' => $employee->branch->id, 'name' => $employee->branch->name] : null,
                'department' => $employee->department?->name,
                'job_position' => $employee->jobPosition?->name,
                'manager' => $employee->manager ? ['id' => $employee->manager->id, 'full_name' => $employee->manager->full_name] : null,
            ],
            // Atasan = karyawan yang punya bawahan (manager_id), bukan role.
            'is_leader' => $employee->subordinates()->exists(),
        ];
    }

    private function findUser(string $login): ?User
    {
        if (str_contains($login, '@')) {
            return User::query()->where('email', $login)->first();
        }

        $employee = Employee::query()->where('employee_number', $login)->whereNotNull('user_id')->first();

        return $employee?->user;
    }
}
