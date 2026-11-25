<?php

namespace App\Http\Controllers\Api\Mobile\Hr;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Dasar controller API aplikasi HP tahap 2. Bentuk respons mengikuti kesepakatan
 * dengan API tahap 1: sukses {data: ...}, galat {message, errors?}.
 */
abstract class HrController extends Controller
{
    protected function employee(): Employee
    {
        $employee = request()->user()?->employee;

        abort_unless($employee, 403, 'Akun Anda belum tertaut ke data karyawan.');

        return $employee;
    }

    /**
     * Atasan = karyawan yang punya bawahan langsung (manager_id), bukan role.
     */
    protected function leader(): Employee
    {
        $employee = $this->employee();

        abort_unless($employee->subordinates()->exists(), 403, 'Menu ini hanya untuk atasan yang punya anggota tim.');

        return $employee;
    }

    /**
     * Seluruh bawahan atasan ini, semua level (bawahan langsung, bawahan dari
     * bawahan, dst.) — sama dengan cakupan "tim" di web.
     *
     * @return list<int>
     */
    protected function teamIds(Employee $leader): array
    {
        $user = request()->user();

        if ($user && $user->employee?->id === $leader->id) {
            return $user->subordinateEmployeeIds();
        }

        return $leader->user?->subordinateEmployeeIds() ?? [];
    }

    protected function ok(mixed $data, int $status = 200, array $extra = []): JsonResponse
    {
        return response()->json(['data' => $data, ...$extra], $status);
    }

    protected function message(string $message, mixed $data = null, int $status = 200): JsonResponse
    {
        return response()->json(array_filter(['message' => $message, 'data' => $data], fn ($v) => $v !== null), $status);
    }

    /**
     * @param  callable(mixed): mixed  $map
     */
    protected function paginated(LengthAwarePaginator $page, callable $map): JsonResponse
    {
        return response()->json([
            'data' => collect($page->items())->map($map)->values(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }
}
