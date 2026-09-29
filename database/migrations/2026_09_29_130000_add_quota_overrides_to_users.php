<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Hesaba özel çalışma alanı sınırları (ücretsiz hesap): {"defter": 5, "not": 30, "alinti": 30,
     * "defter_words": 3000}. Boş olan anahtar Premium Sistemi'ndeki genel ayarı kullanır; premium
     * üyede sınır yok. Süper Admin kullanıcı formundan ayarlar.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('quota_overrides')->nullable()->after('premium_until');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('quota_overrides');
        });
    }
};
