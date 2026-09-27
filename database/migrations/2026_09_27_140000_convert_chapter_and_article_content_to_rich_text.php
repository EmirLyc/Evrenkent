<?php

use App\Support\RichText;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Faz F1: bölüm ve makale içeriği artık temizlenmiş HTML. Eski düz metin kayıtlar
     * paragraflara çevriliyor; Filament'in zengin editöründen gelmiş (daha önce okur
     * sayfasında kaçışlı etiketlerle görünen) HTML de izinli etiketlere indiriliyor.
     * Şema değişmiyor, sadece veri.
     */
    public function up(): void
    {
        foreach (['chapters', 'articles'] as $table) {
            DB::table($table)->select(['id', 'content'])->orderBy('id')->chunkById(100, function ($rows) use ($table) {
                foreach ($rows as $row) {
                    $normalized = RichText::normalize($row->content);

                    if ($normalized !== $row->content) {
                        DB::table($table)->where('id', $row->id)->update(['content' => $normalized]);
                    }
                }
            });
        }
    }

    /**
     * Geri dönüşte HTML olduğu gibi kalır — eski görünüm (whitespace-pre-line) etiketleri
     * gösterir ama veri kaybolmaz.
     */
    public function down(): void {}
};
