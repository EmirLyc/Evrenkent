<?php

use App\Support\RichText;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Arama için düz metin kopyası. İçerik Faz F1'den beri temizlenmiş HTML; arama HTML
     * üzerinde yapılınca etiket adları ("span") eşleşiyor, kesme işaretli kelimeler
     * ("Paşa'nın" — HTML'de "Paşa&#039;nın") bulunamıyordu. Kayıtta HasRichContent dolduruyor.
     */
    public function up(): void
    {
        foreach (['chapters', 'articles'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->longText('content_text')->nullable()->after('content');
            });

            DB::table($table)->select(['id', 'content'])->orderBy('id')->chunkById(100, function ($rows) use ($table) {
                foreach ($rows as $row) {
                    DB::table($table)->where('id', $row->id)->update(['content_text' => RichText::plainText($row->content)]);
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['chapters', 'articles'] as $table) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropColumn('content_text'));
        }
    }
};
