<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Berkas milik sebuah aset. Disimpan di disk privat — tidak ada URL publik, dan
 * satu-satunya jalan keluar adalah AssetDocumentController yang memeriksa cakupan
 * peminta lebih dulu (pola yang sama dengan dokumen kontrak karyawan).
 */
class AssetDocument extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'asset_id',
        'type',
        'title',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size',
        'uploaded_by',
    ];

    /** Disk privat: berkas aset tidak pernah dilayani langsung oleh web server. */
    public const DISK = 'local';

    public const MAX_MB = 5;

    /** type => label. */
    public const TYPE_LABELS = [
        'invoice' => 'Faktur / Bukti Beli',
        'warranty' => 'Kartu Garansi',
        'photo' => 'Foto Kondisi',
        'handover' => 'Berita Acara',
        'other' => 'Lainnya',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPE_LABELS[$this->type] ?? 'Lainnya';
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime_type, 'image/');
    }

    public function sizeLabel(): string
    {
        $bytes = (int) $this->size;

        return $bytes >= 1048576
            ? round($bytes / 1048576, 1).' MB'
            : max(1, (int) round($bytes / 1024)).' KB';
    }

    /**
     * Simpan berkas unggahan dan kembalikan kolom-kolomnya. Disimpan per aset supaya
     * berkas satu barang mudah ditelusuri di disk.
     *
     * @return array<string, mixed>
     */
    public static function columnsFor(UploadedFile $file, int $assetId): array
    {
        return [
            'disk' => self::DISK,
            'path' => $file->store("asset-documents/{$assetId}", self::DISK),
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
        ];
    }

    /**
     * Buang berkasnya dari disk. Tanpa ini disk terus menumpuk berkas yatim yang
     * tidak lagi bisa dijangkau lewat rute mana pun.
     */
    public function deleteFile(): void
    {
        if ($this->path) {
            Storage::disk($this->disk ?: self::DISK)->delete($this->path);
        }
    }

    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }
}
