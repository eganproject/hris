<?php

namespace App\Http\Controllers;

use App\Actions\Assets\AcknowledgeAssignment;
use App\Exceptions\AssetCustodyException;
use App\Models\AssetAssignment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "Aset Saya": apa yang sedang saya pegang, apa yang pernah saya pegang, dan mana
 * yang masih menunggu konfirmasi penerimaan dari saya.
 *
 * Halaman ini hanya pernah memperlihatkan aset milik penggunanya sendiri — cakupan
 * datanya adalah dirinya, bukan lokasi kerja atau divisi.
 */
class MyAssetController extends Controller
{
    public function index(Request $request): View
    {
        $employee = $request->user()->employee;

        $assignments = $employee
            ? $employee->assetAssignments()
                ->with(['asset:id,asset_code,name,category_id', 'asset.category:id,name', 'assignedBy:id,name'])
                ->latest('assigned_at')
                ->get()
            : collect();

        return view('my-assets.index', [
            'employee' => $employee,
            'open' => $assignments->filter(fn (AssetAssignment $a) => $a->isOpen())->values(),
            'history' => $assignments->reject(fn (AssetAssignment $a) => $a->isOpen())->values(),
        ]);
    }

    public function acknowledge(Request $request, AssetAssignment $assignment, AcknowledgeAssignment $action): RedirectResponse
    {
        // Hanya pemegangnya sendiri yang boleh mengakui penerimaan. Pengakuan dari
        // orang lain — termasuk dari petugas yang menyerahkannya — akan menghapus
        // satu-satunya alasan pengakuan ini ada.
        abort_unless(
            $assignment->employee?->user_id !== null
            && $assignment->employee->user_id === $request->user()->id,
            403,
        );

        $data = $request->validate(
            ['note' => ['nullable', 'string', 'max:500']],
            [],
            ['note' => 'catatan'],
        );

        try {
            $action->handle($assignment, $request->user(), $data['note'] ?? null);
        } catch (AssetCustodyException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Terima kasih — penerimaan aset sudah dikonfirmasi.');
    }
}
