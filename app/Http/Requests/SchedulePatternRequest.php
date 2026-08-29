<?php

namespace App\Http\Requests;

use App\Enums\SchedulePatternType;
use App\Models\SchedulePattern;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SchedulePatternRequest extends FormRequest
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
        $patternId = $this->route('schedulePattern')?->id;
        $isRotating = $this->input('type') === SchedulePatternType::Rotating->value;

        return [
            'code' => ['required', 'string', 'max:50', $this->uniqueCode($patternId)],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(array_keys(SchedulePatternType::options()))],
            'cycle_length' => [Rule::requiredIf($isRotating), 'nullable', 'integer', 'min:1', 'max:60'],
            'anchor_date' => [Rule::requiredIf($isRotating), 'nullable', 'date'],
            'is_active' => ['sometimes', 'boolean'],
            'days' => ['array'],
            'days.*' => ['nullable', 'integer', 'exists:shifts,id'],
            'days_wfh' => ['array'],
            'days_wfh.*' => ['nullable'],
        ];
    }

    /**
     * Kode pola unik lintas arsip — lihat ShiftRequest::uniqueCode() untuk alasannya.
     * Tanpa ini, bentrok dengan pola terarsip hanya berbunyi "sudah digunakan" oleh
     * sesuatu yang tidak kelihatan di daftar.
     */
    private function uniqueCode(?int $patternId): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($patternId): void {
            $existing = SchedulePattern::withTrashed()
                ->where('code', $value)
                ->when($patternId, fn ($query, $id) => $query->whereKeyNot($id))
                ->first();

            if (! $existing) {
                return;
            }

            $fail($existing->trashed()
                ? "Kode {$value} masih dipakai pola \"{$existing->name}\" yang ada di arsip. Pulihkan pola itu lewat tab Arsip, atau pakai kode lain."
                : 'Kode pola sudah digunakan.');
        };
    }

    public function attributes(): array
    {
        return [
            'cycle_length' => 'panjang siklus',
            'anchor_date' => 'tanggal jangkar',
        ];
    }
}
