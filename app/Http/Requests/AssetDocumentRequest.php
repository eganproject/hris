<?php

namespace App\Http\Requests;

use App\Models\AssetDocument;
use App\Support\UploadMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssetDocumentRequest extends FormRequest
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
        return [
            'file' => [
                'required',
                'file',
                'mimes:jpg,jpeg,png,webp,pdf',
                'max:'.(AssetDocument::MAX_MB * 1024),
            ],
            'type' => ['required', Rule::in(array_keys(AssetDocument::TYPE_LABELS))],
            'title' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...UploadMessages::attachment('file', AssetDocument::MAX_MB, 'Berkas'),
            'file.required' => 'Pilih berkasnya dulu.',
            'type.required' => 'Pilih jenis berkasnya.',
        ];
    }
}
