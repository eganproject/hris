<?php

namespace App\Http\Controllers;

use App\Http\Requests\AssetDocumentRequest;
use App\Models\Asset;
use App\Models\AssetDocument;
use App\Support\ActivityLogger;
use App\Support\DataScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Berkas aset: faktur, kartu garansi, foto kondisi, berita acara.
 *
 * Berkasnya ada di disk privat, jadi satu-satunya jalan keluar adalah rute ini — dan
 * tiap permintaan diperiksa dua kali: izin menu lewat middleware rutenya, lalu
 * cakupan aset yang bersangkutan di sini. Menyembunyikan tombolnya di layar bukan
 * otorisasi; sebuah URL bisa ditebak.
 */
class AssetDocumentController extends Controller
{
    public function store(AssetDocumentRequest $request, Asset $asset): RedirectResponse
    {
        DataScope::forAssets($request->user())->authorizeAsset($asset);

        $document = $asset->documents()->create([
            ...AssetDocument::columnsFor($request->file('file'), $asset->id),
            'type' => $request->validated('type'),
            'title' => $request->validated('title'),
            'uploaded_by' => $request->user()->id,
        ]);

        // Dicatat eksplisit: baris dokumen tidak diawasi observer, padahal justru
        // berkas — faktur, berita acara — yang paling sering ditanyakan asal-usulnya.
        ActivityLogger::log(
            module: 'assets',
            event: 'created',
            description: "Mengunggah berkas {$document->type_label} pada aset {$asset->asset_code}.",
            subject: $asset,
            properties: ['document' => $document->original_name, 'type' => $document->type],
        );

        return redirect()->route('assets.show', $asset)->with('status', 'Berkas berhasil diunggah.');
    }

    /**
     * Tanpa parameter, berkas ditampilkan inline supaya bisa langsung dibaca di tab
     * baru; dengan ?download=1 berkasnya diunduh.
     */
    public function show(Request $request, Asset $asset, AssetDocument $document): StreamedResponse
    {
        DataScope::forAssets($request->user())->authorizeAsset($asset);

        // Dokumen milik aset lain tidak boleh ikut terbuka hanya karena idnya ditebak
        // di URL aset yang memang boleh dilihat.
        abort_unless($document->asset_id === $asset->id, 404);

        $disk = Storage::disk($document->disk ?: AssetDocument::DISK);

        // Barisnya masih menyimpan path meski berkasnya sudah hilang dari disk —
        // jangan sampai berujung error 500.
        abort_unless($disk->exists($document->path), 404, 'Berkas aset tidak ditemukan.');

        $headers = ['Content-Type' => $document->mime_type ?: 'application/octet-stream'];

        return $request->boolean('download')
            ? $disk->download($document->path, $document->original_name, $headers)
            : $disk->response($document->path, $document->original_name, $headers);
    }

    public function destroy(Request $request, Asset $asset, AssetDocument $document): RedirectResponse
    {
        DataScope::forAssets($request->user())->authorizeAsset($asset);

        abort_unless($document->asset_id === $asset->id, 404);

        $name = $document->original_name;
        $document->deleteFile();
        $document->delete();

        ActivityLogger::log(
            module: 'assets',
            event: 'deleted',
            description: "Menghapus berkas {$name} dari aset {$asset->asset_code}.",
            subject: $asset,
        );

        return redirect()->route('assets.show', $asset)->with('status', 'Berkas berhasil dihapus.');
    }
}
