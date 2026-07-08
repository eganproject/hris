<?php

namespace App\Http\Controllers;

use App\Models\ShiftSwapRequest;
use App\Services\ShiftSwapService;
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

        $requests = ShiftSwapRequest::query()
            ->with(['requester', 'partner', 'reviewer'])
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();

        return view('attendance.swaps.index', [
            'requests' => $requests,
            'status' => $status,
            'pendingCount' => ShiftSwapRequest::query()->pendingHr()->count(),
        ]);
    }

    public function approve(ShiftSwapRequest $swap): RedirectResponse
    {
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
        abort_unless($swap->isPendingHr(), 403);

        $this->swaps->hrReject($swap, $request->string('decision_notes')->toString() ?: null);

        return redirect()->route('attendance.swaps.index')->with('status', 'Tukar jadwal ditolak.');
    }
}
