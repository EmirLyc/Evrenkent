<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Faz H5 ("Okurun Gözünden" — Defterim 1): Defterim artık tek tek girdiler değil, zengin metinli
 * defterler (başlık, alt başlık, etiketler, "deftere bilgi"). Defterler notes tablosunda
 * type = defter olarak kalıyor (kota ve sahiplik aynı yoldan); içerik HTML. Eski düz metin
 * girdiler paragraflara çevriliyor — silinmiyor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notes', function (Blueprint $table) {
            $table->string('subtitle')->nullable()->after('title');
            $table->json('tags')->nullable()->after('page');
            $table->text('info')->nullable()->after('tags');
        });

        DB::table('notes')->where('type', 'defter')->orderBy('id')->each(function ($note) {
            $content = (string) $note->content;
            if ($content !== '' && ! str_starts_with(ltrim($content), '<')) {
                $paragraphs = preg_split('/\R{2,}/u', trim($content));
                $content = collect($paragraphs)
                    ->map(fn ($paragraph) => '<p>'.nl2br(htmlspecialchars($paragraph, ENT_QUOTES, 'UTF-8'), false).'</p>')
                    ->implode('');
            }

            DB::table('notes')->where('id', $note->id)->update([
                'title' => $note->title ?: 'Defter',
                'content' => $content,
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('notes', function (Blueprint $table) {
            $table->dropColumn(['subtitle', 'tags', 'info']);
        });
    }
};
