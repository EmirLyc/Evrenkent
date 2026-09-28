<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Faz G2 ("Yazarın Gözünden" 1.1.1–1.1.6, Yeni Yayın sayfası):
 *  - alt başlık (editörde eser adının altında: "Egemenlik, Sınırlar ve Yeni İmkanlar"),
 *  - sayfa oranı: yazar bir kez seçer, eserin bütün sayfaları için geçerli (13×20, 13×21,
 *    16×24; dergide genelde 21×27,5),
 *  - başlık numaralandırma (I. → 1.1. → 1.1.1.; roman gibi eserlerde kapatılabilir),
 *  - dergi yazısına da "Kapak ve Tanıtım" adımı: kapak + tanıtım metni,
 *  - belge türü: metin içinde görünen görsel ("Ekle → Görsel") ile kar taneli belge ayrı.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->string('subtitle')->nullable()->after('title');
            $table->string('page_ratio', 10)->default('13x20')->after('description');
            $table->boolean('heading_numbering')->default(true)->after('page_ratio');
        });

        Schema::table('articles', function (Blueprint $table) {
            $table->string('subtitle')->nullable()->after('title');
            $table->text('description')->nullable()->after('subtitle');
            $table->string('cover_image')->nullable()->after('description');
            $table->string('page_ratio', 10)->default('21x27.5')->after('cover_image');
            $table->boolean('heading_numbering')->default(true)->after('page_ratio');
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->string('kind', 10)->default('belge')->after('documentable_id');
        });

        // Kitap editörde tek belge: her "Başlık 1" bir bölüm. İlk başlıktan önceki metin başlıksız
        // bir giriş bölümü olur ve bölüm numaralamasına (I., II.…) katılmaz.
        Schema::table('chapters', function (Blueprint $table) {
            $table->boolean('is_preface')->default(false)->after('order');
        });
    }

    public function down(): void
    {
        Schema::table('chapters', fn (Blueprint $table) => $table->dropColumn('is_preface'));
        Schema::table('documents', fn (Blueprint $table) => $table->dropColumn('kind'));
        Schema::table('articles', fn (Blueprint $table) => $table->dropColumn(['subtitle', 'description', 'cover_image', 'page_ratio', 'heading_numbering']));
        Schema::table('books', fn (Blueprint $table) => $table->dropColumn(['subtitle', 'page_ratio', 'heading_numbering']));
    }
};
