<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->date('date')->index();
            $table->string('name');
            // National holidays apply everywhere; a branch_id scopes it to one location.
            $table->boolean('is_national')->default(true);
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->unique(['date', 'branch_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('holidays');
    }
};
