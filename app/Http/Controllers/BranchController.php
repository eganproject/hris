<?php

namespace App\Http\Controllers;

use App\Http\Requests\BranchRequest;
use App\Models\Branch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BranchController extends Controller
{
    public function index(Request $request): View
    {
        $perPage = min(max((int) $request->input('per_page', 15), 10), 100);

        $branches = Branch::query()
            ->withCount(['departments', 'employees', 'employees as active_employees_count' => fn ($query) => $query->active()])
            ->when($request->string('search')->toString(), function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('city', 'like', "%{$search}%");
                });
            })
            ->orderBy('city')
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        return view('organization.branches.index', ['branches' => $branches, 'filters' => $request->only('search'), 'perPage' => $perPage]);
    }

    public function create(): View
    {
        return view('organization.branches.create', ['branch' => new Branch(['is_active' => true, 'type' => 'office'])]);
    }

    public function store(BranchRequest $request): RedirectResponse
    {
        Branch::query()->create($request->payload());

        return redirect()->route('organization.branches.index')->with('status', 'Lokasi kerja berhasil dibuat.');
    }

    public function edit(Branch $branch): View
    {
        return view('organization.branches.edit', ['branch' => $branch]);
    }

    public function update(BranchRequest $request, Branch $branch): RedirectResponse
    {
        $branch->update($request->payload());

        return redirect()->route('organization.branches.index')->with('status', 'Lokasi kerja berhasil diperbarui.');
    }

    public function destroy(Branch $branch): RedirectResponse
    {
        $branch->delete();

        return redirect()->route('organization.branches.index')->with('status', 'Lokasi kerja berhasil dihapus.');
    }
}
