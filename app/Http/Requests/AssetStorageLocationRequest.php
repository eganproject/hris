<?php

namespace App\Http\Requests;

use App\Models\AssetStorageLocation;
use App\Support\DataScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class AssetStorageLocationRequest extends FormRequest
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
        $location = $this->location();
        $branchId = $this->targetBranchId();

        return [
            // Lokasi kerja hanya ditentukan saat dibuat. Memindahkannya ke cabang lain
            // berarti ikut memindahkan seluruh rak di bawahnya beserta aset yang
            // menunjuk ke sana — sebuah perpindahan barang, bukan penyuntingan master.
            'branch_id' => [
                Rule::requiredIf($location === null),
                Rule::in(DataScope::forAssets($this->user())->branches()->pluck('id')->all()),
            ],
            'parent_id' => ['nullable', $this->parentRule($branchId)],
            'name' => [
                'required', 'string', 'max:120',
                $this->uniqueAmongSiblings($branchId, $location?->id),
            ],
            'code' => ['nullable', 'string', 'max:30'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'branch_id.in' => 'Lokasi kerja berada di luar cakupan akses Anda.',
            'name.unique' => 'Sudah ada tempat penyimpanan dengan nama itu di jenjang yang sama.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'branch_id' => 'lokasi kerja',
            'parent_id' => 'induk',
            'name' => 'nama tempat',
            'code' => 'kode',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $data = [
            ...$this->safe()->except(['is_active', 'branch_id']),
            'is_active' => $this->boolean('is_active'),
        ];

        // Pada penyuntingan, lokasi kerjanya tidak ikut dikirim ulang.
        if ($this->location() === null) {
            $data['branch_id'] = $this->validated('branch_id');
        }

        return $data;
    }

    private function location(): ?AssetStorageLocation
    {
        $location = $this->route('storageLocation');

        return $location instanceof AssetStorageLocation ? $location : null;
    }

    private function targetBranchId(): ?int
    {
        return $this->location()?->branch_id ?? (int) $this->input('branch_id') ?: null;
    }

    /**
     * Induk harus berada di lokasi kerja yang sama, bukan dirinya sendiri atau
     * keturunannya (yang akan membuat pohonnya melingkar dan menggantung), dan
     * hasilnya masih muat di dalam batas jenjang bersama seluruh keturunannya.
     */
    private function parentRule(?int $branchId): callable
    {
        return function (string $attribute, $value, callable $fail) use ($branchId): void {
            if (! $value) {
                return;
            }

            $parent = AssetStorageLocation::query()->find($value);

            if (! $parent || ($branchId && $parent->branch_id !== $branchId)) {
                $fail('Induk harus berupa tempat penyimpanan di lokasi kerja yang sama.');

                return;
            }

            $location = $this->location();

            if ($location && in_array((int) $value, $location->subtreeIds(), true)) {
                $fail('Induk tidak boleh berupa tempat penyimpanan itu sendiri atau bagian di bawahnya.');

                return;
            }

            $resultingDepth = (int) $parent->depth + 1 + ($location?->subtreeHeight() ?? 0);

            if ($resultingDepth > AssetStorageLocation::MAX_DEPTH - 1) {
                $fail('Susunannya jadi terlalu dalam — maksimal '.AssetStorageLocation::MAX_DEPTH.' jenjang.');
            }
        };
    }

    private function uniqueAmongSiblings(?int $branchId, ?int $ignoreId): Unique
    {
        $parentId = $this->input('parent_id') ?: null;

        $rule = Rule::unique('asset_storage_locations', 'name')
            ->where('branch_id', $branchId)
            ->ignore($ignoreId);

        return $parentId ? $rule->where('parent_id', $parentId) : $rule->whereNull('parent_id');
    }
}
