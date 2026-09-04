<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Berkas milik sebuah aset: faktur, kartu garansi, foto kondisi, berita acara.
 *
 * Berkasnya ada di disk privat dan tidak punya URL publik — satu-satunya jalan
 * keluar adalah AssetDocumentController yang memeriksa cakupan peminta, sama seperti
 * dokumen kontrak karyawan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();

            $table->string('type', 30)->default('other');
            $table->string('title')->nullable();

            $table->string('disk', 30)->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('size')->nullable();

            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['asset_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_documents');
    }
};
