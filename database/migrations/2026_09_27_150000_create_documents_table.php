<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Faz F2 (mockup 3 / 3.1): metne gömülü belgeler — kar tanesi ikonu, üstüne gelince
     * "ad (tarih / N sayfa)", tıklayınca site içinde açılır, indirilmez. Belge bir kitaba
     * ya da makaleye ait; içerikte <span data-document="id"> ile yerleştiriliyor.
     *
     * date_label serbest metin: arşiv belgelerinde tarih çoğu zaman eksik ("1873",
     * "Haziran 1873") ya da takvim dönüşümlü olabiliyor, date sütunu bunu taşıyamaz.
     */
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->morphs('documentable');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('date_label')->nullable();
            $table->unsignedInteger('page_count')->nullable();
            $table->string('file_path');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size');
            $table->string('original_name');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
