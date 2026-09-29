<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kitaplığım (mockup 4.1): "%45 okundu" — sayfalı okumada okurun ulaştığı en ileri sayfanın
     * kitabın bütün sayfalarına oranı (0–100). Kitap her cihazda aynı sayfalara bölündüğü için
     * yüzde cihazdan bağımsız.
     */
    public function up(): void
    {
        Schema::table('reading_list_items', function (Blueprint $table) {
            $table->unsignedTinyInteger('progress')->nullable()->after('last_chapter_number');
        });
    }

    public function down(): void
    {
        Schema::table('reading_list_items', function (Blueprint $table) {
            $table->dropColumn('progress');
        });
    }
};
