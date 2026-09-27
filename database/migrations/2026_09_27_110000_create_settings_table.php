<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Süper Admin'in panelden değiştirebildiği platform ayarları (plan fiyatları, premium
     * indirim oranı, çalışma alanı kotaları...) — basit anahtar/değer. Satır yoksa
     * App\Support\PlatformSettings içindeki varsayılan kullanılır.
     */
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
