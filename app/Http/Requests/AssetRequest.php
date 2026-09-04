<?php

namespace App\Http\Requests;

use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Support\DataScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $asset = $this->asset();
        $scope = DataScope::forAssets($this->user());

        // Lokasi & divisi yang boleh dipilih dibatasi cakupan penggunanya sendiri.
        // Tanpa ini, seorang officer cabang bisa mendaftarkan aset atas nama cabang
        // lain — sebuah baris yang seketika hilang dari layarnya sendiri.
        $branchIds = $scope->branches()->pluck('id')->all();

        // Divisi yang sudah melekat pada aset ini ikut diterima meski kini nonaktif —
        // tanpa itu, menonaktifkan satu divisi akan mengunci seluruh aset lamanya dari
        // penyuntingan, termasuk sekadar memperbaiki salah ketik nama barang.
        $departmentIds = array_values(array_unique([
            ...$scope->departments()->pluck('id')->all(),
            ...($asset?->departmentIds() ?? []),
        ]));

        return [
            'category_id' => ['required', Rule::exists('asset_categories', 'id')],
            'name' => ['required', 'string', 'max:255'],
            'brand' => ['nullable', 'string', 'max:100'],
            'model' => ['nullable', 'string', 'max:100'],
            'serial_number' => [
                Rule::requiredIf(fn () => $this->categoryRequiresSerial()),
                'nullable',
                'string',
                'max:100',
                // Termasuk aset yang sudah diarsipkan: unique index di tabelnya tidak
                // mengenal soft delete, jadi mengecualikannya di sini hanya menukar
                // pesan validasi yang rapi dengan galat duplikat dari database.
                Rule::unique('assets', 'serial_number')->ignore($asset?->id),
            ],
            'specification' => ['nullable', 'string', 'max:2000'],

            'owning_branch_id' => ['required', Rule::in($branchIds)],
            'current_branch_id' => ['required', Rule::in($branchIds)],

            'department_id' => ['required', Rule::in($departmentIds)],
            // Satu aset boleh dimiliki bersama oleh dua divisi (mis. kendaraan
            // operasional). Divisi kedua opsional dan harus berbeda dari yang utama.
            'secondary_department_id' => ['nullable', 'different:department_id', Rule::in($departmentIds)],

            'status' => ['required', Rule::in(AssetStatus::manualValues())],
            'condition' => ['required', Rule::in(AssetCondition::values())],

            'acquired_at' => ['nullable', 'date', 'before_or_equal:today'],
            'acquisition_cost' => ['nullable', 'numeric', 'min:0', 'max:999999999999999'],
            'warranty_expires_at' => ['nullable', 'date', 'after_or_equal:acquired_at'],

            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'owning_branch_id.in' => 'Lokasi pemilik berada di luar cakupan akses Anda.',
            'current_branch_id.in' => 'Lokasi aset berada di luar cakupan akses Anda.',
            'department_id.in' => 'Divisi pemilik berada di luar cakupan akses Anda.',
            'secondary_department_id.in' => 'Divisi kedua berada di luar cakupan akses Anda.',
            'secondary_department_id.different' => 'Divisi kedua harus berbeda dari divisi pemilik.',
            'serial_number.required' => 'Kategori ini mewajibkan nomor seri.',
            'serial_number.unique' => 'Nomor seri ini sudah terdaftar pada aset lain (termasuk aset yang sudah diarsipkan).',
            'acquired_at.before_or_equal' => 'Tanggal perolehan tidak boleh di masa depan.',
            'warranty_expires_at.after_or_equal' => 'Masa garansi berakhir sebelum tanggal perolehan.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'category_id' => 'kategori',
            'name' => 'nama aset',
            'serial_number' => 'nomor seri',
            'owning_branch_id' => 'lokasi pemilik',
            'current_branch_id' => 'lokasi aset',
            'department_id' => 'divisi pemilik',
            'secondary_department_id' => 'divisi kedua',
            'acquired_at' => 'tanggal perolehan',
            'acquisition_cost' => 'nilai perolehan',
            'warranty_expires_at' => 'garansi berakhir',
        ];
    }

    /**
     * Kolom untuk baris asetnya sendiri (tanpa divisi kedua, yang tinggal di tabel
     * pivot dan disinkronkan controller lewat departmentIds()).
     *
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $data = $this->safe()->except(['secondary_department_id', 'status']);

        // Status yang lahir dari alur kerja (dipegang, dilepas) tidak boleh dikembalikan
        // ke status manual hanya karena seseorang menyunting nama barangnya. Formulir
        // menampilkannya sebagai keterangan, dan nilainya dipertahankan di sini.
        $asset = $this->asset();

        if ($asset === null || in_array($asset->status, AssetStatus::MANUAL, true)) {
            $data['status'] = $this->validated('status');
        }

        return $data;
    }

    /**
     * Himpunan divisi pemilik: satu atau dua, divisi utama selalu ikut.
     *
     * @return list<int>
     */
    public function departmentIds(): array
    {
        return array_values(array_unique(array_filter([
            (int) $this->validated('department_id'),
            (int) $this->validated('secondary_department_id'),
        ])));
    }

    private function asset(): ?Asset
    {
        $asset = $this->route('asset');

        return $asset instanceof Asset ? $asset : null;
    }

    private function categoryRequiresSerial(): bool
    {
        $categoryId = $this->input('category_id');

        return $categoryId
            ? (bool) AssetCategory::query()->whereKey($categoryId)->value('requires_serial')
            : false;
    }
}
