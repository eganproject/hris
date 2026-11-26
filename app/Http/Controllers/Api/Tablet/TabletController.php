<?php

namespace App\Http\Controllers\Api\Tablet;

use App\Http\Controllers\Controller;
use App\Models\AttendanceTablet;
use App\Services\Tablet\TabletSyncPayload;
use App\Http\Controllers\Api\Tablet\Concerns\LogsTabletActivity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Pemasangan tablet dan sinkron data.
 */
class TabletController extends Controller
{
    use LogsTabletActivity;

    /**
     * Tablet baru memasukkan kode pasangan dari web HRIS. Kode sekali pakai;
     * token lama tablet ini (bila dipasangkan ulang) langsung dicabut.
     */
    public function pair(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20'],
            'device_model' => ['nullable', 'string', 'max:120'],
            'app_version' => ['nullable', 'string', 'max:40'],
        ]);

        $tablet = AttendanceTablet::query()
            ->where('status', '!=', AttendanceTablet::STATUS_REVOKED)
            ->whereNotNull('pairing_code_hash')
            ->where('pairing_expires_at', '>', now())
            ->get()
            ->first(fn (AttendanceTablet $t) => $t->pairingCodeMatches($data['code']));

        if (! $tablet) {
            return response()->json(['message' => 'Kode tidak cocok atau sudah kedaluwarsa. Buat kode baru di web HRIS.', 'code' => 'invalid_code'], 422);
        }

        $token = DB::transaction(function () use ($tablet, $data) {
            $tablet->tokens()->delete();
            $tablet->forceFill([
                'status' => AttendanceTablet::STATUS_ACTIVE,
                'pairing_code_hash' => null,
                'pairing_expires_at' => null,
                'paired_at' => now(),
                'last_seen_at' => now(),
                'device_model' => $data['device_model'] ?? null,
                'app_version' => $data['app_version'] ?? null,
            ])->save();

            return $tablet->createToken('tablet:'.$tablet->id)->plainTextToken;
        });

        $this->audit($tablet, 'pair', "Tablet {$tablet->name} dipasangkan ({$tablet->device_model}).");

        return response()->json(['data' => ['token' => $token, 'tablet' => ['id' => $tablet->id, 'name' => $tablet->name]]]);
    }

    /**
     * Sinkron berkala (sekitar tiap menit) sekaligus tanda tablet masih hidup.
     * Query `queue` = jumlah absen yang masih tertahan di tablet.
     */
    public function sync(Request $request, TabletSyncPayload $payload): JsonResponse
    {
        /** @var AttendanceTablet $tablet */
        $tablet = $request->user();

        $tablet->forceFill([
            'last_seen_at' => now(),
            'queue_count' => max(0, (int) $request->query('queue', 0)),
            'app_version' => $request->header('X-App-Version') ?: $tablet->app_version,
        ])->saveQuietly();

        return response()->json(['data' => $payload->build($tablet)]);
    }
}
