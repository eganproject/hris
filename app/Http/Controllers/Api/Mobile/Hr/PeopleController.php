<?php

namespace App\Http\Controllers\Api\Mobile\Hr;

use App\Models\Employee;
use App\Models\MobileDevice;
use App\Support\Mobile\Present;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Rekan kerja di aplikasi HP: siapa yang sedang online, dan profil PUBLIK
 * seseorang. Hanya data yang memang terlihat di kantor yang dibuka — nomor HP,
 * alamat, email, NIK, tanggal lahir, kontrak, gaji, cuti, dan absensi tidak
 * pernah ikut di sini.
 */
class PeopleController extends HrController
{
    /** Dianggap online bila aplikasinya dipakai dalam rentang ini. */
    public const ONLINE_WITHIN_MINUTES = 5;

    public function index(Request $request): JsonResponse
    {
        $this->employee();

        $data = $request->validate([
            'status' => ['nullable', 'in:online,offline'],
            'q' => ['nullable', 'string', 'max:100'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);

        $threshold = now()->subMinutes(self::ONLINE_WITHIN_MINUTES);
        $base = $this->query()
            ->when($data['q'] ?? null, function (Builder $q, string $term) {
                $q->where(function (Builder $w) use ($term) {
                    $w->where('full_name', 'like', "%{$term}%")
                        ->orWhereHas('jobPosition', fn ($j) => $j->where('name', 'like', "%{$term}%"))
                        ->orWhereHas('department', fn ($d) => $d->where('name', 'like', "%{$term}%"));
                });
            });

        $isOnline = fn (Builder $q) => $q->where('status', MobileDevice::STATUS_ACTIVE)->where('last_seen_at', '>=', $threshold);
        $onlineCount = (clone $base)->whereHas('mobileDevices', $isOnline)->count();
        $total = (clone $base)->count();

        $people = (clone $base)
            ->when(($data['status'] ?? null) === 'online', fn ($q) => $q->whereHas('mobileDevices', $isOnline))
            ->when(($data['status'] ?? null) === 'offline', fn ($q) => $q->whereDoesntHave('mobileDevices', $isOnline))
            ->orderByRaw('app_last_seen_at is null')
            ->orderByDesc('app_last_seen_at')
            ->orderBy('full_name')
            ->limit($data['limit'] ?? 300)
            ->get();

        return $this->ok($people->map(fn (Employee $e) => $this->summary($e))->values(), 200, [
            'meta' => ['online_count' => $onlineCount, 'offline_count' => $total - $onlineCount],
        ]);
    }

    public function show(int $employee): JsonResponse
    {
        $this->employee();

        $person = $this->query()
            ->with(['branch', 'departments', 'manager.jobPosition', 'manager.department'])
            ->findOrFail($employee);

        return $this->ok([
            ...$this->summary($person),
            'motto' => $person->motto,
            'branch' => $person->branch?->name,
            'departments' => $person->departments->pluck('name')->values(),
            'manager' => Present::person($person->manager),
            'joined' => $person->join_date?->format('Y-m'),
        ]);
    }

    /** Karyawan aktif + kapan terakhir memakai aplikasi (HP aktif mana pun). */
    /**
     * Rekan kerja hanya yang satu lokasi kerja (cabang) dengan penggunanya. Karyawan
     * tanpa lokasi kerja tidak melihat siapa pun selain dirinya.
     */
    private function query(): Builder
    {
        $me = $this->employee();

        return Employee::query()->active()
            ->when($me->branch_id, fn (Builder $q, $branchId) => $q->where('branch_id', $branchId), fn (Builder $q) => $q->whereKey($me->id))
            ->with(['jobPosition', 'department'])
            ->addSelect([
                'app_last_seen_at' => MobileDevice::query()
                    ->selectRaw('max(last_seen_at)')
                    ->whereColumn('mobile_devices.employee_id', 'employees.id')
                    ->where('status', MobileDevice::STATUS_ACTIVE),
            ]);
    }

    /** @return array<string, mixed> */
    private function summary(Employee $e): array
    {
        $lastSeen = $e->app_last_seen_at ? Carbon::parse($e->app_last_seen_at) : null;

        return [
            ...Present::person($e),
            'online' => $lastSeen !== null && $lastSeen->gte(now()->subMinutes(self::ONLINE_WITHIN_MINUTES)),
            'last_seen_at' => $lastSeen?->toIso8601String(),
        ];
    }
}
