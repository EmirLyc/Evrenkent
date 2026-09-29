<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Faz H3 ("Okurun Gözünden" — Okuma moduna dair): okur metinde seçip Alıntıla / Not Al /
 * Fosforla diyor; işaret metnin içinde görünüyor. Notlara metindeki yer ekleniyor:
 *  - quote: seçilen metin (notta "Kaynak Metin"; alıntı ve fosforda content ile aynı),
 *  - anchor: {chapter, start, end, prefix, suffix} — bölüm sırası ve bölüm metnindeki konum;
 *    yazar metni sonradan düzeltse de seçilen metin + çevresiyle yeniden bulunuyor,
 *  - page: kaydedildiği sayfanın numarası ("s. 24" — dizgi sabit, G4).
 * Tür olarak "fosfor" da geliyor (kota dışı, hiçbir listeye gitmiyor). Eski notlar konumsuz kalıyor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notes', function (Blueprint $table) {
            $table->text('quote')->nullable()->after('content');
            $table->json('anchor')->nullable()->after('quote');
            $table->string('page', 16)->nullable()->after('anchor');
        });
    }

    public function down(): void
    {
        Schema::table('notes', function (Blueprint $table) {
            $table->dropColumn(['quote', 'anchor', 'page']);
        });
    }
};
