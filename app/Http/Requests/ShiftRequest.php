<?php

namespace App\Http\Requests;

use App\Models\Shift;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class ShiftRequest extends FormRequest
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
        $shiftId = $this->route('shift')?->id;

        return [
            'code' => ['required', 'string', 'max:50', $this->uniqueCode($shiftId)],
            'name' => ['required', 'string', 'max:255'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'break_minutes' => ['required', 'integer', 'min:0', 'max:480'],
            'late_tolerance_minutes' => ['nullable', 'integer', 'min:0', 'max:240'],
            'early_leave_tolerance_minutes' => ['nullable', 'integer', 'min:0', 'max:240'],
            'overtime_starts_after_minutes' => ['nullable', 'integer', 'min:0', 'max:480'],
            'overtime_min_minutes' => ['nullable', 'integer', 'min:0', 'max:480'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Kode shift unik lintas arsip — indeks uniknya tidak peduli deleted_at, dan itu
     * memang disengaja supaya memulihkan shift tidak pernah bisa bentrok. Yang perlu
     * dijaga hanyalah pesannya: tanpa ini pengguna cuma diberi tahu "kode sudah
     * dipakai" oleh shift yang tidak kelihatan di mana pun.
     */
    private function uniqueCode(?int $shiftId): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($shiftId): void {
            $existing = Shift::withTrashed()
                ->where('code', $value)
                ->when($shiftId, fn ($query, $id) => $query->whereKeyNot($id))
                ->first();

            if (! $existing) {
                return;
            }

            $fail($existing->trashed()
                ? "Kode {$value} masih dipakai shift \"{$existing->name}\" yang ada di arsip. Pulihkan shift itu lewat tab Arsip, atau pakai kode lain."
                : 'Kode shift sudah digunakan.');
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            ...$this->safe()->except('is_active'),
            'is_active' => $this->boolean('is_active'),
            // Overnight is derived from the times so it can never be inconsistent.
            'crosses_midnight' => $this->input('end_time') <= $this->input('start_time'),
        ];
    }
}
