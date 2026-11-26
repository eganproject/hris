<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\MobileDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Aplikasi HP mendaftarkan token Firebase-nya di sini setelah login (dan setiap
 * kali Firebase memberi token baru), supaya notifikasi bisa dikirim ke HP itu.
 */
class PushTokenController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['token' => ['required', 'string', 'max:512']]);

        /** @var MobileDevice $device */
        $device = $request->attributes->get('mobile_device');

        // Satu token hanya milik satu HP: bila HP dipakai bergantian akun,
        // akun sebelumnya tidak boleh ikut menerima notifikasi.
        MobileDevice::query()
            ->where('push_token', $data['token'])
            ->whereKeyNot($device->id)
            ->update(['push_token' => null, 'push_token_updated_at' => now()]);

        $device->forceFill(['push_token' => $data['token'], 'push_token_updated_at' => now()])->save();

        return response()->json(['data' => ['ok' => true]]);
    }

    public function destroy(Request $request): JsonResponse
    {
        /** @var MobileDevice $device */
        $device = $request->attributes->get('mobile_device');
        $device->forceFill(['push_token' => null, 'push_token_updated_at' => now()])->save();

        return response()->json(['data' => ['ok' => true]]);
    }
}
