<?php

namespace App\Http\Controllers;

use App\Models\ShiftSwapRequest;
use App\Services\ShiftSwapService;
use App\Support\DataScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ShiftSwapController extends Controller
{
    public function __construct(private readonly ShiftSwapService $swaps)
    {
    }

    public function index(Request $request): View
    {
        $status = $request->string('status')->toString() ?: 'pending_hr';
        $perPage = min(max((int) $request->input('per_page', 15), 10), 100);
        $branchId = $request->integer('branch_id') ?: null;
        $search = $request->string('search')->toString() ?: null;
        $dateFrom = $request->string('date_from')->toString() ?: null;
        $dateTo = $request->string('date_to')->toString() ?: null;

        // A swap involves two people: it is only shown when BOTH are inside the scope,
        // otherwise approving it would silently change a roster the user cannot see.
        $scope = DataScope::forAttendance($request->user());

        $requests = ShiftSwapRequest::query()
            ->with(['requester', 'partner', 'reviewer'])
            ->tap(fn ($query) => $scope->constrain($query, 'requester_id'))
            ->tap(fn ($query) => $scope->constrain($query, 'partner_id'))
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            // Lokasi & pencarian dilihat dari sisi pengaju (requester).
            ->when($branchId, fn ($q) => $q->whereHas('requester', fn ($e) => $e->byBranch($branchId)))
            ->when($search, fn ($q, $s) => $q->whereHas('requester', fn ($e) => $e
                ->where('full_name', 'like', "%{$s}%")
                ->orWhere('employee_number', 'like', "%{$s}%")))
            ->when($dateFrom, fn ($q, $d) => $q->whereDate('requester_date', '>=', $d))
            ->when($dateTo, fn ($q, $d) => $q->whereDate('requester_date', '<=', $d))
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();

        return view('attendance.swaps.index', [
            'requests' => $requests,
            'status' => $status,
            'filters' => compact('search', 'branchId', 'dateFrom', 'dateTo'),
            'branches' => $scope->branches(),
            'pendingCount' => ShiftSwapRequest::query()
                ->pendingHr()
                ->tap(fn ($query) => $scope->constrain($query, 'requester_id'))
                ->tap(fn ($query) => $scope->constrain($query, 'partner_id'))
                ->count(),
        ]);
    }

    public function approve(Request $request, ShiftSwapRequest $swap): RedirectResponse
    {
        $this->authorizeScope($request, $swap);
        abort_unless($swap->isPendingHr(), 403);

        $conflicts = $this->swaps->hrApprove($swap, request()->string('decision_notes')->toString() ?: null);

        if ($conflicts !== []) {
            return redirect()->route('attendance.swaps.index')
                ->with('error', 'Tidak bisa disetujui: '.implode(' ', $conflicts));
        }

        return redirect()->route('attendance.swaps.index')->with('status', 'Tukar jadwal disetujui & diterapkan.');
    }

    public function reject(Request $request, ShiftSwapRequest $swap): RedirectResponse
    {
        $this->authorizeScope($request, $swap);
        abort_unless($swap->isPendingHr(), 403);

        $this->swaps->hrReject($swap, $request->string('decision_notes')->toString() ?: null);

        return redirect()->route('attendance.swaps.index')->with('status', 'Tukar jadwal ditolak.');
    }

    /** Both sides of the swap must be inside the user's scope. */
    private function authorizeScope(Request $request, ShiftSwapRequest $swap): void
    {
        $scope = DataScope::forAttendance($request->user());

        $scope->authorize($swap->requester);
        $scope->authorize($swap->partner);
    }
}
