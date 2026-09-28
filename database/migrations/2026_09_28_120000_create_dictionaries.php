<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Faz G3 ("Sözlüğe Dair"): sözlük ayrı bir yayın türü — fiyat, satın alma, onay, yayın ve okuma
 * kitapla aynı olduğu için `books.kind` (kitap | sozluk). Sözlükteki kavramlar bir veri nesnesi:
 * editörde "Kavram" ile işaretlenen satır bir madde başlatır, sonraki kavrama kadar olan metin o
 * maddeye aittir; başka eserler bu maddelere "Sözlüğe Bağla" ile bağlanır.
 *
 * Maddeler her kayıtta metinden yeniden çıkarılıyor (DictionaryDocument). Kimlik kavram
 * satırındaki anahtar (`<p data-concept="anahtar">`): kavramın adı değişse de madde — ve ona
 * verilen bağlantılar — korunur.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->string('kind', 10)->default('kitap')->after('author_id')->index();
        });

        Schema::create('dictionary_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->string('key', 32);
            $table->string('term');
            $table->string('slug');
            // Arama için küçük harfli (Türkçe İ/ı kurallarıyla) kavram adı.
            $table->string('term_search')->index();
            $table->unsignedInteger('position');
            // Maddenin bulunduğu bölüm (okuma sayfası kitaplar.oku/{order}#madde-{key}).
            $table->unsignedInteger('chapter_order');
            $table->longText('content')->nullable();
            $table->text('excerpt')->nullable();
            $table->timestamps();

            $table->unique(['book_id', 'key']);
            $table->unique(['book_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dictionary_entries');
        Schema::table('books', fn (Blueprint $table) => $table->dropColumn('kind'));
    }
};
