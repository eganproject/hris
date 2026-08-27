<?php

namespace App\Http\Controllers;

use App\Exports\ContractsExport;
use App\Models\Branch;
use App\Models\Department;
use App\Models\EmployeeContract;
use App\Models\User;
use App\Support\DataScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Cross-employee view of contracts, centred on the "about to end" question HR keeps
 * asking. Perpanjang & ubah kontrak tetap di halaman karyawan; di sini hanya ada
 * satu aksi tulis — hapus baris kontrak duplikat (lihat destroy()). Semuanya dibatasi
 * ke karyawan yang boleh dilihat pengguna yang login.
 */
class ContractController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $filters = $request->only(['filter', 'type', 'branch_id', 'department_id', 'search']);
        $perPage = min(max((int) $request->input('per_page', 15), 10), 100);

        // A fresh base query per use so each summary card counts independently.
        $base = fn (): Builder => $this->baseQuery($filters, $user);

        $range = $filters['filter'] ?? 'all';

        $contracts = $this->applyRange($base(), $range)
            // employee.contracts dipakai EmployeeContract::deletionBlocker() untuk
            // menilai tiap baris tanpa query tambahan per baris.
            ->with(['employee.branch', 'employee.departments', 'employee.contracts'])
            // Daftar tumpang tindih diurutkan per karyawan lalu per tanggal mulai:
            // pasangan yang beririsan harus bersebelahan agar bisa dibandingkan. Urutan
            // "paling cepat berakhir" justru memisahkan keduanya, kadang beda halaman.
            ->when(
                $range === 'overlapping',
                fn (Builder $query) => $query->orderBy('employee_id')->orderBy('start_date')->orderBy('id'),
                // Soonest-ending first; open-ended (no end date) contracts sink to the bottom.
                fn (Builder $query) => $query->orderByRaw('end_date is null')->orderBy('end_date'),
            )
            ->paginate($perPage)
            ->withQueryString();

        return view('employees.contracts.index', [
            'contracts' => $contracts,
            'branches' => $this->scopedBranches($user),
            'departments' => $this->scopedDepartments($user),
            'filters' => $filters,
            'perPage' => $perPage,
            'contractTypes' => ['PKWT', 'PKWTT', 'Probation', 'Internship'],
            'summary' => [
                'total' => $base()->count(),
                'active' => $base()->active()->count(),
                'expiring_30' => $base()->expiringWithin(30)->count(),
                'expiring_60' => $base()->expiringWithin(60)->count(),
                'expiring_90' => $base()->expiringWithin(90)->count(),
                'expired' => $base()->lapsed()->count(),
                'overlapping' => $base()->overlapping()->count(),
            ],
        ]);
    }

    /**
     * Hapus satu baris kontrak. Disediakan untuk membersihkan duplikat hasil salah
     * input atau impor — bukan untuk membuang riwayat. Aturan boleh/tidaknya ada di
     * EmployeeContract::deletionBlocker(), satu tempat, supaya tombol yang tampil di
     * layar dan penjaga di sini tidak bisa berbeda pendapat.
     */
    public function destroy(Request $request, EmployeeContract $contract): RedirectResponse
    {
        $contract->loadMissing('employee');

        DataScope::forEmployees($request->user())->authorize($contract->employee);

        if ($blocker = $contract->deletionBlocker()) {
            return back()->with('error', 'Kontrak tidak bisa dihapus. '.$blocker);
        }

        $employee = $contract->employee;
        $number = $contract->contract_number;

        DB::transaction(function () use ($contract, $employee, $number) {
            // Jejaknya dicatat lebih dulu: begitu barisnya hilang, tidak ada lagi yang
            // bisa menjelaskan kenapa nomor kontrak itu lenyap dari daftar.
            $employee?->recordEvent(
                'contract_deleted',
                "Kontrak {$number} dihapus (pembersihan data duplikat).",
                now(),
                ['contract_number' => $number, 'contract_type' => $contract->contract_type, 'status' => $contract->status],
            );

            $contract->delete();
        });

        // Setelah commit: berkas di disk tidak ikut transaksi, jadi menghapusnya lebih
        // dulu akan menyisakan baris tanpa dokumen bila transaksinya batal.
        $contract->deleteDocumentFile();

        return back()->with('status', "Kontrak {$number} dihapus.");
    }

    /**
     * Export the contract list (honouring the current filters) to .xlsx.
     */
    public function export(Request $request): BinaryFileResponse
    {
        $filters = $request->only(['filter', 'type', 'branch_id', 'department_id', 'search']);

        return Excel::download(
            new ContractsExport($filters, $request->user()),
            'kontrak-'.now()->format('Y-m-d').'.xlsx',
        );
    }

    /**
     * Contracts belonging to employees inside the user's scope, narrowed by the
     * location / division / type / search filters (but NOT the range preset).
     *
     * @param  array<string, mixed>  $filters
     */
    private function baseQuery(array $filters, User $user): Builder
    {
        return EmployeeContract::query()
            ->whereHas('employee', fn (Builder $query) => $query
                ->visibleTo($user)
                ->byBranch($filters['branch_id'] ?? null)
                ->byDepartment($filters['department_id'] ?? null))
            ->when($filters['type'] ?? null, fn (Builder $query, string $type) => $query->where('contract_type', $type))
            ->when($filters['search'] ?? null, function (Builder $query, string $search) {
                $query->where(function (Builder $query) use ($search) {
                    $query
                        ->where('contract_number', 'like', "%{$search}%")
                        ->orWhereHas('employee', fn (Builder $employee) => $employee
                            ->where('full_name', 'like', "%{$search}%")
                            ->orWhere('employee_number', 'like', "%{$search}%"));
                });
            });
    }

    private function applyRange(Builder $query, string $filter): Builder
    {
        return match ($filter) {
            'active' => $query->active(),
            'expiring_30' => $query->expiringWithin(30),
            'expiring_60' => $query->expiringWithin(60),
            'expiring_90' => $query->expiringWithin(90),
            'expired' => $query->lapsed(),
            'overlapping' => $query->overlapping(),
            default => $query,
        };
    }

    /**
     * @return Collection<int, Branch>
     */
    private function scopedBranches(User $user)
    {
        $branchIds = $user->seesAllData(User::SCOPE_BYPASS_EMPLOYEES) ? [] : $user->accessBranchIds();

        return Branch::query()
            ->where('is_active', true)
            ->when($branchIds !== [], fn ($query) => $query->whereIn('id', $branchIds))
            ->orderBy('city')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, Department>
     */
    private function scopedDepartments(User $user)
    {
        $departmentIds = $user->seesAllData(User::SCOPE_BYPASS_EMPLOYEES) ? [] : $user->accessDepartmentIds();

        return Department::query()
            ->where('is_active', true)
            ->when($departmentIds !== [], fn ($query) => $query->whereIn('id', $departmentIds))
            ->orderBy('name')
            ->get();
    }
}
