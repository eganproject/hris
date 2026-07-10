<?php

namespace App\Http\Controllers;

use App\Http\Requests\LeaveTypeRequest;
use App\Models\LeaveType;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class LeaveTypeController extends Controller
{
    public function index(): View
    {
        return view('attendance.leave-types.index', [
            'leaveTypes' => LeaveType::query()->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('attendance.leave-types.create', [
            'leaveType' => new LeaveType(['is_paid' => true, 'is_active' => true, 'counts_against_balance' => false]),
            'statuses' => LeaveTypeRequest::ALLOWED_STATUSES,
        ]);
    }

    public function store(LeaveTypeRequest $request): RedirectResponse
    {
        LeaveType::query()->create($request->validated());

        return redirect()->route('attendance.leave-types.index')->with('status', 'Jenis cuti berhasil ditambahkan.');
    }

    public function edit(LeaveType $leaveType): View
    {
        return view('attendance.leave-types.edit', [
            'leaveType' => $leaveType,
            'statuses' => LeaveTypeRequest::ALLOWED_STATUSES,
        ]);
    }

    public function update(LeaveTypeRequest $request, LeaveType $leaveType): RedirectResponse
    {
        $leaveType->update($request->validated());

        return redirect()->route('attendance.leave-types.index')->with('status', 'Jenis cuti berhasil diperbarui.');
    }

    public function destroy(LeaveType $leaveType): RedirectResponse
    {
        if ($leaveType->leaveRequests()->exists()) {
            return back()->with('error', 'Jenis cuti tidak bisa dihapus karena sudah dipakai pada pengajuan. Nonaktifkan saja.');
        }

        $leaveType->delete();

        return redirect()->route('attendance.leave-types.index')->with('status', 'Jenis cuti berhasil dihapus.');
    }
}
