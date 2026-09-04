<?php

namespace App\Support;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Satu-satunya pintu penulisan jejak aktivitas.
 *
 * Semua pencatatan lewat sini agar tiga hal tidak pernah terlewat: pelaku, alamat IP,
 * dan penyensoran kolom rahasia. Menulis langsung ke ActivityLog::create() gampang
 * melupakan salah satunya — dan sebuah baris audit tanpa pelaku tidak ada gunanya.
 */
class ActivityLogger
{
    /**
     * Kolom yang tidak boleh ikut tercatat, berapa pun modulnya. Nilai kata sandi dan
     * token sesi tidak punya alasan untuk ada di dalam catatan yang justru dibuat agar
     * bisa dibaca banyak orang.
     *
     * @var list<string>
     */
    public const REDACTED = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'api_token',
    ];

    /**
     * Kolom yang berubah pada hampir setiap penyimpanan tapi tidak berarti apa-apa
     * bagi pembacanya.
     *
     * @var list<string>
     */
    public const IGNORED = ['updated_at', 'created_at', 'remember_token'];

    /**
     * Catat satu aktivitas.
     *
     * @param  array<string, mixed>  $properties
     */
    public static function log(
        string $module,
        string $event,
        string $description,
        ?Model $subject = null,
        array $properties = [],
        ?string $subjectLabel = null,
        ?User $actor = null,
        ?string $actorName = null,
    ): ActivityLog {
        $actor ??= Auth::user();
        $request = request();

        return ActivityLog::query()->create([
            'user_id' => $actor?->id,
            // Namanya ikut disalin: baris ini harus tetap bisa dibaca kalau akunnya
            // dihapus nanti, dan nullOnDelete akan mengosongkan user_id-nya.
            // Dipotong, bukan dibiarkan: MySQL berjalan dengan STRICT_TRANS_TABLES,
            // jadi nilai yang melebihi panjang kolom akan MENGGAGALKAN tindakan yang
            // sedang dicatat — pencatatan tidak boleh sampai membatalkan pekerjaan.
            'actor_name' => self::fit($actorName ?? $actor?->name),
            'module' => $module,
            'event' => $event,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'subject_label' => self::fit($subjectLabel ?? self::labelFor($subject)),
            'description' => str($description)->limit(490)->toString(),
            'properties' => $properties === [] ? null : $properties,
            'ip_address' => $request?->ip(),
            'user_agent' => str((string) $request?->userAgent())->limit(250)->toString() ?: null,
            'created_at' => now(),
        ]);
    }

    /**
     * Catat kejadian autentikasi.
     *
     * Akun yang dituju dan pelakunya dipisah dengan sengaja: pada percobaan masuk yang
     * GAGAL, akunnya diketahui tetapi pelakunya tidak — dan menuliskan nama pemilik
     * akun sebagai pelaku sama saja menuduh orang yang justru sedang jadi sasaran.
     *
     * @param  array<string, mixed>  $properties
     */
    public static function auth(
        string $event,
        string $description,
        ?User $account = null,
        array $properties = [],
        ?User $actor = null,
        ?string $actorName = null,
    ): ActivityLog {
        return self::log('auth', $event, $description, $account, $properties, $account?->email, $actor, $actorName);
    }

    /**
     * Perubahan yang layak dicatat dari sebuah model yang baru disimpan:
     * kolom rahasia disensor, kolom teknis dibuang.
     *
     * @return array<string, array{dari: mixed, jadi: mixed}>
     */
    public static function changesOf(Model $model): array
    {
        $original = $model->getOriginal();
        $changes = [];

        foreach ($model->getChanges() as $field => $new) {
            if (in_array($field, self::IGNORED, true)) {
                continue;
            }

            $redacted = in_array($field, self::REDACTED, true);

            $changes[$field] = [
                'dari' => $redacted ? '••••••' : self::plain($original[$field] ?? null),
                'jadi' => $redacted ? '••••••' : self::plain($new),
            ];
        }

        return $changes;
    }

    /** Nama yang paling masuk akal untuk sebuah model, dicari dari kolom yang lazim. */
    public static function labelFor(?Model $model): ?string
    {
        if (! $model) {
            return null;
        }

        // Data yang berkode dicari orang lewat kodenya, tapi kode saja tidak berarti
        // apa-apa setahun kemudian — jadi keduanya disimpan sekaligus.
        // asset_code ikut dibaca: aset dicari orang lewat kode yang tertempel di
        // fisiknya, dan kolomnya memang tidak bisa bernama "code" begitu saja.
        $code = $model->getAttribute('code') ?: $model->getAttribute('asset_code');
        $name = $model->getAttribute('full_name') ?: $model->getAttribute('name');

        if (is_string($code) && $code !== '' && is_string($name) && $name !== '') {
            return "{$code} — {$name}";
        }

        foreach (['full_name', 'name', 'code', 'contract_number', 'email'] as $field) {
            $value = $model->getAttribute($field);

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return class_basename($model).' #'.$model->getKey();
    }

    /** Potong ke panjang yang pasti muat di kolom varchar(255). */
    private static function fit(?string $value): ?string
    {
        return $value === null ? null : str($value)->limit(250)->toString();
    }

    /** Nilai yang aman disimpan sebagai JSON dan enak dibaca kembali. */
    private static function plain(mixed $value): mixed
    {
        if ($value instanceof \BackedEnum) {
            return $value->value;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_object($value) || is_array($value)) {
            return json_decode(json_encode($value), true);
        }

        return $value;
    }
}
