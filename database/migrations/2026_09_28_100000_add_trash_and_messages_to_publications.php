<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Faz G1 ("Yazarın Gözünden" revizesi, 2026-09-28):
 *  - Taslakta silme iki kademeli: silinen eser önce çöp kutusuna gider (soft delete).
 *  - Taslaklarım kartlarındaki "Sohbet / Mesajlar": yazar ile inceleyen (Süper Admin,
 *    makalede dergi editörü) arasında esere bağlı yazışma.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('books', fn (Blueprint $table) => $table->softDeletes());
        Schema::table('articles', fn (Blueprint $table) => $table->softDeletes());

        Schema::create('content_messages', function (Blueprint $table) {
            $table->id();
            $table->morphs('messageable');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_messages');
        Schema::table('articles', fn (Blueprint $table) => $table->dropSoftDeletes());
        Schema::table('books', fn (Blueprint $table) => $table->dropSoftDeletes());
    }
};
