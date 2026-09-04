<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssetCategoryRequest extends FormRequest
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
        $categoryId = $this->route('category')?->id;

        return [
            'code' => ['required', 'string', 'max:30', Rule::unique('asset_categories', 'code')->ignore($categoryId)],
            'name' => ['required', 'string', 'max:255'],
            // Ikut membentuk kode aset, jadi hanya huruf/angka — tanda hubung adalah
            // pemisah bagian di kode aset dan akan mengaburkan batasnya.
            'asset_prefix' => ['required', 'string', 'max:10', 'regex:/^[A-Za-z0-9]+$/'],
            'requires_serial' => ['sometimes', 'boolean'],
            'useful_life_months' => ['nullable', 'integer', 'min:1', 'max:1200'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'asset_prefix.regex' => 'Prefix kode hanya boleh berisi huruf dan angka, tanpa spasi atau tanda baca.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'code' => 'kode kategori',
            'name' => 'nama kategori',
            'asset_prefix' => 'prefix kode aset',
            'useful_life_months' => 'umur ekonomis',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            ...$this->safe()->except(['requires_serial', 'is_active', 'code', 'asset_prefix']),
            // Keduanya muncul di kode aset yang tercetak, jadi bentuknya diseragamkan
            // di sini — bukan diserahkan pada ketelitian pengetiknya.
            'code' => strtoupper($this->string('code')->trim()->toString()),
            'asset_prefix' => strtoupper($this->string('asset_prefix')->trim()->toString()),
            'requires_serial' => $this->boolean('requires_serial'),
            'is_active' => $this->boolean('is_active'),
        ];
    }
}
