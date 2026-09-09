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
use App\Models\AssetCategory;
use App\Models\Employee;
use App\Support\DataScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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
    /** Pilihan jumlah baris per halaman. */
    private const PER_PAGE_OPTIONS = [25, 50, 100, 200];

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
        $state = AssetAssignment::resolveState($request->input('state'));

        // Dibaca defensif. Halaman ini dibuka lewat URL yang gampang disalin-tempel
        // dan disunting tangan, dan dua jebakan bawaan Laravel menunggu di situ:
        // Request::date() melempar pengecualian pada tanggal yang tidak masuk akal,
        // dan Request::string() melempar TypeError begitu parameternya dikirim
        // sebagai array (?category[]=x). Keduanya berujung 500.
        $filters = [
            'search' => $this->text($request->input('search')),
            'category' => $this->id($request->input('category')),
            'branch' => $this->id($request->input('branch')),
            'department' => $this->id($request->input('department')),
            'employee' => $this->id($request->input('employee')),
            'from' => $this->date($request->input('from')),
            'to' => $this->date($request->input('to')),
        ];

        $perPage = in_array((int) $request->input('per_page'), self::PER_PAGE_OPTIONS, true)
            ? (int) $request->input('per_page')
            : self::PER_PAGE_OPTIONS[0];

        // Penyaring yang menyangkut asetnya dititipkan ke Asset::scopeMatchingFilters()
        // lewat subkueri yang SAMA dengan yang membatasi cakupan — bukan disalin ke
        // sini. Dengan begitu "divisi IT" di halaman ini selalu berarti persis sama
        // dengan "divisi IT" di Daftar Aset dan di Register Aset.
        $assets = $scope->assets()
            ->matchingFilters(['category' => $filters['category'], 'branch' => $filters['branch'], 'department' => $filters['department']])
            ->select('assets.id');

        $assignments = AssetAssignment::query()
            ->whereIn('asset_id', $assets)
            ->inState($state)
            ->matchingFilters($filters)
            ->with(['asset:id,asset_code,name', 'employee:id,full_name', 'assignedBy:id,name'])
            ->latest('assigned_at')
            ->paginate($perPage)
            ->withQueryString();

        return view('assets.assignments.index', [
            'assignments' => $assignments,
            'filter' => $state,
            'states' => AssetAssignment::STATES,
            'filters' => $filters,
            'perPage' => $perPage,
            'perPageOptions' => self::PER_PAGE_OPTIONS,
            'categories' => AssetCategory::query()->orderBy('name')->get(['id', 'name']),
            'branches' => $scope->branches(),
            'departments' => $scope->departments(),
            // Hanya karyawan yang benar-benar pernah memegang aset dalam cakupan ini
            // yang ditawarkan — daftar seluruh karyawan membuat kotaknya panjang tanpa
            // guna, dan menawarkan nama yang tidak akan pernah menghasilkan satu baris.
            // Dihitung dari cakupan saja, sengaja tidak ikut menyempit oleh penyaring
            // lain: daftar pilihan yang isinya berubah-ubah setiap kali kotak di
            // sebelahnya diubah membuat orang mengira namanya hilang dari sistem.
            'employees' => Employee::query()
                ->whereIn('id', AssetAssignment::query()
                    ->whereIn('asset_id', $scope->assets()->select('assets.id'))
                    ->select('employee_id'))
                ->orderBy('full_name')
                ->get(['id', 'full_name']),
            'hasNoScope' => $scope->isEmpty(),
        ]);
    }

    private function text(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    /** Id penyaring, atau null bila bukan angka yang masuk akal. */
    private function id(mixed $value): ?int
    {
        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }

    /** Tanggal penyaring, atau null bila tidak bisa dibaca sebagai tanggal. */
    private function date(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
