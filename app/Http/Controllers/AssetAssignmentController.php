<?php

namespace App\Http\Controllers;

use App\Actions\Assets\AssignAsset;
use App\Actions\Assets\ReturnAsset;
use App\Actions\Assets\TransferAsset;
use App\Exceptions\AssetCustodyException;
use App\Http\Requests\AssignAssetRequest;
use App\Http\Requests\ReturnAssetRequest;
use App\Http\Requests\TransferAssetRequest;
use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Support\DataScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Serah-terima aset: penyerahan, pengembalian, dan pemindahan lokasi.
 *
 * Controller-nya sengaja tipis. Seluruh aturannya — aset harus tersedia, tidak boleh
 * ada dua pemegang sekaligus, karyawan harus aktif — tinggal di Action masing-masing,
 * di dalam transaksi dengan baris aset yang dikunci. Yang tersisa di sini hanyalah
 * memeriksa cakupan dan mengubah penolakan menjadi kalimat di layar.
 */
class AssetAssignmentController extends Controller
{
    public function assign(AssignAssetRequest $request, Asset $asset, AssignAsset $action): RedirectResponse
    {
        DataScope::forAssets($request->user())->authorizeAsset($asset);

        try {
            $assignment = $action->handle($asset, $request->validated(), $request->user());
        } catch (AssetCustodyException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('assets.show', $asset)->with(
            'status',
            "Aset diserahkan kepada {$assignment->employee?->full_name}. Menunggu konfirmasi penerimaan dari yang bersangkutan.",
        );
    }

    /**
     * Menerima aset kembali. Namanya bukan return() — itu kata kunci PHP.
     */
    public function receive(ReturnAssetRequest $request, Asset $asset, ReturnAsset $action): RedirectResponse
    {
        DataScope::forAssets($request->user())->authorizeAsset($asset);

        try {
            $action->handle($asset, $request->validated(), $request->user());
        } catch (AssetCustodyException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('assets.show', $asset)
            ->with('status', 'Aset tercatat kembali. Statusnya sudah disesuaikan dengan hasil pemeriksaan.');
    }

    public function transfer(TransferAssetRequest $request, Asset $asset, TransferAsset $action): RedirectResponse
    {
        DataScope::forAssets($request->user())->authorizeAsset($asset);

        try {
            $action->handle($asset, $request->validated(), $request->user());
        } catch (AssetCustodyException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('assets.show', $asset)->with('status', 'Aset berhasil dipindahkan.');
    }

    /**
     * Riwayat serah-terima lintas aset: siapa memegang apa, dan mana yang telat
     * kembali. Halaman pengawasan bagi yang mengurus aset.
     */
    public function index(Request $request): View
    {
        $scope = DataScope::forAssets($request->user());
        $filter = $request->input('state', 'open');

        $assignments = AssetAssignment::query()
            ->whereIn('asset_id', $scope->assets()->select('assets.id'))
            ->when($filter === 'open', fn ($query) => $query->open())
            ->when($filter === 'unacknowledged', fn ($query) => $query->awaitingAcknowledgement())
            ->when($filter === 'overdue', fn ($query) => $query->open()
                ->whereNotNull('expected_return_at')
                ->whereDate('expected_return_at', '<', today()))
            ->when($filter === 'closed', fn ($query) => $query->closed())
            ->with(['asset:id,asset_code,name', 'employee:id,full_name', 'assignedBy:id,name'])
            ->latest('assigned_at')
            ->paginate(25)
            ->withQueryString();

        return view('assets.assignments.index', [
            'assignments' => $assignments,
            'filter' => $filter,
            'hasNoScope' => $scope->isEmpty(),
        ]);
    }
}
